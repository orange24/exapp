<?php

namespace Tests\Feature\Sanction;

use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionEntryIdentifier;
use App\Models\SanctionEntryName;
use App\Models\SanctionFpClearance;
use App\Models\SanctionScreening;
use App\Services\Sanction\Dto\ScreeningInput;
use App\Services\Sanction\NameNormalizer;
use App\Services\Sanction\SanctionScreeningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class SanctionScreeningServiceTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function service(): SanctionScreeningService
    {
        return app(SanctionScreeningService::class);
    }

    private function makeEntry(string $nameEn, ?string $nationalId = null): SanctionEntry
    {
        $entry = SanctionEntry::create([
            'list_code' => SanctionEntry::LIST_FREEZE_05_TH,
            'source_ref' => (string) random_int(1000, 999999),
            'name_en' => $nameEn,
            'nationality' => 'TH',
            'date_of_birth' => '18-12-1981',
            'national_id' => $nationalId,
            'status' => 'Designated person',
            'content_hash' => hash('sha256', $nameEn),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        SanctionEntryName::create([
            'sanction_entry_id' => $entry->id,
            'name_raw' => $nameEn,
            'name_normalized' => NameNormalizer::normalize($nameEn),
            'name_soundex' => NameNormalizer::soundexOf($nameEn),
            'script' => 'latin',
            'is_primary' => true,
        ]);

        if ($nationalId !== null) {
            SanctionEntryIdentifier::create([
                'sanction_entry_id' => $entry->id,
                'type' => 'national_id',
                'value_raw' => $nationalId,
                'value_normalized' => $nationalId,
            ]);
        }

        return $entry;
    }

    public function test_clear_result_is_still_recorded_as_evidence(): void
    {
        $screening = $this->service()->screen(
            input: new ScreeningInput(name: 'SOMCHAI JAIDEE', idType: 'passport', idNumber: 'AA123456'),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
            branchId: $this->branch->id,
            counterId: $this->counter->id,
        );

        $this->assertSame(SanctionScreening::RESULT_CLEAR, $screening->result);
        $this->assertSame(1, SanctionScreening::count(), 'ต้องบันทึกแม้ผลเป็น clear เพื่อพิสูจน์ว่าได้ตรวจ');
        $this->assertSame(0, $screening->matches()->count());
    }

    public function test_input_snapshot_is_stored(): void
    {
        $screening = $this->service()->screen(
            input: new ScreeningInput(
                name: 'SOMCHAI JAIDEE',
                idType: 'passport',
                idNumber: 'AA123456',
                nationality: 'THA',
                dob: '1990-05-05',
            ),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
        );

        $this->assertSame('SOMCHAI JAIDEE', $screening->input_name);
        $this->assertSame('passport', $screening->input_id_type);
        $this->assertSame('AA123456', $screening->input_id_number);
        $this->assertSame('THA', $screening->input_nationality);
        $this->assertSame('1990-05-05', $screening->input_dob);
    }

    public function test_exact_national_id_produces_confirmed_match(): void
    {
        $entry = $this->makeEntry('AMRAN MING', '5960500028101');

        $screening = $this->service()->screen(
            input: new ScreeningInput(name: 'AMRAN MING', idType: 'national_id', idNumber: '5960500028101'),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
        );

        $this->assertSame(SanctionScreening::RESULT_CONFIRMED_MATCH, $screening->result);
        $this->assertTrue($screening->isBlocked());
        $this->assertSame(100.0, (float) $screening->top_score);
        $this->assertSame(1, $screening->matches()->count());
        $this->assertSame($entry->id, $screening->matches()->first()->sanction_entry_id);
    }

    public function test_name_only_match_produces_potential_match_not_blocked(): void
    {
        $this->makeEntry('AMRAN MING');

        $screening = $this->service()->screen(
            input: new ScreeningInput(name: 'AMRAN MING', nationality: 'TH'),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
        );

        $this->assertSame(SanctionScreening::RESULT_POTENTIAL_MATCH, $screening->result);
        $this->assertFalse($screening->isBlocked());
        $this->assertTrue($screening->needsDecision());
    }

    public function test_hr_lists_never_produce_a_blocking_result(): void
    {
        $entry = $this->makeEntry('AMRAN MING', '5960500028101');
        $entry->update(['list_code' => SanctionEntry::LIST_HR_08]);

        $screening = $this->service()->screen(
            input: new ScreeningInput(name: 'AMRAN MING', idType: 'national_id', idNumber: '5960500028101'),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
        );

        $this->assertSame(
            SanctionScreening::RESULT_POTENTIAL_MATCH,
            $screening->result,
            'hr_02/hr_08 เป็นข้อมูลประกอบการประเมินความเสี่ยง ไม่ใช่หน้าที่อายัด'
        );
    }

    public function test_cleared_false_positive_is_not_flagged_again(): void
    {
        $entry = $this->makeEntry('AMRAN MING');

        $customer = Customer::create([
            'type' => 'individual',
            'id_type' => 'passport',
            'id_number' => 'AA999999',
            'name_en' => 'AMRAN MING',
            'nationality' => 'TH',
            'kyc_status' => 'approved',
        ]);

        SanctionFpClearance::create([
            'customer_id' => $customer->id,
            'sanction_entry_id' => $entry->id,
            'cleared_by' => $this->adminUser->id,
            'cleared_at' => now(),
            'reason' => 'ตรวจพาสปอร์ตเล่มจริงแล้ว คนละคน วันเกิดต่างกัน 12 ปี',
            'entry_content_hash' => $entry->content_hash,
            'customer_identity_hash' => SanctionFpClearance::identityHashFor($customer),
        ]);

        $screening = $this->service()->screen(
            input: ScreeningInput::fromCustomer($customer),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
            customerId: $customer->id,
        );

        $this->assertSame(SanctionScreening::RESULT_CLEAR, $screening->result);
        $this->assertSame(1, SanctionScreening::count(), 'ยังต้องบันทึก screening แม้จะเคลียร์แล้ว');
    }

    public function test_clearance_expires_when_amlo_changes_the_entry(): void
    {
        $entry = $this->makeEntry('AMRAN MING');

        $customer = Customer::create([
            'type' => 'individual',
            'id_type' => 'passport',
            'id_number' => 'AA999999',
            'name_en' => 'AMRAN MING',
            'nationality' => 'TH',
            'kyc_status' => 'approved',
        ]);

        SanctionFpClearance::create([
            'customer_id' => $customer->id,
            'sanction_entry_id' => $entry->id,
            'cleared_by' => $this->adminUser->id,
            'cleared_at' => now(),
            'reason' => 'ตรวจพาสปอร์ตเล่มจริงแล้ว คนละคน วันเกิดต่างกัน 12 ปี',
            'entry_content_hash' => $entry->content_hash,
            'customer_identity_hash' => SanctionFpClearance::identityHashFor($customer),
        ]);

        $entry->update(['content_hash' => hash('sha256', 'ปปง. แก้ข้อมูลแล้ว')]);

        $screening = $this->service()->screen(
            input: ScreeningInput::fromCustomer($customer),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
            customerId: $customer->id,
        );

        $this->assertSame(SanctionScreening::RESULT_POTENTIAL_MATCH, $screening->result);
    }

    public function test_clearance_expires_when_customer_identity_changes(): void
    {
        $entry = $this->makeEntry('AMRAN MING');

        $customer = Customer::create([
            'type' => 'individual',
            'id_type' => 'passport',
            'id_number' => 'AA999999',
            'name_en' => 'AMRAN MING',
            'nationality' => 'TH',
            'kyc_status' => 'approved',
        ]);

        SanctionFpClearance::create([
            'customer_id' => $customer->id,
            'sanction_entry_id' => $entry->id,
            'cleared_by' => $this->adminUser->id,
            'cleared_at' => now(),
            'reason' => 'ตรวจพาสปอร์ตเล่มจริงแล้ว คนละคน วันเกิดต่างกัน 12 ปี',
            'entry_content_hash' => $entry->content_hash,
            'customer_identity_hash' => SanctionFpClearance::identityHashFor($customer),
        ]);

        $customer->update(['id_number' => 'BB888888']);

        $screening = $this->service()->screen(
            input: ScreeningInput::fromCustomer($customer->fresh()),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
            customerId: $customer->id,
        );

        $this->assertSame(SanctionScreening::RESULT_POTENTIAL_MATCH, $screening->result);
    }

    public function test_clearance_for_one_customer_does_not_cover_another(): void
    {
        $entry = $this->makeEntry('AMRAN MING');

        $cleared = Customer::create([
            'type' => 'individual', 'id_type' => 'passport', 'id_number' => 'AA111111',
            'name_en' => 'AMRAN MING', 'nationality' => 'TH', 'kyc_status' => 'approved',
        ]);

        SanctionFpClearance::create([
            'customer_id' => $cleared->id,
            'sanction_entry_id' => $entry->id,
            'cleared_by' => $this->adminUser->id,
            'cleared_at' => now(),
            'reason' => 'ตรวจพาสปอร์ตเล่มจริงแล้ว คนละคน วันเกิดต่างกัน 12 ปี',
            'entry_content_hash' => $entry->content_hash,
            'customer_identity_hash' => SanctionFpClearance::identityHashFor($cleared),
        ]);

        $other = Customer::create([
            'type' => 'individual', 'id_type' => 'passport', 'id_number' => 'AA222222',
            'name_en' => 'AMRAN MING', 'nationality' => 'TH', 'kyc_status' => 'approved',
        ]);

        $screening = $this->service()->screen(
            input: ScreeningInput::fromCustomer($other),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
            customerId: $other->id,
        );

        $this->assertSame(SanctionScreening::RESULT_POTENTIAL_MATCH, $screening->result);
    }

    public function test_empty_input_records_a_screening_marked_as_not_screened(): void
    {
        $screening = $this->service()->screen(
            input: new ScreeningInput(),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
        );

        $this->assertSame(SanctionScreening::RESULT_CLEAR, $screening->result);
        $this->assertNull($screening->input_name);
        $this->assertSame(0.0, (float) $screening->top_score);
    }

    public function test_approve_records_decision_and_creates_clearance(): void
    {
        $entry = $this->makeEntry('AMRAN MING');

        $customer = Customer::create([
            'type' => 'individual', 'id_type' => 'passport', 'id_number' => 'AA999999',
            'name_en' => 'AMRAN MING', 'nationality' => 'TH', 'kyc_status' => 'approved',
        ]);

        $screening = $this->service()->screen(
            input: ScreeningInput::fromCustomer($customer),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
            customerId: $customer->id,
        );

        $this->service()->decide(
            screening: $screening,
            decision: SanctionScreening::DECISION_FALSE_POSITIVE,
            decidedBy: $this->adminUser->id,
            reason: 'ตรวจพาสปอร์ตเล่มจริงแล้ว วันเกิดต่างกัน 12 ปี คนละคนแน่นอน',
        );

        $screening->refresh();

        $this->assertSame(SanctionScreening::DECISION_FALSE_POSITIVE, $screening->decision);
        $this->assertSame($this->adminUser->id, $screening->decided_by);
        $this->assertNotNull($screening->decided_at);
        $this->assertSame(
            $this->staffUser->id,
            $screening->screened_by,
            'screened_by ต้องยังเป็นพนักงาน ไม่ใช่ผู้อนุมัติ'
        );

        $this->assertSame(1, SanctionFpClearance::where('customer_id', $customer->id)->count());
    }

    public function test_true_match_decision_does_not_create_clearance(): void
    {
        $this->makeEntry('AMRAN MING');

        $customer = Customer::create([
            'type' => 'individual', 'id_type' => 'passport', 'id_number' => 'AA999999',
            'name_en' => 'AMRAN MING', 'nationality' => 'TH', 'kyc_status' => 'approved',
        ]);

        $screening = $this->service()->screen(
            input: ScreeningInput::fromCustomer($customer),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
            customerId: $customer->id,
        );

        $this->service()->decide(
            screening: $screening,
            decision: SanctionScreening::DECISION_TRUE_MATCH,
            decidedBy: $this->adminUser->id,
            reason: 'ยืนยันว่าเป็นบุคคลเดียวกัน ระงับธุรกรรมและแจ้ง ปปง. แล้ว',
        );

        $this->assertSame(0, SanctionFpClearance::count());
    }

    public function test_decide_rejects_short_reason(): void
    {
        $this->makeEntry('AMRAN MING');

        $screening = $this->service()->screen(
            input: new ScreeningInput(name: 'AMRAN MING', nationality: 'TH'),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
        );

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->decide(
            screening: $screening,
            decision: SanctionScreening::DECISION_FALSE_POSITIVE,
            decidedBy: $this->adminUser->id,
            reason: 'ok',
        );
    }

    public function test_screening_records_the_sync_run_it_was_checked_against(): void
    {
        $run = \App\Models\SanctionSyncRun::create([
            'list_code' => SanctionEntry::LIST_FREEZE_05_TH,
            'source_adapter' => 'amlo_public_scraper',
            'status' => \App\Models\SanctionSyncRun::STATUS_SUCCESS,
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(4),
            'source_as_of' => '2026-10-01',
        ]);

        $screening = $this->service()->screen(
            input: new ScreeningInput(name: 'SOMCHAI JAIDEE'),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
        );

        $this->assertSame($run->id, $screening->sync_run_id);
    }
}
