<?php

namespace Tests\Feature\Transaction;

use App\Models\CounterRate;
use App\Models\CounterStock;
use App\Models\WorkingDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * Admin / Branch Manager may override the counter rate on Buy and Sell.
 * Every other role transacts at the configured rate — and because Livewire
 * public properties are writable from the browser, the server must reject a
 * tampered rate rather than trusting the disabled input.
 */
class EditableRateTest extends TestCase
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
            'rate_sell' => 34.20,
        ]);

        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 5000,
            'hold_amount' => 0,
            'avg_cost' => 33,
            'total_cost_value' => 165000,
        ]);

        // Buy/Sell redirect to open-close-day unless the working day is open.
        // Inserted via the query builder to bypass the `work_date` date cast —
        // see PageAccessTest::openWorkingDay() for why.
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

    // ─── Buy ─────────────────────────────────────────────────────────────

    public function test_buy_admin_can_override_rate(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test('transaction.buy-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->assertSet('currentRate', 33.32)
            ->set('currentRate', 35.0)
            ->set('addAmount', 100)
            ->call('addRow')
            ->assertSet('rows.0.rate', 35.0)
            ->assertSet('rows.0.total', 3500.0);
    }

    public function test_buy_staff_rate_is_forced_back_to_configured_rate(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('currentRate', 99.0)          // tampered payload
            ->assertSet('currentRate', 33.32)   // reverted on update
            ->set('addAmount', 100)
            ->call('addRow')
            ->assertSet('rows.0.rate', 33.32)
            ->assertSet('rows.0.total', 3332.0);
    }

    public function test_buy_rate_field_is_editable_only_for_admin(): void
    {
        $this->actingAsAdmin()->get('/transaction/buy')
            ->assertStatus(200)
            ->assertSee('wire:model.blur="currentRate"', false);

        $this->actingAsStaff()->get('/transaction/buy')
            ->assertStatus(200)
            ->assertDontSee('wire:model.blur="currentRate"', false)
            ->assertSee('อัตราซื้อ');
    }

    // ─── Sell ────────────────────────────────────────────────────────────

    public function test_sell_admin_can_override_rate(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test('transaction.sell-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->assertSet('currentRate', 34.20)
            ->set('currentRate', 40.0)
            ->set('addAmount', 4000)
            ->call('addRow')
            ->assertSet('rows.0.rate', 40.0)
            ->assertSet('rows.0.total', 100.0);   // floor(4000 / 40)
    }

    public function test_sell_staff_rate_is_forced_back_to_configured_rate(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test('transaction.sell-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('currentRate', 1.0)            // tampered payload
            ->assertSet('currentRate', 34.20)    // reverted on update
            ->set('addAmount', 3500)
            ->call('addRow')
            ->assertSet('rows.0.rate', 34.20)
            ->assertSet('rows.0.total', 102.0);  // floor(3500 / 34.20)
    }

    public function test_sell_rate_field_is_editable_only_for_admin(): void
    {
        $this->actingAsAdmin()->get('/transaction/sell')
            ->assertStatus(200)
            ->assertSee('wire:model.blur="currentRate"', false);

        $this->actingAsStaff()->get('/transaction/sell')
            ->assertStatus(200)
            ->assertDontSee('wire:model.blur="currentRate"', false)
            ->assertSee('อัตราขาย');
    }
}
