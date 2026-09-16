<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\TransactionMaster;
use App\Models\WorkingDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * แก้สาขา/เคาน์เตอร์ทำงานหลัง login ได้ ในกรณีที่เลือกผิดตอนแรก
 *
 * เดิม sidebar เช็ค canSwitchBranch() (= isAdmin) เพื่อตัดสินใจว่าจะโชว์ dropdown
 * เคาน์เตอร์หรือไม่ ทำให้ staff และ branch_manager เห็นเป็นข้อความอ่านเฉยๆ และ
 * modal บน dashboard ก็ไม่โผล่อีกเพราะ cookie อยู่ 365 วัน — เลือกผิดแล้วจบเลย
 */
class CounterSwitchingTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Branch $otherBranch;
    private Counter $otherCounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $this->otherBranch = Branch::create([
            'branch_code' => 'HKT-01',
            'branch_name' => 'Phuket',
            'type' => 'branch',
            'city' => 'Phuket',
            'is_active' => true,
        ]);

        $this->otherCounter = Counter::create([
            'counter_code' => 'HKT-C1',
            'counter_name' => 'Counter Phuket',
            'branch_id' => $this->otherBranch->id,
            'is_active' => true,
        ]);
    }

    private function managerUser()
    {
        return \App\Models\User::create([
            'name' => 'Manager',
            'email' => 'manager@test.local',
            'password' => bcrypt('password'),
            'role_id' => \App\Models\Role::where('name', 'branch_manager')->value('id'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    // ─── ใครเปลี่ยนได้ ───────────────────────────────────────────────

    public function test_staff_and_manager_and_admin_can_switch_counter(): void
    {
        $this->assertTrue($this->staffUser->canSwitchCounter());
        $this->assertTrue($this->managerUser()->canSwitchCounter());
        $this->assertTrue($this->adminUser->canSwitchCounter());
    }

    public function test_trader_and_auditor_cannot_switch_counter(): void
    {
        foreach (['trader', 'auditor'] as $roleName) {
            $user = \App\Models\User::create([
                'name' => ucfirst($roleName),
                'email' => "{$roleName}@test.local",
                'password' => bcrypt('password'),
                'role_id' => \App\Models\Role::where('name', $roleName)->value('id'),
                'branch_id' => $this->branch->id,
                'is_active' => true,
            ]);

            $this->assertFalse($user->canSwitchCounter(), "{$roleName} ไม่ควรเปลี่ยนเคาน์เตอร์ได้");
        }
    }

    // ─── ขอบเขตตัวเลือก ─────────────────────────────────────────────

    public function test_manager_can_only_pick_counters_in_their_own_branch(): void
    {
        $ids = $this->managerUser()->selectableCounters()->pluck('id')->all();

        $this->assertContains($this->counter->id, $ids);
        $this->assertContains($this->counter2->id, $ids);
        $this->assertNotContains($this->otherCounter->id, $ids, 'สาขาอื่นต้องไม่หลุดเข้ามา');
    }

    public function test_admin_can_pick_any_counter(): void
    {
        $ids = $this->adminUser->selectableCounters()->pluck('id')->all();

        $this->assertContains($this->counter->id, $ids);
        $this->assertContains($this->otherCounter->id, $ids);
    }

    /**
     * staff ที่ย้ายสาขาแล้วต้องได้เคาน์เตอร์ของสาขาที่ทำงานอยู่ ไม่ใช่สาขาต้นสังกัด
     * — เป็นบั๊กเดิมของ modal บน dashboard ที่อ่าน branch_id จาก user record
     */
    public function test_staff_counter_options_follow_the_working_branch_not_the_home_branch(): void
    {
        $this->actingAs($this->staffUser);

        session(['working_branch_id' => $this->otherBranch->id]);
        $ids = $this->staffUser->selectableCounters()->pluck('id')->all();

        $this->assertSame([$this->otherCounter->id], $ids);
    }

    public function test_inactive_counters_are_never_offered(): void
    {
        $this->counter2->update(['is_active' => false]);

        $ids = $this->managerUser()->selectableCounters()->pluck('id')->all();

        $this->assertNotContains($this->counter2->id, $ids);
    }

    // ─── การเปลี่ยนจริงผ่าน route ────────────────────────────────────

    public function test_manager_can_switch_to_another_counter_in_their_branch(): void
    {
        $this->actingAs($this->managerUser())
            ->withSession(['working_counter_id' => $this->counter->id])
            ->post(route('switch-counter'), ['counter_id' => $this->counter2->id])
            ->assertRedirect();

        $this->assertSame($this->counter2->id, session('working_counter_id'));
        $this->assertSame($this->counter2->counter_name, session('working_counter_name'));
    }

    public function test_manager_cannot_switch_to_a_counter_outside_their_branch(): void
    {
        $this->actingAs($this->managerUser())
            ->post(route('switch-counter'), ['counter_id' => $this->otherCounter->id])
            ->assertNotFound();
    }

    public function test_staff_cannot_switch_to_a_counter_outside_the_working_branch(): void
    {
        $this->actingAs($this->staffUser)
            ->withSession(['working_branch_id' => $this->branch->id])
            ->post(route('switch-counter'), ['counter_id' => $this->otherCounter->id])
            ->assertNotFound();
    }

    // ─── modal เปิดใหม่ได้ ───────────────────────────────────────────

    public function test_dashboard_reopens_the_picker_when_asked(): void
    {
        $this->actingAsStaff()
            ->get(route('dashboard') . '?switch=1')
            ->assertStatus(200)
            ->assertSee('this.showModal = true;', false)
            ->assertSee('ยกเลิก');
    }

    public function test_dashboard_does_not_force_the_picker_without_the_flag(): void
    {
        $this->actingAsStaff()
            ->get(route('dashboard'))
            ->assertStatus(200)
            ->assertDontSee('ยกเลิก');
    }

    // ─── คำเตือนเมื่อมีงานค้าง ───────────────────────────────────────

    public function test_counter_reports_no_activity_when_the_day_is_untouched(): void
    {
        $activity = $this->counter->workingDayActivity();

        $this->assertFalse($activity['has_open_day']);
        $this->assertSame(0, $activity['transaction_count']);
    }

    public function test_counter_reports_an_open_working_day(): void
    {
        WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => now()->format('Y-m-d'),
            'opening_thb_cash' => 0,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
        ]);

        $this->assertTrue($this->counter->workingDayActivity()['has_open_day']);
    }

    public function test_counter_counts_todays_bills_and_ignores_cancelled_ones(): void
    {
        TransactionMaster::create([
            'trns_no' => 'B0001',
            'trns_type' => 'BUYING',
            'counter_id' => $this->counter->id,
            'counter_name' => $this->counter->counter_name,
            'convert_currency_to' => 'THB',
            'trns_datetime' => now(),
            'created_by' => $this->staffUser->id,
            'updated_by' => $this->staffUser->id,
        ]);

        TransactionMaster::create([
            'trns_no' => 'B0002',
            'trns_type' => 'BUYING',
            'counter_id' => $this->counter->id,
            'counter_name' => $this->counter->counter_name,
            'convert_currency_to' => 'THB',
            'trns_datetime' => now(),
            'flag_cancel' => 'Y',
            'created_by' => $this->staffUser->id,
            'updated_by' => $this->staffUser->id,
        ]);

        $this->assertSame(1, $this->counter->workingDayActivity()['transaction_count']);
    }

    public function test_sidebar_warns_before_switching_away_from_a_busy_counter(): void
    {
        WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => now()->format('Y-m-d'),
            'opening_thb_cash' => 0,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
        ]);

        $this->actingAsStaff()
            ->get(route('dashboard'))
            ->assertStatus(200)
            ->assertSee('เคาน์เตอร์นี้มีงานค้างอยู่')
            ->assertSee('วันทำการเปิดอยู่');
    }

    public function test_sidebar_shows_no_warning_for_an_idle_counter(): void
    {
        $this->actingAsStaff()
            ->get(route('dashboard'))
            ->assertStatus(200)
            ->assertDontSee('เคาน์เตอร์นี้มีงานค้างอยู่');
    }
}
