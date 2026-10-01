<?php

namespace Tests\Feature\Sanction;

use App\Models\CounterRate;
use App\Models\SanctionEntry;
use App\Models\SanctionEntryIdentifier;
use App\Models\SanctionEntryName;
use App\Models\SanctionScreening;
use App\Models\WorkingDay;
use App\Services\Sanction\NameNormalizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ด่านตรวจรายชื่อหน้าเคาน์เตอร์ฝั่งรับซื้อ
 *
 * สิ่งที่เทสชุดนี้ยืนยัน:
 *  - gate อยู่ "ก่อน" DB::transaction() จริง (เช็กว่าทุกตารางว่าง ไม่ใช่แค่ master)
 *  - การบังคับใช้อยู่ฝั่ง server ไม่เชื่อ property ฝั่งหน้าจอ
 *  - supervisor override: พนักงานยัง login อยู่ ผู้จัดการใส่รหัสของตัวเอง
 */
class BuyFormScreeningTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        session(['working_counter_id' => $this->counter->id]);

        // ไม่มี rate = addRow() ไม่ยอมเพิ่มแถว แล้ว saveTransaction() จะ return
        // ก่อนถึง gate ทำให้เทสผ่านโดยที่ไม่ได้ทดสอบอะไรเลย
        CounterRate::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'rate_date' => now()->toDateString(),
            'rate_buy' => 33.00,
            'rate_sell' => 38.00,
        ]);

        WorkingDay::insert([
            'counter_id' => $this->counter->id,
            'work_date' => now()->format('Y-m-d'),
            'opening_thb_cash' => 0,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function sanctionedEntry(string $nameEn, ?string $nationalId = null, ?string $passport = null): SanctionEntry
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

        foreach ([['national_id', $nationalId], ['passport', $passport]] as [$type, $value]) {
            if ($value !== null) {
                SanctionEntryIdentifier::create([
                    'sanction_entry_id' => $entry->id,
                    'type' => $type,
                    'value_raw' => $value,
                    'value_normalized' => $value,
                ]);
            }
        }

        return $entry;
    }

    /** กรอกฟอร์มซื้อให้พร้อมบันทึก */
    private function form(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 100)
            ->call('addRow');
    }

    public function test_exact_national_id_blocks_and_writes_nothing(): void
    {
        $this->sanctionedEntry('AMRAN MING', nationalId: '5960500028101');

        $this->form()
            ->set('ocrPassportNo', '5960500028101')
            ->set('custName', 'AMRAN MING')
            ->set('ocrNationality', 'TH')
            ->call('saveTransaction');

        // gate อยู่นอก DB::transaction() -> ต้องไม่มีอะไรเกิดขึ้นเลยสักตาราง
        // เช็กทีละตาราง ไม่ใช่แค่ transactions_master เพราะถ้าวันหน้ามีคนย้าย gate
        // เข้าไปข้างในแล้วพึ่ง rollback เทสที่เช็กตารางเดียวจะยังผ่านแต่มีขยะค้าง
        $this->assertDatabaseCount('transactions_master', 0);
        $this->assertDatabaseCount('transactions_detail', 0);
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('customers', 0);

        $screening = SanctionScreening::first();
        $this->assertNotNull($screening);
        $this->assertSame(SanctionScreening::RESULT_CONFIRMED_MATCH, $screening->result);
    }

    public function test_potential_match_without_approval_does_not_save(): void
    {
        $this->sanctionedEntry('AMRAN MING');

        $this->form()
            ->set('ocrPassportNo', 'AA123456')
            ->set('custName', 'AMRAN MING')
            ->set('ocrNationality', 'TH')
            ->call('saveTransaction');

        $this->assertDatabaseCount('transactions_master', 0);

        $this->assertSame(
            SanctionScreening::RESULT_POTENTIAL_MATCH,
            SanctionScreening::first()->result
        );
    }

    public function test_manager_approval_allows_the_transaction(): void
    {
        $this->sanctionedEntry('AMRAN MING');

        $manager = \App\Models\User::create([
            'name' => 'Branch Manager',
            'email' => 'bm@test.local',
            'password' => bcrypt('Manager@1234'),
            'role_id' => \App\Models\Role::where('name', 'branch_manager')->first()->id,
            'branch_id' => $this->branch->id,
        ]);

        $component = $this->form()
            ->set('ocrPassportNo', 'AA123456')
            ->set('custName', 'AMRAN MING')
            ->set('ocrNationality', 'TH')
            ->call('saveTransaction')
            ->set('approverEmail', 'bm@test.local')
            ->set('approverPassword', 'Manager@1234')
            ->set('approvalReason', 'ตรวจพาสปอร์ตเล่มจริงแล้ว วันเกิดต่างกัน 12 ปี คนละคน')
            ->call('submitSanctionApproval');

        $component->call('saveTransaction');

        $this->assertDatabaseCount('transactions_master', 1);

        $screening = SanctionScreening::whereNotNull('decision')->first();
        $this->assertNotNull($screening);
        $this->assertSame(SanctionScreening::DECISION_FALSE_POSITIVE, $screening->decision);
        $this->assertSame($manager->id, $screening->decided_by);
        $this->assertSame(
            $this->staffUser->id,
            $screening->screened_by,
            'screened_by ต้องเป็นพนักงาน ส่วน decided_by เป็นผู้จัดการ'
        );
    }

    public function test_staff_cannot_approve_even_with_correct_password(): void
    {
        $this->sanctionedEntry('AMRAN MING');

        $this->form()
            ->set('ocrPassportNo', 'AA123456')
            ->set('custName', 'AMRAN MING')
            ->set('ocrNationality', 'TH')
            ->call('saveTransaction')
            ->set('approverEmail', $this->staffUser->email)
            ->set('approverPassword', 'password')
            ->set('approvalReason', 'ขอผ่านหน่อย ลูกค้ารออยู่ตรงหน้าแล้วจริงๆ')
            ->call('submitSanctionApproval')
            ->assertHasErrors('approverEmail');

        $this->assertDatabaseCount('transactions_master', 0);
    }

    public function test_short_reason_is_rejected(): void
    {
        $this->sanctionedEntry('AMRAN MING');

        \App\Models\User::create([
            'name' => 'Branch Manager',
            'email' => 'bm@test.local',
            'password' => bcrypt('Manager@1234'),
            'role_id' => \App\Models\Role::where('name', 'branch_manager')->first()->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->form()
            ->set('ocrPassportNo', 'AA123456')
            ->set('custName', 'AMRAN MING')
            ->set('ocrNationality', 'TH')
            ->call('saveTransaction')
            ->set('approverEmail', 'bm@test.local')
            ->set('approverPassword', 'Manager@1234')
            ->set('approvalReason', 'ok')
            ->call('submitSanctionApproval')
            ->assertHasErrors('approvalReason');

        $this->assertDatabaseCount('transactions_master', 0);
    }

    public function test_clean_customer_saves_and_records_a_clear_screening(): void
    {
        $this->form()
            ->set('ocrPassportNo', 'AA123456')
            ->set('custName', 'SOMCHAI JAIDEE')
            ->set('ocrNationality', 'TH')
            ->call('saveTransaction');

        $this->assertDatabaseCount('transactions_master', 1);

        $screening = SanctionScreening::first();
        $this->assertSame(SanctionScreening::RESULT_CLEAR, $screening->result);
        $this->assertNotNull($screening->transaction_id, 'screening ต้องผูกกับธุรกรรมที่บันทึกสำเร็จ');
    }

    public function test_transaction_without_any_customer_data_is_recorded_as_unscreened(): void
    {
        $this->form()->call('saveTransaction');

        $this->assertDatabaseCount('transactions_master', 1);

        $screening = SanctionScreening::first();
        $this->assertNotNull($screening, 'ต้องบันทึกไว้เพื่อเข้ารายงานช่องโหว่การตรวจ');
        $this->assertNull($screening->input_name);
        $this->assertNull($screening->customer_id);
    }
    /**
     * approvedScreeningId เป็น public property — เขียนได้จาก browser
     * ถ้า gate เชื่อค่านี้ ใครก็ปลดล็อกเองได้ จึงต้องตรวจซ้ำที่ฐานข้อมูล
     */
    public function test_forged_approval_property_does_not_unlock_the_gate(): void
    {
        $this->sanctionedEntry('AMRAN MING');

        $this->form()
            ->set('ocrPassportNo', 'AA123456')
            ->set('custName', 'AMRAN MING')
            ->set('ocrNationality', 'TH')
            ->set('approvedScreeningId', 999999)
            ->call('saveTransaction');

        $this->assertDatabaseCount('transactions_master', 0);
    }

    /**
     * ใบอนุมัติผูกกับคนที่ถูกตรวจ ไม่ใช่ผูกกับหน้าจอ — อนุมัติให้คนหนึ่งแล้ว
     * แก้ชื่อเป็นคนอื่นในลิสต์ ต้องขออนุมัติใหม่
     */
    public function test_approval_does_not_carry_over_to_a_different_person(): void
    {
        $this->sanctionedEntry('AMRAN MING');
        $this->sanctionedEntry('SAMAN HAJI');

        \App\Models\User::create([
            'name' => 'Branch Manager',
            'email' => 'bm@test.local',
            'password' => bcrypt('Manager@1234'),
            'role_id' => \App\Models\Role::where('name', 'branch_manager')->first()->id,
            'branch_id' => $this->branch->id,
        ]);

        $component = $this->form()
            ->set('ocrPassportNo', 'AA123456')
            ->set('custName', 'AMRAN MING')
            ->set('ocrNationality', 'TH')
            ->call('saveTransaction')
            ->set('approverEmail', 'bm@test.local')
            ->set('approverPassword', 'Manager@1234')
            ->set('approvalReason', 'ตรวจพาสปอร์ตเล่มจริงแล้ว วันเกิดต่างกัน 12 ปี คนละคน')
            ->call('submitSanctionApproval');

        // สลับเป็นคนอื่นในลิสต์ แล้วใช้ใบอนุมัติเดิม
        $component->set('custName', 'SAMAN HAJI')->call('saveTransaction');

        $this->assertDatabaseCount('transactions_master', 0);
        $component->assertSet('showSanctionApproval', true);
    }
}
