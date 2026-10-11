<?php

namespace Tests\Feature\Inventory;

use App\Models\Setting;
use App\Models\WorkingDay;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * สาขาที่ตั้งเวลาตัดรอบไว้ตี 3 ต้องขายของได้ตอนเที่ยงคืนหนึ่งนาที
 *
 * วันทำการที่พนักงานเปิดไว้คือของเมื่อวาน และยังไม่ปิด การข้ามเที่ยงคืน
 * ไม่ใช่เหตุการณ์ในสายตาของร้าน
 */
class TradingAfterMidnightTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        Setting::updateOrCreate(['setting_key' => 'WORKING_CUT_OFF'], ['setting_value' => '03:00:00']);
        session(['working_counter_id' => $this->counter->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function openWorkingDay(string $date): WorkingDay
    {
        return WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => $date,
            'opening_thb_cash' => 0,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => Carbon::parse("{$date} 08:00:00"),
        ]);
    }

    public function test_the_buy_form_stays_open_past_midnight_on_the_day_that_was_opened_yesterday(): void
    {
        $this->openWorkingDay('2026-10-11');

        Carbon::setTestNow('2026-10-12 00:01:00');

        // เดิมหน้านี้เด้งพนักงานออกไปหน้าเปิด/ปิดวัน พร้อมข้อความว่ายังไม่เปิด
        // วันทำการ ทั้งที่เพิ่งทำรายการสำเร็จเมื่อสองนาทีก่อน
        Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->assertSet('counterId', (string) $this->counter->id);

        $this->assertNull(session('error'), 'ไม่ควรมีคำเตือนว่ายังไม่เปิดวันทำการ');
    }

    public function test_the_sell_form_stays_open_past_midnight_too(): void
    {
        $this->openWorkingDay('2026-10-11');

        Carbon::setTestNow('2026-10-12 00:01:00');

        Livewire::actingAs($this->staffUser)
            ->test('transaction.sell-form')
            ->assertSet('counterId', (string) $this->counter->id);

        $this->assertNull(session('error'));
    }

    public function test_the_form_closes_once_the_cut_off_has_passed(): void
    {
        $this->openWorkingDay('2026-10-11');

        // ตี 3 ผ่านไปแล้ว วันทำการที่เปิดไว้หมดอายุ ต้องเปิดวันใหม่ก่อน
        Carbon::setTestNow('2026-10-12 03:30:00');

        Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->assertRedirect('/inventory/open-close-day');
    }

    public function test_opening_a_day_after_midnight_defaults_to_the_day_still_in_progress(): void
    {
        Carbon::setTestNow('2026-10-12 00:01:00');

        // หน้าเปิด/ปิดวันต้องชี้ไปวันทำการเดียวกับที่หน้าซื้อขายมองหา
        // ไม่งั้นพนักงานจะเปิดวันที่ 12 ทิ้งไว้แล้วยังทำรายการไม่ได้
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->assertSet('date', '2026-10-11');
    }

    public function test_an_unopened_day_still_blocks_trading_after_midnight(): void
    {
        // ไม่มีใครเปิดวันทำการเลย การข้ามเที่ยงคืนต้องไม่กลายเป็นช่องให้ผ่าน
        Carbon::setTestNow('2026-10-12 00:01:00');

        Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->assertRedirect('/inventory/open-close-day');
    }
}
