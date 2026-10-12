<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Setting;
use App\Support\WorkingDayClock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * รายงานที่รวมหลายสาขาซึ่งตัดรอบไม่ตรงกัน
 *
 * ไม่มีช่วงเวลาเดียวที่ถูกต้องสำหรับทุกแถว ถ้าใช้ช่วงของสาขาหนึ่งไปครอบอีก
 * สาขา สาขาที่ตัดรอบเร็วกว่าจะถูกนับรายการของวันถัดไปเข้ามาด้วย
 */
class CrossBranchWorkingDayReportTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Counter $lateCounter;

    private Counter $calendarCounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        Setting::updateOrCreate(['setting_key' => 'WORKING_CUT_OFF'], ['setting_value' => '03:00:00']);

        // สาขาเดิมใช้ค่ากลาง ตี 3
        $this->lateCounter = $this->counter;

        $calendar = Branch::create([
            'branch_code' => 'CITY',
            'branch_name' => 'สาขาในเมือง',
            'type' => 'branch',
            'is_active' => true,
            'cut_off_time' => '00:00:00',
        ]);

        $this->calendarCounter = Counter::create([
            'branch_id' => $calendar->id,
            'counter_name' => 'เคาน์เตอร์เมือง',
            'counter_code' => 'CITY-C1',
            'is_active' => true,
        ]);
    }

    private function bill(Counter $counter, string $at, string $no): void
    {
        DB::table('transactions_master')->insert([
            'trns_no' => $no,
            'trns_type' => 'BUYING',
            'counter_id' => $counter->id,
            'flag_cancel' => 'N',
            'trns_datetime' => $at,
            'created_by' => $this->staffUser->id,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /** @return array<string> เลขที่บิลที่คิวรี่มองเห็น */
    private function visibleOn(string $date, array $counterIds = []): array
    {
        $q = DB::table('transactions_master')->where('flag_cancel', 'N');

        app(WorkingDayClock::class)->constrainToWorkingDay(
            $q, 'trns_datetime', 'counter_id', $date, $counterIds
        );

        $seen = $q->pluck('trns_no')->all();
        sort($seen);

        return $seen;
    }

    public function test_each_branch_contributes_its_own_working_day(): void
    {
        // ตีหนึ่งของวันที่ 12 — สาขาตี 3 ยังเป็นวันที่ 11 สาขาปฏิทินเป็นวันที่ 12 แล้ว
        $this->bill($this->lateCounter, '2026-10-12 01:00:00', 'LATE-ตีหนึ่ง');
        $this->bill($this->calendarCounter, '2026-10-12 01:00:00', 'CAL-ตีหนึ่ง');

        // บ่ายวันที่ 11 — ทั้งสองสาขาเป็นวันที่ 11
        $this->bill($this->lateCounter, '2026-10-11 14:00:00', 'LATE-บ่าย');
        $this->bill($this->calendarCounter, '2026-10-11 14:00:00', 'CAL-บ่าย');

        $this->assertSame(
            ['CAL-บ่าย', 'LATE-ตีหนึ่ง', 'LATE-บ่าย'],
            $this->visibleOn('2026-10-11'),
        );

        $this->assertSame(['CAL-ตีหนึ่ง'], $this->visibleOn('2026-10-12'));
    }

    public function test_a_single_shared_cut_off_produces_the_same_result_as_before(): void
    {
        // ทุกสาขาใช้ค่าเดียวกัน — ผลต้องเหมือนสูตรเดิมเป๊ะ ไม่มีอะไรเปลี่ยน
        Branch::query()->update(['cut_off_time' => null]);

        $this->bill($this->lateCounter, '2026-10-12 01:00:00', 'A');
        $this->bill($this->calendarCounter, '2026-10-12 01:00:00', 'B');
        $this->bill($this->lateCounter, '2026-10-12 04:00:00', 'C');

        $this->assertSame(['A', 'B'], $this->visibleOn('2026-10-11'));
        $this->assertSame(['C'], $this->visibleOn('2026-10-12'));
    }

    public function test_limiting_to_one_counter_still_uses_that_branch_own_cut_off(): void
    {
        $this->bill($this->lateCounter, '2026-10-12 01:00:00', 'LATE');
        $this->bill($this->calendarCounter, '2026-10-12 01:00:00', 'CAL');

        $this->assertSame(['LATE'], $this->visibleOn('2026-10-11', [$this->lateCounter->id]));
        $this->assertSame([], $this->visibleOn('2026-10-11', [$this->calendarCounter->id]));
        $this->assertSame(['CAL'], $this->visibleOn('2026-10-12', [$this->calendarCounter->id]));
    }

    public function test_the_boundary_belongs_to_the_day_that_is_ending(): void
    {
        // ตี 3 พอดีเป็นขอบขวาซึ่งรวมอยู่ในวันที่กำลังจบ ไม่ใช่วันใหม่
        $this->bill($this->lateCounter, '2026-10-12 03:00:00', 'ขอบ');
        $this->bill($this->lateCounter, '2026-10-12 03:00:01', 'หลังขอบ');

        $this->assertSame(['ขอบ'], $this->visibleOn('2026-10-11', [$this->lateCounter->id]));
        $this->assertSame(['หลังขอบ'], $this->visibleOn('2026-10-12', [$this->lateCounter->id]));
    }

    public function test_a_range_across_branches_covers_each_branch_own_edges(): void
    {
        $this->bill($this->lateCounter, '2026-10-13 02:00:00', 'LATE-ท้ายช่วง');
        $this->bill($this->calendarCounter, '2026-10-13 02:00:00', 'CAL-เลยช่วง');

        $q = DB::table('transactions_master')->where('flag_cancel', 'N');

        app(WorkingDayClock::class)->constrainToWorkingDayRange(
            $q, 'trns_datetime', 'counter_id', '2026-10-11', '2026-10-12'
        );

        // สาขาตี 3: ช่วงจบ 13 เวลา 03:00 จึงนับบิลตีสองของวันที่ 13
        // สาขาปฏิทิน: ช่วงจบ 13 เวลา 00:00 จึงไม่นับ
        $this->assertSame(['LATE-ท้ายช่วง'], $q->pluck('trns_no')->all());
    }
}
