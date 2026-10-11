<?php

namespace Tests\Feature\Inventory;

use App\Models\Setting;
use App\Support\WorkingDayClock;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * วันทำการไม่ใช่วันตามปฏิทิน ร้านปิดตี 3 แปลว่าวันที่ 11 กินไปถึงตี 3 ของวันที่ 12
 *
 * เดิมทุกจุดใช้ now()->format('Y-m-d') ซึ่งเปลี่ยนตอนเที่ยงคืน ไม่ใช่ตอนตัดรอบ
 * ช่วงเวลาที่คำนวณได้ระหว่างเที่ยงคืนถึงตี 3 จึงเริ่มในอนาคต และรายการที่
 * กำลังเกิดอยู่ตกหล่นออกจากทุกรายงาน
 */
class WorkingDayClockTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        Setting::updateOrCreate(['setting_key' => 'WORKING_CUT_OFF'], ['setting_value' => '03:00:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function moments(): array
    {
        return [
            'บ่ายวันที่ 11'            => ['2026-10-11 14:00:00', '2026-10-11'],
            'สามทุ่มวันที่ 11'         => ['2026-10-11 21:00:00', '2026-10-11'],
            'เกือบเที่ยงคืน'           => ['2026-10-11 23:59:59', '2026-10-11'],
            'เที่ยงคืนหนึ่งนาที'        => ['2026-10-12 00:01:00', '2026-10-11'],
            'ตีสองห้าสิบเก้า'          => ['2026-10-12 02:59:59', '2026-10-11'],
            'ตีสามพอดี'                => ['2026-10-12 03:00:00', '2026-10-11'],
            'ตีสามหนึ่งวินาที'          => ['2026-10-12 03:00:01', '2026-10-12'],
            'เช้าวันที่ 12'            => ['2026-10-12 09:00:00', '2026-10-12'],
        ];
    }

    #[DataProvider('moments')]
    public function test_the_working_day_turns_over_at_the_cut_off_not_at_midnight(string $at, string $expected): void
    {
        Carbon::setTestNow($at);

        $this->assertSame($expected, app(WorkingDayClock::class)->currentDate());
    }

    public function test_a_transaction_made_just_after_midnight_falls_inside_the_current_working_day(): void
    {
        Carbon::setTestNow('2026-10-12 00:01:00');

        $clock = app(WorkingDayClock::class);
        [$start, $end] = $clock->window($clock->currentDate());

        /*
         * นี่คือบั๊กเดิม: เวลา 00:01 ระบบคิดว่าวันนี้คือวันที่ 12 แล้วได้ช่วง
         * 12 เวลา 03:00 ถึง 13 เวลา 03:00 ซึ่งเริ่มในอีกสามชั่วโมงข้างหน้า
         * รายการที่พนักงานเพิ่งทำจึงไม่อยู่ในรายงานใด ๆ
         */
        $this->assertSame('2026-10-11 03:00:00', $start);
        $this->assertSame('2026-10-12 03:00:00', $end);

        $this->assertTrue(
            Carbon::parse($start)->lt(now()) && now()->lte(Carbon::parse($end)),
            'รายการที่เกิดตอนนี้ต้องอยู่ในช่วงของวันทำการปัจจุบัน',
        );
    }

    public function test_the_window_matches_the_comparison_the_queries_use(): void
    {
        $clock = app(WorkingDayClock::class);
        [$start, $end] = $clock->window('2026-10-11');

        // คิวรี่ทั่วระบบใช้ trns_datetime > start AND <= end
        // ขอบจึงเป็น เปิดซ้าย-ปิดขวา และต้องไม่เปลี่ยน ไม่งั้นตัวเลขรายงานขยับ
        $this->assertFalse(Carbon::parse($start)->gt(Carbon::parse($start)));
        $this->assertSame('2026-10-11 03:00:00', $start);
        $this->assertSame('2026-10-12 03:00:00', $end);
    }

    public function test_a_cut_off_of_midnight_makes_working_days_match_the_calendar(): void
    {
        Setting::updateOrCreate(['setting_key' => 'WORKING_CUT_OFF'], ['setting_value' => '00:00:00']);
        Carbon::setTestNow('2026-10-12 00:01:00');

        // สาขาที่ไม่ต้องการตัดรอบกลางดึกต้องได้พฤติกรรมตรงไปตรงมา
        $this->assertSame('2026-10-12', app(WorkingDayClock::class)->currentDate());
    }

    public function test_the_date_for_an_arbitrary_moment_can_be_asked_for(): void
    {
        // รายการเก่าต้องรู้ได้ว่าเป็นของวันทำการไหน โดยไม่ต้องขยับนาฬิกา
        $clock = app(WorkingDayClock::class);

        $this->assertSame('2026-10-11', $clock->dateAt(Carbon::parse('2026-10-12 02:00:00')));
        $this->assertSame('2026-10-12', $clock->dateAt(Carbon::parse('2026-10-12 04:00:00')));
    }
    public function test_a_multi_day_range_starts_at_the_first_cut_off_and_ends_at_the_last(): void
    {
        // รายงานที่รับช่วงวันที่ เช่น กำไรขาดทุน ต้องคลุมตั้งแต่เปิดวันแรก
        // จนปิดวันสุดท้าย ไม่ใช่เที่ยงคืนถึงเที่ยงคืน
        [$start, $end] = app(WorkingDayClock::class)->range("2026-10-01", "2026-10-31");

        $this->assertSame("2026-10-01 03:00:00", $start);
        $this->assertSame("2026-11-01 03:00:00", $end);
    }

    public function test_a_single_day_range_matches_the_window_for_that_day(): void
    {
        $clock = app(WorkingDayClock::class);

        $this->assertSame($clock->window("2026-10-11"), $clock->range("2026-10-11", "2026-10-11"));
    }
}