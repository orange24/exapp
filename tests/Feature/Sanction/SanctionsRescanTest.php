<?php

namespace Tests\Feature\Sanction;

use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionEntryName;
use App\Models\SanctionScreening;
use App\Services\Sanction\NameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class SanctionsRescanTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function entry(string $name, Carbon|string|null $seenAt = null): SanctionEntry
    {
        $seen = $seenAt !== null ? Carbon::parse($seenAt) : now();

        $entry = SanctionEntry::create([
            'list_code' => SanctionEntry::LIST_FREEZE_05_TH,
            'source_ref' => (string) random_int(1, 999999),
            'name_en' => $name, 'nationality' => 'TH', 'status' => 'Designated person',
            'content_hash' => hash('sha256', $name),
            'first_seen_at' => $seen,
            'last_seen_at' => $seen,
        ]);

        // sync จริงจะไม่แตะ entry ที่เนื้อหาไม่เปลี่ยนเลย -> updated_at ต้องเก่าตามไปด้วย
        // ถ้าปล่อยเป็น now() fixture จะไม่เหมือนของจริง และ --since จะดูเหมือนพัง
        SanctionEntry::whereKey($entry->id)->update([
            'created_at' => $seen,
            'updated_at' => $seen,
        ]);

        $entry->refresh();

        SanctionEntryName::create([
            'sanction_entry_id' => $entry->id, 'name_raw' => $name,
            'name_normalized' => NameNormalizer::normalize($name),
            'name_soundex' => NameNormalizer::soundexOf($name),
            'script' => 'latin', 'is_primary' => true,
        ]);

        return $entry;
    }

    private function customer(string $name): Customer
    {
        return Customer::create([
            'type' => 'individual', 'id_type' => 'passport',
            'id_number' => 'AA' . random_int(100000, 999999),
            'name_en' => $name, 'nationality' => 'TH', 'kyc_status' => 'approved',
        ]);
    }

    public function test_rescan_all_screens_every_customer(): void
    {
        $this->entry('AMRAN MING');
        $this->customer('AMRAN MING');
        $this->customer('SOMCHAI JAIDEE');

        $this->artisan('sanctions:rescan', ['--all' => true])->assertExitCode(0);

        $this->assertSame(2, SanctionScreening::where('trigger', SanctionScreening::TRIGGER_RESCAN)->count());
        $this->assertSame(1, SanctionScreening::awaitingDecision()->count());
    }

    public function test_rescan_without_all_only_uses_recently_changed_entries(): void
    {
        // entry เก่า — ไม่ควรถูกนำมาสแกนรอบนี้
        $this->entry('OLD PERSON', seenAt: now()->subDays(10));
        $this->customer('OLD PERSON');

        $this->artisan('sanctions:rescan', ['--since' => now()->subDay()->toDateTimeString()])
            ->assertExitCode(0);

        $this->assertSame(
            0,
            SanctionScreening::awaitingDecision()->count(),
            'entry ที่ไม่เปลี่ยนตั้งแต่ --since ต้องไม่ถูกนำมาสแกน'
        );
    }

    public function test_rescan_flags_customer_matching_a_newly_added_entry(): void
    {
        $this->customer('AMRAN MING');
        $this->entry('AMRAN MING');   // first_seen_at = now()

        $this->artisan('sanctions:rescan', ['--since' => now()->subMinute()->toDateTimeString()])
            ->assertExitCode(0);

        $this->assertSame(1, SanctionScreening::awaitingDecision()->count());
    }

    public function test_rescan_closes_open_hits_for_delisted_entries(): void
    {
        $entry = $this->entry('AMRAN MING');
        $customer = $this->customer('AMRAN MING');

        $screening = app(\App\Services\Sanction\SanctionScreeningService::class)->screen(
            input: \App\Services\Sanction\Dto\ScreeningInput::fromCustomer($customer),
            trigger: SanctionScreening::TRIGGER_RESCAN,
            screenedBy: $this->adminUser->id,
            customerId: $customer->id,
        );

        $this->assertTrue($screening->needsDecision());

        $entry->update(['delisted_at' => now()]);

        $this->artisan('sanctions:rescan', ['--close-delisted' => true])->assertExitCode(0);

        $screening->refresh();

        $this->assertNotNull($screening->decision);
        $this->assertStringContainsString('เพิกถอน', (string) $screening->decision_reason);
    }
}
