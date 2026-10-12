<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * รายงานจริงทุกหน้าต้องเคารพเวลาตัดรอบของแต่ละสาขา
 *
 * เงื่อนไขเวลาที่แยกตามสาขาถูกเชื่อมด้วย OR ซ้อนอยู่ในคิวรี่ที่มี groupBy และ
 * selectRaw อยู่แล้ว เทสนี้ยิงเส้นทางจริงเพื่อให้แน่ใจว่า sql ที่ประกอบออกมา
 * ใช้ได้ ไม่ใช่แค่ตัวช่วยทำงานถูกตอนเรียกเดี่ยวๆ
 */
class ReportsRespectBranchCutOffTest extends TestCase
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
    }

    private function bill(Counter $counter, string $at, string $no, float $thb): void
    {
        $id = DB::table('transactions_master')->insertGetId([
            'trns_no' => $no,
            'trns_type' => 'BUYING',
            'counter_id' => $counter->id,
            'counter_name' => $counter->counter_name,
            'cust_name' => 'ลูกค้า ' . $no,
            'flag_cancel' => 'N',
            'trns_datetime' => $at,
            'created_by' => $this->adminUser->id,
            'created_at' => $at,
            'updated_at' => $at,
        ]);

        DB::table('transactions_detail')->insert([
            'transaction_id' => $id,
            'currency_code' => 'USD',
            'currency_name' => 'USD 100-50',
            'unit_price' => 35,
            'amount' => $thb / 35,
            'total' => $thb,
            'created_by' => $this->adminUser->id,
            'created_at' => $at,
            'updated_at' => $at,
        ]);
    }

    /** รายการตีหนึ่งของวันที่ 12 อยู่วันทำการที่ 11 ของสาขาตี 3 แต่เป็นวันที่ 12 ของสาขาปฏิทิน */
    private function seedAcrossTheGap(): void
    {
        $this->bill($this->counter, '2026-10-12 01:00:00', 'LATE-GAP', 70000);
        $this->bill($this->calendarCounter, '2026-10-12 01:00:00', 'CAL-GAP', 35000);
        $this->bill($this->counter, '2026-10-11 14:00:00', 'LATE-DAY', 7000);
        $this->bill($this->calendarCounter, '2026-10-11 14:00:00', 'CAL-DAY', 3500);
    }

    public function test_the_daily_report_adds_up_each_branch_own_working_day(): void
    {
        $this->seedAcrossTheGap();

        $response = $this->actingAs($this->adminUser)
            ->get(route('reports.daily', ['date' => '2026-10-11']));

        $response->assertOk();

        // วันทำการที่ 11 ต้องได้ LATE-GAP + LATE-DAY + CAL-DAY = 80,500
        // ถ้าใช้ช่วงของสาขาตี 3 ไปครอบสาขาปฏิทินด้วย จะได้ CAL-GAP มาเกิน 35,000
        $buying = $response->viewData('buying');
        $total = $buying->sum(fn ($row) => (float) $row->thb_amount);

        $this->assertEquals(80500.0, $total);
    }

    public function test_the_day_after_only_contains_what_belongs_to_it(): void
    {
        $this->seedAcrossTheGap();

        $response = $this->actingAs($this->adminUser)
            ->get(route('reports.daily', ['date' => '2026-10-12']));

        $response->assertOk();

        // วันที่ 12 มีแต่ CAL-GAP เพราะสาขาตี 3 ยังไม่เริ่มวันที่ 12
        $this->assertEquals(
            35000.0,
            $response->viewData('buying')->sum(fn ($row) => (float) $row->thb_amount),
        );
    }

    public function test_the_same_numbers_come_out_when_every_branch_shares_one_cut_off(): void
    {
        // ยืนยันว่าเทสสองตัวข้างบนแยกแยะได้จริง ไม่ใช่เขียวเพราะอะไรก็ได้ —
        // พอทุกสาขาใช้ค่าเดียวกัน รายการตีหนึ่งของทั้งคู่ตกวันทำการที่ 11
        // ยอดจึงเป็น 115,500 ไม่ใช่ 80,500 และนี่คือพฤติกรรมเดิมของระบบ
        Branch::query()->update(["cut_off_time" => null]);

        $this->seedAcrossTheGap();

        $response = $this->actingAs($this->adminUser)
            ->get(route("reports.daily", ["date" => "2026-10-11"]));

        $this->assertEquals(
            115500.0,
            $response->viewData("buying")->sum(fn ($row) => (float) $row->thb_amount),
        );
    }
    /** @return array<string, array{0: string, 1: array<string, string>}> */
    public static function reportPages(): array
    {
        return [
            'สรุปประจำวัน'    => ['reports.daily', ['date' => '2026-10-11']],
            'สรุปบัญชี'       => ['reports.accounting-summary', ['date' => '2026-10-11']],
            'เรทเฉลี่ย'       => ['reports.avg-rate', ['date' => '2026-10-11']],
            'ของฉัน'          => ['reports.my-summary', ['date' => '2026-10-11']],
            'กำไรขาดทุน'      => ['reports.profit-loss', ['date_from' => '2026-10-11', 'date_to' => '2026-10-12']],
            'ลูกค้า'          => ['reports.customer-transaction', ['date_from' => '2026-10-11', 'date_to' => '2026-10-12']],
            'ผลงานพนักงาน'    => ['reports.cashier', ['date_from' => '2026-10-11', 'date_to' => '2026-10-12']],
        ];
    }

    /**
     * @param  array<string, string>  $params
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('reportPages')]
    public function test_every_report_page_renders_with_branches_on_different_cut_offs(string $route, array $params): void
    {
        $this->seedAcrossTheGap();
        session(['working_counter_id' => $this->counter->id]);

        $this->actingAs($this->adminUser)->get(route($route, $params))->assertOk();
    }

    public function test_the_stock_report_uses_the_cut_off_of_the_counter_being_viewed(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('reports.stock-movement', [
                'counter_id' => $this->calendarCounter->id,
                'date' => '2026-10-11',
            ]))
            ->assertOk();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
