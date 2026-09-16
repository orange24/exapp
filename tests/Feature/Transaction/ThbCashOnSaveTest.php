<?php

namespace Tests\Feature\Transaction;

use App\Models\CounterRate;
use App\Models\CounterStock;
use App\Models\StockMovement;
use App\Models\WorkingDay;
use App\Services\ThbCashService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * เงินบาทในลิ้นชักต้องเคลื่อนไหวตามบิลที่หน้าเคาน์เตอร์บันทึก
 *
 * จุดสำคัญคือทิศทางและคอลัมน์ที่ใช้: ฝั่งรับซื้อ detail.total คือเงินบาท
 * ฝั่งขาย detail.amount คือเงินบาท — สลับกัน ถ้าหยิบผิดคอลัมน์ยอดจะเพี้ยน
 * โดยไม่มีอะไรฟ้อง
 */
class ThbCashOnSaveTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private ThbCashService $thb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->thb = app(ThbCashService::class);

        CounterRate::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'rate_date' => now()->toDateString(),
            'rate_buy' => 33.00,
            'rate_sell' => 38.00,
        ]);

        WorkingDay::insert([
            'counter_id' => $this->counter->id,
            'work_date' => now()->format('Y-m-d'),
            'opening_thb_cash' => 0,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function seedThb(float $amount): void
    {
        $this->thb->apply(
            $this->counter->id,
            StockMovement::THB_IN,
            $amount,
            ThbCashService::REF_TOPUP,
            null,
            $this->adminUser->id,
            null,
            now()->subHour(),
        );
    }

    private function seedUsdStock(float $quantity): void
    {
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => $quantity,
            'hold_amount' => 0,
            'avg_cost' => 33,
            'total_cost_value' => 33 * $quantity,
        ]);
    }

    public function test_buying_foreign_currency_pays_baht_out_of_the_drawer(): void
    {
        $this->seedThb(100000);

        Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('custName', 'Test Customer')
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 100)         // 100 USD × 33.00 = 3,300 บาท
            ->call('addRow')
            ->call('saveTransaction')
            ->assertHasNoErrors();

        $this->assertSame(96700.0, $this->thb->balance($this->counter->id));

        $this->assertDatabaseHas('stock_movements', [
            'counter_id' => $this->counter->id,
            'currency_code' => 'THB',
            'denomination_id' => null,
            'movement_type' => StockMovement::THB_OUT,
            'amount' => -3300,
        ]);
    }

    public function test_selling_foreign_currency_takes_baht_into_the_drawer(): void
    {
        $this->seedUsdStock(1000);

        Livewire::actingAs($this->staffUser)
            ->test('transaction.sell-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('custName', 'Test Customer')
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 3800)        // ลูกค้าจ่าย 3,800 บาท → รับ 100 USD
            ->call('addRow')
            ->call('saveTransaction')
            ->assertHasNoErrors();

        $this->assertSame(3800.0, $this->thb->balance($this->counter->id));

        $this->assertDatabaseHas('stock_movements', [
            'counter_id' => $this->counter->id,
            'currency_code' => 'THB',
            'denomination_id' => null,
            'movement_type' => StockMovement::THB_IN,
            'amount' => 3800,
        ]);
    }

    public function test_buying_warns_but_still_saves_when_the_drawer_is_short(): void
    {
        $this->seedThb(1000);   // มีแค่พันบาท แต่ต้องจ่าย 3,300

        $component = Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('custName', 'Test Customer')
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 100)
            ->call('addRow')
            ->call('saveTransaction')
            ->assertHasNoErrors();

        // บันทึกได้ ไม่บล็อก — แต่ต้องเตือน
        $this->assertNotSame('', $component->get('thbWarning'));
        $component->assertSee('ยอดเงินบาทในลิ้นชักไม่พอจ่าย');

        $this->assertDatabaseCount('transactions_master', 1);
        $this->assertSame(-2300.0, $this->thb->balance($this->counter->id));
    }

    public function test_no_warning_is_shown_when_the_drawer_has_enough(): void
    {
        $this->seedThb(100000);

        Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('custName', 'Test Customer')
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 100)
            ->call('addRow')
            ->call('saveTransaction')
            ->assertSet('thbWarning', '');
    }
}
