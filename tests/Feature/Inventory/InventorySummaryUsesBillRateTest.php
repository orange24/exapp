<?php

namespace Tests\Feature\Inventory;

use App\Models\CounterStock;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * แถบสรุปด้านบนของหน้า Inventory ต้องแสดงเงินบาทตามเรทที่ใช้จริงในบิล
 *
 * เดิมคิดเป็น ปริมาณ × ต้นทุนเฉลี่ย ทำให้ยอด "ขายออก" ไม่เท่ากับเงินที่ลูกค้าจ่าย
 * เคสจริงที่เจอ: ขาย EUR 200 ที่เรท 38.30 = 7,660 บาท แต่ต้นทุนเฉลี่ยของ EUR
 * ในสต็อกคือ 38.20 แถบจึงแสดง 7,640 ต่างกัน 20 บาท (= กำไรของบิลนั้น) แล้วขัดกับ
 * การ์ดเงินบาทในลิ้นชักที่อยู่หน้าเดียวกันและแสดง 7,660
 */
class InventorySummaryUsesBillRateTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function dashboard()
    {
        return Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\InventoryDashboard::class)
            ->set('counterId', $this->counter->id)
            ->set('date', now()->format('Y-m-d'));
    }

    public function test_sold_tile_uses_the_bill_rate_not_average_cost(): void
    {
        // สต็อกที่เหลือหลังขาย 200 จาก 300 ต้นทุนเฉลี่ย 38.20
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 100,
            'hold_amount' => 0,
            'avg_cost' => 38.20,
            'total_cost_value' => 3820,
        ]);

        // ขาย 200 ที่เรท 38.30 → ลูกค้าจ่าย 7,660
        StockMovement::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'movement_type' => 'sell',
            'amount' => -200,
            'unit_price' => 38.30,
            'reference_type' => 'transaction',
            'reference_id' => 1,
            'moved_by' => $this->staffUser->id,
            'moved_at' => now(),
        ]);

        $summary = $this->dashboard()->get('summary');

        $this->assertEquals(7660.00, round($summary['sold'], 2), 'ต้องเป็นเงินที่ลูกค้าจ่ายจริง ไม่ใช่ 200 × 38.20 = 7,640');

        // ยอดคงเหลือยังเป็นมูลค่าสต็อกตามต้นทุนเฉลี่ย
        $this->assertEquals(3820.00, round($summary['remaining'], 2));
    }

    public function test_bought_tile_uses_the_bill_rate(): void
    {
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 500,
            'hold_amount' => 0,
            'avg_cost' => 33.00,
            'total_cost_value' => 16500,
        ]);

        // รับซื้อ 500 ที่เรท 32.90 → จ่ายจริง 16,450
        StockMovement::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'movement_type' => 'buy',
            'amount' => 500,
            'unit_price' => 32.90,
            'reference_type' => 'transaction',
            'reference_id' => 2,
            'moved_by' => $this->staffUser->id,
            'moved_at' => now(),
        ]);

        $summary = $this->dashboard()->get('summary');

        $this->assertEquals(16450.00, round($summary['bought'], 2));
    }

    /**
     * ยอดคงเหลือต้องไม่ถูกเปลี่ยนไปใช้เรทบิล — สต็อกที่ยังไม่ได้ขายไม่มีเรทของ
     * รายการให้อ้าง มันคือมูลค่าสินค้าคงคลัง
     */
    public function test_remaining_tile_still_uses_average_cost(): void
    {
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 1000,
            'hold_amount' => 0,
            'avg_cost' => 34.5678,
            'total_cost_value' => 34567.80,
        ]);

        $summary = $this->dashboard()->get('summary');

        $this->assertEquals(34567.80, round($summary['remaining'], 2));
        $this->assertEquals(0.0, round($summary['sold'], 2));
        $this->assertEquals(0.0, round($summary['bought'], 2));
    }

    /**
     * ส่วนต่างระหว่างเงินที่รับจริงกับมูลค่าต้นทุนคือกำไร — แถบนี้ไม่ต้องบวกลบลงตัว
     * และกำไรไปดูที่รายงานกำไรขาดทุน ไม่ใช่หน้านี้
     */
    public function test_summary_is_not_expected_to_reconcile(): void
    {
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 100,
            'hold_amount' => 0,
            'avg_cost' => 38.20,
            'total_cost_value' => 3820,
        ]);

        StockMovement::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'movement_type' => 'sell',
            'amount' => -200,
            'unit_price' => 38.30,
            'reference_type' => 'transaction',
            'reference_id' => 3,
            'moved_by' => $this->staffUser->id,
            'moved_at' => now(),
        ]);

        $s = $this->dashboard()->get('summary');

        // opening(300 × 38.20 = 11,460) − sold(7,660) = 3,800 แต่ remaining = 3,820
        // ต่างกัน 20 = กำไรของบิล ซึ่งเป็นพฤติกรรมที่ตั้งใจ
        $gap = ($s['opening'] - $s['sold']) - $s['remaining'];
        $this->assertEquals(-20.00, round($gap, 2));
    }
}
