<?php

namespace Tests\Feature\Inventory;

use App\Models\CounterStock;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class StockAdjustmentTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    public function test_can_add_stock_adjustment(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockAdjustment::class)
            ->set('counterId', $this->counter->id)
            ->set('currencyCode', 'USD')
            ->set('denominationId', $this->denomination->id)
            ->set('adjustType', 'add')
            ->set('amount', '100')
            ->set('note', 'เงินเกินจากการนับ')
            ->call('save')
            ->assertHasNoErrors();

        $stock = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();
        $this->assertEquals(100, (float) $stock->quantity);

        $movement = StockMovement::where('movement_type', 'adjustment')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(100, (float) $movement->amount);
        $this->assertStringContainsString('ปรับเพิ่ม', $movement->note);
    }

    public function test_can_subtract_stock_adjustment(): void
    {
        // Seed stock first
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 500,
            'hold_amount' => 0,
            'avg_cost' => 35,
            'total_cost_value' => 17500,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockAdjustment::class)
            ->set('counterId', $this->counter->id)
            ->set('currencyCode', 'USD')
            ->set('denominationId', $this->denomination->id)
            ->set('adjustType', 'subtract')
            ->set('amount', '50')
            ->set('note', 'เงินขาดจากการนับ')
            ->call('save')
            ->assertHasNoErrors();

        $stock = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();
        $this->assertEquals(450, (float) $stock->quantity);
    }

    public function test_subtract_fails_when_insufficient_stock(): void
    {
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 50,
            'hold_amount' => 0,
            'avg_cost' => 35,
            'total_cost_value' => 1750,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockAdjustment::class)
            ->set('counterId', $this->counter->id)
            ->set('currencyCode', 'USD')
            ->set('denominationId', $this->denomination->id)
            ->set('adjustType', 'subtract')
            ->set('amount', '100')
            ->set('note', 'ทดสอบ')
            ->call('save')
            ->assertHasErrors('amount');
    }

    public function test_adjustment_requires_reason(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockAdjustment::class)
            ->set('counterId', $this->counter->id)
            ->set('currencyCode', 'USD')
            ->set('denominationId', $this->denomination->id)
            ->set('adjustType', 'add')
            ->set('amount', '100')
            ->set('note', '')
            ->call('save')
            ->assertHasErrors('note');
    }
}
