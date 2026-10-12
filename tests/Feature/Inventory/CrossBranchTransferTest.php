<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterStock;
use App\Models\Setting;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * โอนของข้ามสาขาที่ตัดรอบไม่ตรงกัน
 *
 * โอนตอนตีหนึ่ง สาขาที่ตัดตี 3 ยังอยู่ในวันทำการของเมื่อวาน สาขาที่ใช้วัน
 * ปฏิทินข้ามวันไปแล้ว ขาส่งกับขารับจึงตกคนละวันทำการ — และนั่นถูกต้อง
 * สมุดของแต่ละสาขาต้องบันทึกตามรอบของตัวเอง ไม่ใช่ตามรอบของคู่ค้า
 */
class CrossBranchTransferTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Counter $calendarCounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        Setting::updateOrCreate(['setting_key' => 'WORKING_CUT_OFF'], ['setting_value' => '03:00:00']);

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

        CounterStock::updateOrCreate(
            [
                'counter_id' => $this->counter->id,
                'currency_code' => 'USD',
                'denomination_id' => $this->denomination->id,
            ],
            ['quantity' => 1000, 'avg_cost' => 35, 'total_cost_value' => 35000],
        );
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array{out: float, in: float} ยอดโอนออก/เข้าที่หน้าสรุปของเคาน์เตอร์หนึ่งในวันทำการหนึ่ง */
    private function movementsFor(Counter $counter, string $date): array
    {
        [$start, $end] = app(\App\Support\WorkingDayClock::class)
            ->windowForCounter($counter->id, $date);

        $row = \App\Models\StockMovement::where('counter_id', $counter->id)
            ->whereNotNull('denomination_id')
            ->where('moved_at', '>', $start)
            ->where('moved_at', '<=', $end)
            ->selectRaw('
                SUM(CASE WHEN movement_type = "transfer_out" THEN ABS(amount) ELSE 0 END) as out_total,
                SUM(CASE WHEN movement_type = "transfer_in" THEN amount ELSE 0 END) as in_total
            ')
            ->first();

        return ['out' => (float) ($row->out_total ?? 0), 'in' => (float) ($row->in_total ?? 0)];
    }

    public function test_each_side_of_a_transfer_lands_in_its_own_working_day(): void
    {
        Carbon::setTestNow('2026-10-12 01:00:00');

        app(InventoryService::class)->transferStock(
            'borrow',
            $this->counter->id,
            $this->calendarCounter->id,
            'USD',
            $this->denomination->id,
            200,
            $this->adminUser->id,
        );

        // ขาส่งอยู่สาขาที่ตัดตี 3 — ตีหนึ่งยังเป็นวันทำการของวันที่ 11
        $this->assertEquals(200.0, $this->movementsFor($this->counter, '2026-10-11')['out']);
        $this->assertEquals(0.0, $this->movementsFor($this->counter, '2026-10-12')['out']);

        // ขารับอยู่สาขาที่ใช้วันปฏิทิน — ตีหนึ่งเป็นวันทำการของวันที่ 12 แล้ว
        $this->assertEquals(200.0, $this->movementsFor($this->calendarCounter, '2026-10-12')['in']);
        $this->assertEquals(0.0, $this->movementsFor($this->calendarCounter, '2026-10-11')['in']);
    }

    public function test_the_stock_itself_moves_immediately_regardless_of_the_books(): void
    {
        Carbon::setTestNow('2026-10-12 01:00:00');

        app(InventoryService::class)->transferStock(
            'borrow',
            $this->counter->id,
            $this->calendarCounter->id,
            'USD',
            $this->denomination->id,
            200,
            $this->adminUser->id,
        );

        // รอบตัดวันเป็นเรื่องของการนับยอด ไม่ใช่ของตัวเงิน ของต้องถึงปลายทางทันที
        $this->assertEquals(800.0, (float) CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->value('quantity'));

        $this->assertEquals(200.0, (float) CounterStock::where('counter_id', $this->calendarCounter->id)
            ->where('denomination_id', $this->denomination->id)->value('quantity'));
    }

    public function test_a_transfer_in_normal_hours_lands_in_the_same_day_on_both_sides(): void
    {
        Carbon::setTestNow('2026-10-11 14:00:00');

        app(InventoryService::class)->transferStock(
            'borrow',
            $this->counter->id,
            $this->calendarCounter->id,
            'USD',
            $this->denomination->id,
            100,
            $this->adminUser->id,
        );

        // เวลาปกติไม่มีอะไรต่าง ทั้งคู่อยู่วันทำการที่ 11
        $this->assertEquals(100.0, $this->movementsFor($this->counter, '2026-10-11')['out']);
        $this->assertEquals(100.0, $this->movementsFor($this->calendarCounter, '2026-10-11')['in']);
    }
}
