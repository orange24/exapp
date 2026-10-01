<?php

namespace Tests\Feature\Sanction;

use App\Livewire\Sanction\ReviewQueue;
use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionEntryName;
use App\Models\SanctionFpClearance;
use App\Models\SanctionScreening;
use App\Services\Sanction\Dto\ScreeningInput;
use App\Services\Sanction\NameNormalizer;
use App\Services\Sanction\SanctionScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class ReviewQueueTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function pendingScreening(string $name = 'AMRAN MING'): SanctionScreening
    {
        $entry = SanctionEntry::create([
            'list_code' => SanctionEntry::LIST_FREEZE_05_TH,
            'source_ref' => (string) random_int(1, 999999),
            'name_en' => $name, 'nationality' => 'TH', 'status' => 'Designated person',
            'content_hash' => hash('sha256', $name),
            'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        SanctionEntryName::create([
            'sanction_entry_id' => $entry->id, 'name_raw' => $name,
            'name_normalized' => NameNormalizer::normalize($name),
            'name_soundex' => NameNormalizer::soundexOf($name),
            'script' => 'latin', 'is_primary' => true,
        ]);

        $customer = Customer::create([
            'type' => 'individual', 'id_type' => 'passport',
            'id_number' => 'AA' . random_int(100000, 999999),
            'name_en' => $name, 'nationality' => 'TH', 'kyc_status' => 'approved',
        ]);

        return app(SanctionScreeningService::class)->screen(
            input: ScreeningInput::fromCustomer($customer),
            trigger: SanctionScreening::TRIGGER_RESCAN,
            screenedBy: $this->staffUser->id,
            customerId: $customer->id,
            branchId: $this->branch->id,
        );
    }

    public function test_queue_lists_screenings_awaiting_a_decision(): void
    {
        $this->pendingScreening();

        Livewire::actingAs($this->adminUser)
            ->test(ReviewQueue::class)
            ->assertSee('AMRAN MING');
    }

    public function test_decided_screenings_disappear_from_the_queue(): void
    {
        $screening = $this->pendingScreening();

        app(SanctionScreeningService::class)->decide(
            screening: $screening,
            decision: SanctionScreening::DECISION_FALSE_POSITIVE,
            decidedBy: $this->adminUser->id,
            reason: 'ตรวจพาสปอร์ตเล่มจริงแล้ว คนละคน วันเกิดต่างกัน 12 ปี',
        );

        Livewire::actingAs($this->adminUser)
            ->test(ReviewQueue::class)
            ->assertDontSee('AMRAN MING');
    }

    public function test_bulk_decide_clears_many_at_once(): void
    {
        $a = $this->pendingScreening('AMRAN MING');
        $b = $this->pendingScreening('ROWI HAYIDING');

        Livewire::actingAs($this->adminUser)
            ->test(ReviewQueue::class)
            ->set('selected', [$a->id, $b->id])
            ->set('bulkReason', 'ตรวจเอกสารครบแล้ว ไม่ตรงกับรายชื่อ เป็นคนละบุคคล')
            ->call('bulkDecide', SanctionScreening::DECISION_FALSE_POSITIVE)
            ->assertHasNoErrors();

        $this->assertSame(0, SanctionScreening::awaitingDecision()->count());
        $this->assertSame(2, SanctionFpClearance::count());
    }

    public function test_bulk_decide_rejects_short_reason(): void
    {
        $a = $this->pendingScreening();

        Livewire::actingAs($this->adminUser)
            ->test(ReviewQueue::class)
            ->set('selected', [$a->id])
            ->set('bulkReason', 'ok')
            ->call('bulkDecide', SanctionScreening::DECISION_FALSE_POSITIVE)
            ->assertHasErrors('bulkReason');

        $this->assertSame(1, SanctionScreening::awaitingDecision()->count());
    }

    public function test_user_without_approve_permission_cannot_decide(): void
    {
        // สิทธิ์ module7 ถูก seed โดย SeedsTestData (ดูแผนที่ 2 Task 7)
        $a = $this->pendingScreening();

        Livewire::actingAs($this->staffUser)
            ->test(ReviewQueue::class)
            ->set('selected', [$a->id])
            ->set('bulkReason', 'ตรวจเอกสารครบแล้ว ไม่ตรงกับรายชื่อ เป็นคนละบุคคล')
            ->call('bulkDecide', SanctionScreening::DECISION_FALSE_POSITIVE)
            ->assertForbidden();
    }

    public function test_queue_is_sorted_by_score_descending(): void
    {
        $this->pendingScreening('AMRAN MING');
        $this->pendingScreening('ROWI HAYIDING');

        $component = Livewire::actingAs($this->adminUser)->test(ReviewQueue::class);

        $scores = collect($component->get('rows'))->pluck('top_score')->map(fn ($s) => (float) $s)->all();

        $this->assertSame($scores, collect($scores)->sortDesc()->values()->all());
    }
}
