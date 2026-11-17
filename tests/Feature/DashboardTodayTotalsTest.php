<?php

namespace Tests\Feature;

use App\Models\TransactionDetail;
use App\Models\TransactionMaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * บิลขายเก็บ amount = บาท, total = เงินต่างประเทศ ซึ่งกลับด้านกับบิลซื้อ
 * หน้าแรกต้องสลับคอลัมน์ตามประเภทบิล ไม่งั้นยอดขายเป็นจำนวน USD และ
 * จำนวน USD ในการ์ดสกุลเงินจะมีบาทของบิลขายปนเข้ามา
 */
class DashboardTodayTotalsTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function bill(string $type, string $no, float $amount, float $total, float $rate): void
    {
        $tx = TransactionMaster::create([
            'trns_no' => $no,
            'trns_type' => $type,
            'counter_id' => $this->counter->id,
            'counter_name' => $this->counter->counter_name,
            'convert_currency_to' => 'THB',
            'trns_datetime' => now(),
            'created_by' => $this->staffUser->id,
            'updated_by' => $this->staffUser->id,
        ]);

        TransactionDetail::create([
            'transaction_id' => $tx->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'currency_name' => 'USD 100-50',
            'amount' => $amount,
            'unit_price' => $rate,
            'total' => $total,
            'created_by' => $this->staffUser->id,
        ]);
    }

    public function test_sell_bills_are_not_mixed_up_with_buy_bills(): void
    {
        // รับซื้อ 1,000 USD จ่าย 33,710 บาท
        $this->bill('BUYING', 'B0001', 1000, 33710, 33.71);
        // ขาย 200 USD รับ 6,766 บาท — เก็บกลับด้าน
        $this->bill('SELLING', 'S0001', 6766, 200, 33.83);

        $this->actingAsStaff()
            ->withSession(['working_counter_id' => $this->counter->id])
            ->get(route('dashboard'))
            ->assertStatus(200)
            ->assertSee('6,766.00 THB')      // ขายวันนี้ + บรรทัดขายในการ์ด USD
            ->assertSee('ซื้อ 1,000.00')
            ->assertSee('33,710.00 THB')
            ->assertSee('ขาย 200.00')
            ->assertDontSee('7,766.00');
    }
}
