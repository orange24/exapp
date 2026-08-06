<?php

namespace Tests\Feature\Rate;

use App\Models\CounterRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ปุ่ม "คัดลอกราคา" และ "ดึงราคาตั้งต้น" เคย return เงียบเมื่อไม่เจอข้อมูล
 * คนกดแยกไม่ออกว่าไม่มีข้อมูลหรือระบบค้าง — ต้องมีข้อความเสมอ
 */
class RateSetupNoticeTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function board(?int $counterId = null)
    {
        return Livewire::actingAs($this->adminUser)
            ->test('rate.rate-setup-board', ['counterId' => $counterId ?? $this->counter->id]);
    }

    public function test_auto_set_warns_when_there_is_no_previous_day_rate(): void
    {
        $this->board()
            ->call('autoSetRates')
            ->assertSet('noticeType', 'warn')
            ->assertSee('ไม่พบราคาของวันก่อนหน้า');
    }

    public function test_auto_set_reports_how_many_rates_it_pulled(): void
    {
        CounterRate::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'rate_buy' => 33.10,
            'rate_sell' => 34.00,
            'rate_date' => now()->subDay(),
        ]);

        $component = $this->board()->call('autoSetRates');

        $component->assertSet('noticeType', 'success')
            ->assertSee('มาแล้ว 1 รายการ');

        $rates = $component->get('rates');
        $this->assertEquals(33.10, $rates[$this->denomination->id]['buy']);
        $this->assertEquals(34.00, $rates[$this->denomination->id]['sell']);
    }

    public function test_copy_warns_when_no_source_counter_is_selected(): void
    {
        $this->board()
            ->call('copyRates')
            ->assertSet('noticeType', 'warn')
            ->assertSee('กรุณาเลือกเคาน์เตอร์ต้นทางก่อน');
    }

    public function test_copy_warns_when_the_source_counter_has_no_rates(): void
    {
        $this->board()
            ->set('copyFromCode', $this->counter2->counter_code)
            ->call('copyRates')
            ->assertSet('noticeType', 'warn')
            ->assertSee('ยังไม่เคยตั้งราคาไว้เลย');
    }

    public function test_copy_reports_how_many_rates_it_copied(): void
    {
        CounterRate::create([
            'counter_id' => $this->counter2->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'rate_buy' => 32.50,
            'rate_sell' => 33.50,
            'rate_date' => today(),
        ]);

        $component = $this->board()
            ->set('copyFromCode', $this->counter2->counter_code)
            ->call('copyRates');

        $component->assertSet('noticeType', 'success')
            ->assertSee('มาแล้ว 1 รายการ');

        $this->assertEquals(32.50, $component->get('rates')[$this->denomination->id]['buy']);
    }
}
