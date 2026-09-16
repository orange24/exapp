<?php

namespace Tests\Feature\Inventory;

use App\Models\CounterStock;
use App\Models\StockMovement;
use App\Models\WorkingDay;
use App\Services\ThbCashService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class ThbCashLedgerTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private ThbCashService $thb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->thb = app(ThbCashService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** ใส่เงินเข้าลิ้นชักที่เวลาที่กำหนด */
    private function seedMovement(float $signed, string $at, ?int $counterId = null): StockMovement
    {
        return $this->thb->apply(
            $counterId ?? $this->counter->id,
            $signed > 0 ? StockMovement::THB_IN : StockMovement::THB_OUT,
            $signed,
            ThbCashService::REF_TOPUP,
            null,
            $this->adminUser->id,
            null,
            $at,
        );
    }

    public function test_balance_sums_movements(): void
    {
        Carbon::setTestNow('2026-01-10 12:00:00');

        $this->seedMovement(100000, '2026-01-01 09:00:00');
        $this->seedMovement(-25000, '2026-01-02 09:00:00');
        $this->seedMovement(7500.50, '2026-01-03 09:00:00');

        $this->assertSame(82500.50, $this->thb->balance($this->counter->id));
    }

    public function test_opening_carries_forward_across_days_with_no_activity(): void
    {
        // เงินเข้าวันที่ 1 แล้วร้านปิดไปสองสัปดาห์
        $this->seedMovement(500000, '2026-01-01 09:00:00');

        // ยอดยกมาของวันที่ 20 ต้องยังเป็น 500,000 ไม่ใช่ 0
        $this->assertSame(500000.0, $this->thb->openingFor($this->counter->id, '2026-01-20'));
    }

    public function test_opening_ignores_whether_previous_day_was_closed(): void
    {
        $this->seedMovement(200000, '2026-01-01 09:00:00');

        // วันทำการเปิดค้างไว้ ไม่เคยปิดยอด — เหมือนข้อมูลจริงในระบบ
        WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => '2026-01-01',
            'opening_thb_cash' => 0,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => '2026-01-01 08:00:00',
        ]);

        $this->assertSame(200000.0, $this->thb->openingFor($this->counter->id, '2026-01-02'));
    }

    public function test_movement_exactly_at_cutoff_belongs_to_the_closing_day(): void
    {
        // cutoff = 03:00:00 ช่วงของวัน D คือ (D 03:00, D+1 03:00]
        $this->seedMovement(10000, '2026-01-02 03:00:00');

        // ตกอยู่ในยอดปลายวันของวันที่ 1
        $this->assertSame(10000.0, $this->thb->expectedClosingFor($this->counter->id, '2026-01-01'));

        // และกลายเป็นยอดยกมาของวันที่ 2
        $this->assertSame(10000.0, $this->thb->openingFor($this->counter->id, '2026-01-02'));

        // แต่ไม่ถูกนับเป็นความเคลื่อนไหวของวันที่ 2 อีกครั้ง
        $summary = $this->thb->summaryFor($this->counter->id, '2026-01-02');
        $this->assertSame(0.0, $summary['in']);
        $this->assertSame(0.0, $summary['out']);
        $this->assertSame(10000.0, $summary['closing']);
    }

    public function test_movement_one_second_after_cutoff_belongs_to_the_new_day(): void
    {
        $this->seedMovement(10000, '2026-01-02 03:00:01');

        $this->assertSame(0.0, $this->thb->expectedClosingFor($this->counter->id, '2026-01-01'));
        $this->assertSame(0.0, $this->thb->openingFor($this->counter->id, '2026-01-02'));
        $this->assertSame(10000.0, $this->thb->summaryFor($this->counter->id, '2026-01-02')['in']);
    }

    public function test_summary_splits_in_and_out(): void
    {
        $this->seedMovement(100000, '2026-01-01 09:00:00');
        $this->seedMovement(50000, '2026-01-02 10:00:00');
        $this->seedMovement(-30000, '2026-01-02 11:00:00');

        $summary = $this->thb->summaryFor($this->counter->id, '2026-01-02');

        $this->assertSame(100000.0, $summary['opening']);
        $this->assertSame(50000.0, $summary['in']);
        $this->assertSame(-30000.0, $summary['out']);
        $this->assertSame(120000.0, $summary['closing']);
    }

    public function test_purchase_pays_baht_out_and_sale_takes_baht_in(): void
    {
        Carbon::setTestNow('2026-01-05 10:00:00');

        $this->thb->recordSaleReceipt($this->counter->id, 35000, 101, $this->adminUser->id);
        $this->thb->recordPurchasePayment($this->counter->id, 12000, 102, $this->adminUser->id);

        $this->assertSame(23000.0, $this->thb->balance($this->counter->id));

        $this->assertDatabaseHas('stock_movements', [
            'counter_id' => $this->counter->id,
            'currency_code' => 'THB',
            'movement_type' => StockMovement::THB_OUT,
            'reference_type' => ThbCashService::REF_TRANSACTION,
            'reference_id' => 102,
            'amount' => -12000,
        ]);
    }

    public function test_counter_stock_row_is_maintained_for_thb(): void
    {
        Carbon::setTestNow('2026-01-05 10:00:00');

        $this->seedMovement(80000, '2026-01-05 09:00:00');

        $stock = CounterStock::thb()->where('counter_id', $this->counter->id)->first();

        $this->assertNotNull($stock, 'ต้องมีแถว counter_stock ของ THB ไม่งั้น reverseMovement จะข้ามการคืนเงินบาท');

        // decimal ถูกคืนเป็น string บน MySQL แต่เป็น numeric บน sqlite — เทียบค่า ไม่เทียบชนิด
        $this->assertEquals(80000.0, (float) $stock->quantity);
        $this->assertEquals(1.0, (float) $stock->avg_cost);
        $this->assertEquals(80000.0, (float) $stock->total_cost_value);
    }

    public function test_thb_rows_are_invisible_to_foreign_currency_scopes(): void
    {
        $this->seedMovement(80000, '2026-01-05 09:00:00');

        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 1000,
            'hold_amount' => 0,
            'avg_cost' => 35,
            'total_cost_value' => 35000,
        ]);

        $this->assertSame(1, CounterStock::foreignCurrency()->count());
        $this->assertSame(0, StockMovement::foreignCurrency()->count());
        $this->assertSame(1, StockMovement::thb()->count());
    }

    /**
     * เติมเงินทุนตัดขาเดียว — ที่มาของเงินคือบัญชีธนาคาร/เจ้าของ ซึ่งอยู่นอก
     * บัญชีเงินสดเคาน์เตอร์ จึงไม่มีขาหักจากเคาน์เตอร์อื่น
     */
    public function test_top_up_adds_cash_without_debiting_another_counter(): void
    {
        Carbon::setTestNow('2026-01-05 10:00:00');

        $this->thb->topUp($this->counter->id, 120000, $this->adminUser->id, 'เงินทุนเช้า');

        $this->assertSame(120000.0, $this->thb->balance($this->counter->id));
        $this->assertSame(0.0, $this->thb->balance($this->counter2->id), 'เคาน์เตอร์อื่นต้องไม่ถูกแตะ');

        $this->assertDatabaseHas('stock_movements', [
            'counter_id' => $this->counter->id,
            'currency_code' => 'THB',
            'movement_type' => StockMovement::THB_IN,
            'reference_type' => ThbCashService::REF_TOPUP,
            'amount' => 120000,
        ]);

        $this->assertSame(1, StockMovement::thb()->count(), 'ต้องมี movement ขาเดียว ไม่ใช่สองแถว');
    }

    public function test_withdraw_removes_cash_without_crediting_another_counter(): void
    {
        Carbon::setTestNow('2026-01-05 10:00:00');

        $this->seedMovement(300000, '2026-01-05 08:00:00');
        $this->thb->withdraw($this->counter->id, 50000, $this->adminUser->id, 'ฝากเข้าบัญชี');

        $this->assertSame(250000.0, $this->thb->balance($this->counter->id));
        $this->assertSame(0.0, $this->thb->balance($this->counter2->id));

        $this->assertDatabaseHas('stock_movements', [
            'counter_id' => $this->counter->id,
            'movement_type' => StockMovement::THB_OUT,
            'reference_type' => ThbCashService::REF_RETURN,
            'amount' => -50000,
        ]);
    }

    public function test_top_up_and_withdraw_reject_non_positive_amounts(): void
    {
        foreach ([0, -1000] as $amount) {
            try {
                $this->thb->topUp($this->counter->id, $amount, $this->adminUser->id);
                $this->fail("topUp ต้องปฏิเสธจำนวน {$amount}");
            } catch (\InvalidArgumentException) {
                // ตามคาด
            }

            try {
                $this->thb->withdraw($this->counter->id, $amount, $this->adminUser->id);
                $this->fail("withdraw ต้องปฏิเสธจำนวน {$amount}");
            } catch (\InvalidArgumentException) {
                // ตามคาด
            }
        }

        $this->assertSame(0, StockMovement::thb()->count());
    }

    public function test_closing_variance_posts_at_day_end_so_it_becomes_next_day_opening(): void
    {
        $this->seedMovement(100000, '2026-01-02 09:00:00');

        $workingDay = WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => '2026-01-02',
            'opening_thb_cash' => 0,
            'status' => 'closed',
            'opened_by' => $this->adminUser->id,
            'opened_at' => '2026-01-02 08:00:00',
            'closing_thb_expected' => 100000,
            'closing_thb_actual' => 99500,
            'closing_thb_variance' => -500,
        ]);

        // อนุมัติย้อนหลังหลายวัน — ผลต่างต้องไม่ไปตกวันที่อนุมัติ
        Carbon::setTestNow('2026-01-09 16:00:00');
        $movement = $this->thb->postClosingVariance($workingDay, $this->adminUser->id);

        $this->assertNotNull($movement);
        $this->assertSame('2026-01-03 03:00:00', $movement->moved_at->format('Y-m-d H:i:s'));

        // ยอดปลายวันของวันที่ 2 = เงินที่นับได้จริง
        $this->assertSame(99500.0, $this->thb->expectedClosingFor($this->counter->id, '2026-01-02'));

        // และยกไปเป็นยอดยกมาของวันที่ 3
        $this->assertSame(99500.0, $this->thb->openingFor($this->counter->id, '2026-01-03'));
    }

    public function test_closing_variance_is_not_posted_twice(): void
    {
        $workingDay = WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => '2026-01-02',
            'opening_thb_cash' => 0,
            'status' => 'closed',
            'opened_by' => $this->adminUser->id,
            'opened_at' => '2026-01-02 08:00:00',
            'closing_thb_expected' => 100000,
            'closing_thb_actual' => 99500,
            'closing_thb_variance' => -500,
        ]);

        $this->assertNotNull($this->thb->postClosingVariance($workingDay, $this->adminUser->id));
        $this->assertNull($this->thb->postClosingVariance($workingDay, $this->adminUser->id));

        $this->assertSame(1, StockMovement::thb()
            ->where('reference_type', ThbCashService::REF_WORKING_DAY)
            ->count());
    }

    public function test_zero_variance_creates_no_movement(): void
    {
        $workingDay = WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => '2026-01-02',
            'opening_thb_cash' => 0,
            'status' => 'closed',
            'opened_by' => $this->adminUser->id,
            'opened_at' => '2026-01-02 08:00:00',
            'closing_thb_expected' => 100000,
            'closing_thb_actual' => 100000,
            'closing_thb_variance' => 0,
        ]);

        $this->assertNull($this->thb->postClosingVariance($workingDay, $this->adminUser->id));
        $this->assertSame(0, StockMovement::thb()->count());
    }

    public function test_would_go_negative_warns_without_blocking(): void
    {
        Carbon::setTestNow('2026-01-05 10:00:00');
        $this->seedMovement(10000, '2026-01-05 09:00:00');

        $this->assertTrue($this->thb->wouldGoNegative($this->counter->id, 15000));
        $this->assertFalse($this->thb->wouldGoNegative($this->counter->id, 5000));

        // เตือนแล้วแต่ยังบันทึกได้ — ยอดติดลบไม่ถูกบล็อก
        $this->thb->recordPurchasePayment($this->counter->id, 15000, 1, $this->adminUser->id);
        $this->assertSame(-5000.0, $this->thb->balance($this->counter->id));
    }

    public function test_cancelling_a_transaction_restores_the_baht(): void
    {
        Carbon::setTestNow('2026-01-05 10:00:00');

        $this->seedMovement(500000, '2026-01-05 08:00:00');
        $this->thb->recordPurchasePayment($this->counter->id, 70000, 555, $this->adminUser->id);

        $this->assertSame(430000.0, $this->thb->balance($this->counter->id));

        // reverseMovement จะ `continue` ข้ามไปถ้าหาแถว counter_stock ไม่เจอ
        // เทสต์นี้จึงคุ้มกันทั้งการคืนเงิน และการที่แถว THB ต้องมีอยู่จริง
        app(\App\Services\InventoryService::class)->reverseMovement(555, $this->adminUser->id);

        $this->assertSame(500000.0, $this->thb->balance($this->counter->id));
    }

    public function test_thb_can_be_adjusted_from_the_stock_adjustment_screen(): void
    {
        $this->seedMovement(50000, '2026-01-05 09:00:00');

        \Livewire\Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockAdjustment::class)
            ->set('counterId', $this->counter->id)
            ->set('currencyCode', 'THB')   // ไม่ต้องเลือก denomination
            ->set('adjustType', 'subtract')
            ->set('amount', '2500')
            ->set('note', 'เงินขาดจากการนับ')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(47500.0, $this->thb->balance($this->counter->id));

        $this->assertDatabaseHas('stock_movements', [
            'counter_id' => $this->counter->id,
            'currency_code' => 'THB',
            'movement_type' => 'adjustment',
            'amount' => -2500,
        ]);
    }

}
