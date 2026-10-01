<?php

namespace Tests\Feature\Sanction;

use App\Models\SanctionEntry;
use App\Models\SanctionEntryName;
use App\Models\SanctionScreening;
use App\Services\Sanction\NameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class CustomerScreeningTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function seedSanctionedPerson(string $name = 'AMRAN MING'): SanctionEntry
    {
        $entry = SanctionEntry::create([
            'list_code' => SanctionEntry::LIST_FREEZE_05_TH,
            'source_ref' => '1',
            'name_en' => $name,
            'nationality' => 'TH',
            'status' => 'Designated person',
            'content_hash' => hash('sha256', $name),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        SanctionEntryName::create([
            'sanction_entry_id' => $entry->id,
            'name_raw' => $name,
            'name_normalized' => NameNormalizer::normalize($name),
            'name_soundex' => NameNormalizer::soundexOf($name),
            'script' => 'latin',
            'is_primary' => true,
        ]);

        return $entry;
    }

    public function test_creating_a_sanctioned_customer_is_allowed_but_queued_for_review(): void
    {
        $this->seedSanctionedPerson();

        $this->actingAsAdmin()->post(route('admin.customers.store'), [
            'type' => 'individual',
            'id_type' => 'passport',
            'id_number' => 'AA123456',
            'name_en' => 'AMRAN MING',
            'nationality' => 'TH',
        ])->assertRedirect(route('admin.customers.index'));

        // การบันทึกลูกค้าต้องสำเร็จเสมอ ถ้าบล็อกที่นี่พนักงานจะเลี่ยง
        // ไม่สร้างประวัติลูกค้า แล้วร่องรอยหลักฐานจะหายไปทั้งหมด
        $this->assertDatabaseHas('customers', ['id_number' => 'AA123456']);

        $screening = SanctionScreening::where('trigger', SanctionScreening::TRIGGER_CUSTOMER_CREATE)->first();

        $this->assertNotNull($screening);
        $this->assertSame(SanctionScreening::RESULT_POTENTIAL_MATCH, $screening->result);
        $this->assertTrue($screening->needsDecision());
    }

    public function test_editing_a_customer_into_a_sanctioned_name_is_also_screened(): void
    {
        $this->seedSanctionedPerson();

        $customer = \App\Models\Customer::create([
            'type' => 'individual',
            'id_type' => 'passport',
            'id_number' => 'BB987654',
            'name_en' => 'SOMCHAI JAIDEE',
            'nationality' => 'TH',
        ]);

        $this->actingAsAdmin()->put(route('admin.customers.update', $customer), [
            'type' => 'individual',
            'id_type' => 'passport',
            'id_number' => 'BB987654',
            'name_en' => 'AMRAN MING',
            'nationality' => 'TH',
        ])->assertRedirect(route('admin.customers.index'));

        $this->assertSame('AMRAN MING', $customer->fresh()->name_en);

        $screening = SanctionScreening::where('trigger', SanctionScreening::TRIGGER_CUSTOMER_CREATE)
            ->where('customer_id', $customer->id)
            ->first();

        $this->assertNotNull($screening);
        $this->assertSame(SanctionScreening::RESULT_POTENTIAL_MATCH, $screening->result);
        $this->assertTrue($screening->needsDecision());
    }

    public function test_a_clean_customer_is_screened_and_recorded_as_clear(): void
    {
        $this->seedSanctionedPerson();

        $this->actingAsAdmin()->post(route('admin.customers.store'), [
            'type' => 'individual',
            'id_type' => 'national_id',
            'id_number' => '1234567890123',
            'name_en' => 'WICHAI PRASERT',
            'nationality' => 'TH',
        ])->assertRedirect(route('admin.customers.index'));

        $screening = SanctionScreening::where('trigger', SanctionScreening::TRIGGER_CUSTOMER_CREATE)->first();

        $this->assertNotNull($screening, 'ทุกการบันทึกลูกค้าต้องมีร่องรอยการตรวจ แม้ผลจะไม่เข้าข่าย');
        $this->assertSame(SanctionScreening::RESULT_CLEAR, $screening->result);
        $this->assertFalse($screening->needsDecision());
    }
}
