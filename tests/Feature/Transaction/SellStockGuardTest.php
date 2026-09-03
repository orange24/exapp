<?php

namespace Tests\Feature\Transaction;

use App\Models\CounterRate;
use App\Models\CounterStock;
use App\Models\TransactionMaster;
use App\Models\WorkingDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * Selling more than the counter holds must be refused by the form, not by an
 * uncaught RuntimeException from InventoryService::recordSell() — that surfaces
 * to the cashier as a 500 page on "บันทึก & พิมพ์".
 */
class SellStockGuardTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        CounterRate::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'rate_date' => now()->toDateString(),
            'rate_buy' => 33.32,
            'rate_sell' => 38.00,
        ]);

        WorkingDay::insert([
            'counter_id' => $this->counter->id,
            'work_date' => now()->format('Y-m-d'),
            'opening_thb_cash' => 10000,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function stock(float $quantity): void
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

    private function form()
    {
        return Livewire::actingAs($this->staffUser)
            ->test('transaction.sell-form')
            ->set('counterId', (string) $this->counter->id);
    }

    /** A zero-quantity stock row must not be read as "no limit configured". */
    public function test_row_is_refused_when_stock_row_exists_but_is_empty(): void
    {
        $this->stock(0);

        $this->form()
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 700)   // 700 / 38.00 → 18 USD
            ->call('addRow')
            ->assertHasErrors('stock')
            ->assertCount('rows', 0)
            ->assertSee('คงเหลือ 0 ที่เคาน์เตอร์นี้');
    }

    /** No stock row at all is also zero available, not "unknown". */
    public function test_row_is_refused_when_counter_has_no_stock_row(): void
    {
        $this->form()
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 700)
            ->call('addRow')
            ->assertHasErrors('stock')
            ->assertCount('rows', 0);
    }

    /** Sufficient stock still goes through untouched. */
    public function test_row_is_added_when_stock_is_sufficient(): void
    {
        $this->stock(100);

        $this->form()
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 700)
            ->call('addRow')
            ->assertHasNoErrors()
            ->assertCount('rows', 1)
            ->assertSet('rows.0.total', 18.0);
    }

    /**
     * Stock can be drained by another counter session between "เพิ่ม" and
     * "บันทึก & พิมพ์". The save must fail closed with a message, never a 500.
     */
    public function test_save_reports_an_error_instead_of_crashing_when_stock_ran_out(): void
    {
        $this->stock(100);

        $component = $this->form()
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 700)
            ->call('addRow')
            ->assertCount('rows', 1);

        CounterStock::where('counter_id', $this->counter->id)->update(['quantity' => 0]);

        $component->call('saveTransaction')
            ->assertHasErrors('stock')
            ->assertSet('showPrintSlip', false)
            ->assertCount('rows', 1);

        $this->assertSame(0, TransactionMaster::count());
    }
}
