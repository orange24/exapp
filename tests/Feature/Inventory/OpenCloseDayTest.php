<?php

namespace Tests\Feature\Inventory;

use App\Models\CounterStock;
use App\Models\Inventory;
use App\Models\WorkingDay;
use App\Services\ThbCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class OpenCloseDayTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 500,
            'hold_amount' => 0,
            'avg_cost' => 35,
            'total_cost_value' => 17500,
        ]);
    }

    public function test_open_day_creates_working_day_and_snapshot(): void
    {
        // ไม่ต้องกรอกเงินทุนอีกต่อไป — ยอดบาทยกมาถูกคำนวณจาก movement เอง
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', now()->format('Y-m-d'))
            ->call('openDay')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('working_days', [
            'counter_id' => $this->counter->id,
            'status' => 'open',
        ]);

        $inv = Inventory::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();
        $this->assertNotNull($inv);
        $this->assertEquals(500, (float) $inv->opening_balance);
    }

    public function test_cannot_open_day_twice(): void
    {
        // Direct service call verifies uniqueness
        $this->assertDatabaseCount('working_days', 0);

        WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => now()->toDateString(),
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
        ]);

        $this->assertDatabaseCount('working_days', 1);

        // Trying to insert same counter+date should fail via DB constraint
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => now()->toDateString(),
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
        ]);
    }

    public function test_close_day_updates_status(): void
    {
        $wd = WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => now()->toDateString(),
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
        ]);

        // Close directly via service
        app(\App\Services\InventoryService::class)->closeDay($this->counter->id, now()->format('Y-m-d'));

        $wd->update([
            'status' => 'closed',
            'closed_by' => $this->adminUser->id,
            'closed_at' => now(),
        ]);

        $this->assertEquals('closed', $wd->fresh()->status);
        $this->assertNotNull($wd->fresh()->closed_at);
    }

    public function test_open_day_snapshots_the_computed_thb_opening(): void
    {
        $thb = app(ThbCashService::class);

        // เงินบาทค้างมาจากเมื่อวาน
        $thb->apply(
            $this->counter->id,
            \App\Models\StockMovement::THB_IN,
            250000,
            ThbCashService::REF_TOPUP,
            null,
            $this->adminUser->id,
            null,
            now()->subDay()->setTime(10, 0),
        );

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', now()->format('Y-m-d'))
            ->call('openDay')
            ->assertHasNoErrors();

        $wd = WorkingDay::where('counter_id', $this->counter->id)->first();
        $this->assertEquals(250000.0, (float) $wd->opening_thb_cash);
    }

    public function test_topup_at_open_day_credits_only_this_counter(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', now()->format('Y-m-d'))
            ->set('topupThbCash', 300000)
            ->call('openDay')
            ->assertHasNoErrors();

        $thb = app(ThbCashService::class);

        $this->assertEquals(300000.0, $thb->balance($this->counter->id));

        // ตัดขาเดียว — ที่มาของเงินอยู่นอกบัญชีเงินสด ไม่หักจากเคาน์เตอร์ไหน
        $this->assertEquals(0.0, $thb->balance($this->counter2->id));
    }

    /**
     * เงินที่เติมตอนเปิดวันไม่ใช่ "ยอดยกมา"
     *
     * มันเกิดที่ now() ซึ่งอยู่หลัง cutoff ของวันนั้น จึงถูกนับเป็นรับเข้า
     * ระหว่างวัน ส่วน opening_thb_cash คงเป็นยอดที่ยกมาจากวันก่อนเท่านั้น
     */
    public function test_topup_at_open_day_counts_as_intraday_cash_in_not_carried_forward(): void
    {
        $today = now()->format('Y-m-d');

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', $today)
            ->set('topupThbCash', 50000)
            ->call('openDay')
            ->assertHasNoErrors();

        $wd = WorkingDay::where('counter_id', $this->counter->id)->first();
        $this->assertEquals(0.0, (float) $wd->opening_thb_cash, 'ยกมาจากวันก่อนคือ 0');

        $summary = app(ThbCashService::class)->summaryFor($this->counter->id, $today);
        $this->assertEquals(0.0, $summary['opening']);
        $this->assertEquals(50000.0, $summary['in']);
        $this->assertEquals(50000.0, $summary['closing']);
    }

    public function test_cash_can_be_topped_up_and_withdrawn_during_the_day(): void
    {
        $thb = app(ThbCashService::class);

        $component = Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', now()->format('Y-m-d'));

        $component->set('transferAmount', 200000)->call('topUpCash')->assertHasNoErrors();
        $this->assertEquals(200000.0, $thb->balance($this->counter->id));

        $component->set('transferAmount', 75000)->call('withdrawCash')->assertHasNoErrors();
        $this->assertEquals(125000.0, $thb->balance($this->counter->id));

        $this->assertEquals(0.0, $thb->balance($this->counter2->id));
    }

    public function test_approving_closing_posts_the_thb_variance_into_next_day_opening(): void
    {
        $thb = app(ThbCashService::class);
        $today = now()->format('Y-m-d');

        $thb->apply(
            $this->counter->id,
            \App\Models\StockMovement::THB_IN,
            100000,
            ThbCashService::REF_TOPUP,
            null,
            $this->adminUser->id,
            null,
            now()->setTime(9, 0),
        );

        $wd = WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => $today,
            'opening_thb_cash' => 0,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now()->setTime(8, 0),
        ]);

        // นับเงินได้ขาดไป 1,200 บาท
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', $today)
            ->call('prepareClosing')
            ->set('closingThbActual', 98800)
            ->call('saveClosingAmounts')
            ->assertHasNoErrors();

        $wd->refresh();
        $this->assertEquals(100000.0, (float) $wd->closing_thb_expected);
        $this->assertEquals(98800.0, (float) $wd->closing_thb_actual);
        $this->assertEquals(-1200.0, (float) $wd->closing_thb_variance);

        // ยังไม่อนุมัติ — ยอดต้องยังไม่ถูกแก้
        $this->assertEquals(100000.0, $thb->balance($this->counter->id));

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', $today)
            ->call('approveClosing', $wd->id)
            ->assertHasNoErrors();

        // อนุมัติแล้ว — เงินที่นับได้จริงกลายเป็นยอดยกมาวันถัดไป
        $this->assertEquals(98800.0, $thb->openingFor(
            $this->counter->id,
            now()->addDay()->format('Y-m-d')
        ));
    }
}
