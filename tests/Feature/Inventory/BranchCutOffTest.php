<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Setting;
use App\Models\WorkingDay;
use App\Support\WorkingDayClock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * เวลาตัดรอบแยกตามสาขา
 *
 * สาขาสนามบินตัดรอบตี 3 สาขาในเมืองไม่ตัดรอบกลางดึก (เที่ยงคืน) เวลา
 * เดียวกันทั้งสองสาขาจึงอยู่คนละวันทำการได้ และนั่นถูกต้อง
 *
 * เวลาตัดรอบต้องอยู่ช่วงเช้ามืด เพราะสูตรนิยามว่าวันทำการ D เริ่มที่เวลา
 * ตัดรอบของวันที่ D และจบที่เวลาเดียวกันของวันถัดไป ถ้าตั้งเป็นสี่ทุ่ม
 * ความหมายจะกลับหัว — รายการตอนบ่ายของวันที่ 11 จะไปอยู่วันทำการที่ 10
 */
class BranchCutOffTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Branch $calendarBranch;

    private Counter $calendarCounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        Setting::updateOrCreate(['setting_key' => 'WORKING_CUT_OFF'], ['setting_value' => '03:00:00']);

        // สาขาที่ไม่ต้องการตัดรอบกลางดึก — วันทำการตรงกับวันตามปฏิทิน
        $this->calendarBranch = Branch::create([
            'branch_code' => 'CITY',
            'branch_name' => 'สาขาในเมือง',
            'type' => 'branch',
            'is_active' => true,
            'cut_off_time' => '00:00:00',
        ]);

        $this->calendarCounter = Counter::create([
            'branch_id' => $this->calendarBranch->id,
            'counter_name' => 'เคาน์เตอร์เมือง',
            'counter_code' => 'CITY-C1',
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function clock(): WorkingDayClock
    {
        return app(WorkingDayClock::class);
    }

    public function test_a_branch_without_its_own_cut_off_keeps_using_the_central_value(): void
    {
        // สาขาที่ยังไม่ได้ตั้งค่าต้องทำงานเหมือนเดิมเป๊ะ การเพิ่มฟีเจอร์นี้
        // ไม่ควรเปลี่ยนพฤติกรรมของสาขาใดจนกว่าจะมีคนไปตั้งค่า
        $this->assertNull($this->branch->cut_off_time);
        $this->assertSame('03:00:00', $this->clock()->cutoff($this->branch->id));
    }

    public function test_a_branch_with_its_own_cut_off_ignores_the_central_value(): void
    {
        $this->assertSame('00:00:00', $this->clock()->cutoff($this->calendarBranch->id));
    }

    public function test_two_branches_can_be_in_different_working_days_at_the_same_instant(): void
    {
        Carbon::setTestNow('2026-10-12 00:30:00');

        $clock = $this->clock();

        // สาขาตัดตี 3 ยังอยู่ในวันทำการของวันที่ 11
        $this->assertSame('2026-10-11', $clock->currentDate($this->branch->id));

        // สาขาที่ไม่ตัดรอบข้ามไปวันที่ 12 แล้วตอนเที่ยงคืน
        $this->assertSame('2026-10-12', $clock->currentDate($this->calendarBranch->id));
    }

    public function test_the_window_of_each_branch_follows_its_own_cut_off(): void
    {
        $clock = $this->clock();

        $this->assertSame(
            ['2026-10-11 03:00:00', '2026-10-12 03:00:00'],
            $clock->window('2026-10-11', $this->branch->id),
        );

        $this->assertSame(
            ['2026-10-11 00:00:00', '2026-10-12 00:00:00'],
            $clock->window('2026-10-11', $this->calendarBranch->id),
        );
    }

    public function test_asking_without_a_branch_still_gives_the_central_value(): void
    {
        // จุดที่ไม่รู้ว่าเป็นสาขาไหน เช่น ค่าเริ่มต้นของช่องวันที่ ต้องยังทำงานได้
        $this->assertSame('03:00:00', $this->clock()->cutoff());
        $this->assertSame(
            ['2026-10-11 03:00:00', '2026-10-12 03:00:00'],
            $this->clock()->window('2026-10-11'),
        );
    }

    public function test_a_counter_resolves_to_the_cut_off_of_the_branch_it_sits_in(): void
    {
        $clock = $this->clock();

        $this->assertSame($this->branch->id, $clock->branchOfCounter($this->counter->id));
        $this->assertSame($this->calendarBranch->id, $clock->branchOfCounter($this->calendarCounter->id));
    }

    public function test_changing_a_branch_cut_off_does_not_move_a_day_that_already_closed(): void
    {
        // วันที่ 10 ถูกเปิดและปิดไปตอนที่สาขายังตัดรอบตี 3
        WorkingDay::create([
            'counter_id' => $this->calendarCounter->id,
            'work_date' => '2026-10-10',
            'cut_off_time' => '03:00:00',
            'opening_thb_cash' => 0,
            'status' => 'closed',
            'opened_by' => $this->adminUser->id,
            'opened_at' => Carbon::parse('2026-10-10 08:00:00'),
        ]);

        // วันนี้สาขาเปลี่ยนมาไม่ตัดรอบ ยอดของวันที่ 10 ต้องไม่ขยับ
        // ไม่งั้นงบที่ปิดไปแล้วเปลี่ยนตัวเลขย้อนหลังเพราะการตั้งค่าวันนี้
        $this->assertSame(
            ['2026-10-10 03:00:00', '2026-10-11 03:00:00'],
            $this->clock()->window('2026-10-10', $this->calendarBranch->id),
        );
    }

    public function test_a_day_that_is_still_open_keeps_the_cut_off_it_was_opened_with(): void
    {
        WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => '2026-10-11',
            'cut_off_time' => '03:00:00',
            'opening_thb_cash' => 0,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => Carbon::parse('2026-10-11 08:00:00'),
        ]);

        // ผู้จัดการเลื่อนเวลาตัดรอบไปตี 5 ตอนตีสี่ ขณะที่กะยังไม่ปิด
        $this->branch->update(['cut_off_time' => '05:00:00']);
        Carbon::setTestNow('2026-10-12 04:00:00');

        // กะที่เปิดอยู่ต้องยังเป็นวันทำการปัจจุบัน — ค่าใหม่มีผลกับวันถัดไป
        // ถ้าไม่ยึดค่าที่ตราไว้ ช่วงเวลาจะจบไปแล้วตอนตี 3 และพนักงานที่ยัง
        // นั่งอยู่จะทำรายการไม่ได้ทั้งที่ยังไม่ได้ปิดกะ
        $this->assertSame('2026-10-11', $this->clock()->currentDate($this->branch->id));
    }

    public function test_the_new_cut_off_takes_effect_once_no_day_is_open(): void
    {
        $this->branch->update(['cut_off_time' => '05:00:00']);
        Carbon::setTestNow('2026-10-12 04:00:00');

        // ไม่มีกะเปิดอยู่ ค่าใหม่มีผลทันที — ตีสี่ยังไม่ถึงตี 5 จึงยังเป็นวันที่ 11
        $this->assertSame('2026-10-11', $this->clock()->currentDate($this->branch->id));

        Carbon::setTestNow('2026-10-12 05:30:00');
        $this->assertSame('2026-10-12', $this->clock()->currentDate($this->branch->id));
    }

    public function test_a_stale_open_day_does_not_freeze_the_clock_forever(): void
    {
        // กะที่ลืมปิดไว้หลายวันมีอยู่จริงในระบบ มันต้องไม่ทำให้วันทำการ
        // ปัจจุบันค้างอยู่ที่วันนั้น
        WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => '2026-10-04',
            'cut_off_time' => '03:00:00',
            'opening_thb_cash' => 0,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => Carbon::parse('2026-10-04 08:00:00'),
        ]);

        Carbon::setTestNow('2026-10-12 14:00:00');

        $this->assertSame('2026-10-12', $this->clock()->currentDate($this->branch->id));
    }
    public function test_opening_a_day_stamps_the_cut_off_that_was_in_effect(): void
    {
        Carbon::setTestNow("2026-10-11 09:00:00");
        session(["working_counter_id" => $this->calendarCounter->id]);

        \Livewire\Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set("counterId", $this->calendarCounter->id)
            ->set("date", "2026-10-11")
            ->call("openDay")
            ->assertHasNoErrors();

        $row = WorkingDay::where("counter_id", $this->calendarCounter->id)->firstOrFail();

        // ค่าที่ตราไว้คือค่าของสาขาตอนนั้น ไม่ใช่ค่ากลาง
        $this->assertSame("00:00:00", substr((string) $row->cut_off_time, 0, 8));

        // เปลี่ยนตั้งค่าวันหลังแล้วยอดของวันนี้ต้องไม่ขยับ
        $this->calendarBranch->update(["cut_off_time" => "04:00:00"]);

        $this->assertSame(
            ["2026-10-11 00:00:00", "2026-10-12 00:00:00"],
            $this->clock()->window("2026-10-11", $this->calendarBranch->id),
        );
    }

    public function test_days_that_existed_before_the_feature_keep_the_central_value(): void
    {
        // migration เติมค่ากลางให้แถวเก่าทุกแถว เพราะตอนนั้นยังไม่มีสาขาใด
        // ตั้งค่าเอง ค่ากลางจึงเป็นความจริงของวันเหล่านั้นแน่นอน
        $stamps = \Illuminate\Support\Facades\DB::table("working_days")
            ->whereNull("cut_off_time")->count();

        WorkingDay::create([
            "counter_id" => $this->counter->id,
            "work_date" => "2026-09-01",
            "opening_thb_cash" => 0,
            "status" => "closed",
            "opened_by" => $this->adminUser->id,
            "opened_at" => Carbon::parse("2026-09-01 08:00:00"),
        ]);

        $this->assertSame($stamps, 0, "ของเดิมต้องถูกเติมครบ");

        // แถวที่ไม่มีค่าตราไว้ ถอยไปใช้ค่าของสาขา แล้วค่อยถอยไปค่ากลาง
        $this->assertSame(
            ["2026-09-01 03:00:00", "2026-09-02 03:00:00"],
            $this->clock()->window("2026-09-01", $this->branch->id),
        );
    }
}