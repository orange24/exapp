<?php

namespace Tests\Feature\Inventory;

use App\Models\CounterStock;
use App\Models\StockTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class StockTransferTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        // Seed source counter with stock
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 1000,
            'hold_amount' => 0,
            'avg_cost' => 35,
            'total_cost_value' => 35000,
        ]);
    }

    public function test_borrow_transfer(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockTransferForm::class, ['type' => 'borrow'])
            ->set('fromCounterId', $this->counter->id)
            ->set('toCounterId', $this->counter2->id)
            ->set('currencyCode', 'USD')
            ->set('denominationId', $this->denomination->id)
            ->set('amount', '200')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('stock_transfers', [
            'transfer_type' => 'borrow',
            'from_counter_id' => $this->counter->id,
            'to_counter_id' => $this->counter2->id,
        ]);

        $source = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();
        $this->assertEquals(800, (float) $source->quantity);

        $dest = CounterStock::where('counter_id', $this->counter2->id)
            ->where('denomination_id', $this->denomination->id)->first();
        $this->assertEquals(200, (float) $dest->quantity);
    }

    public function test_transfer_fails_when_same_counter(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockTransferForm::class, ['type' => 'borrow'])
            ->set('fromCounterId', $this->counter->id)
            ->set('toCounterId', $this->counter->id) // same counter
            ->set('currencyCode', 'USD')
            ->set('denominationId', $this->denomination->id)
            ->set('amount', '200')
            ->call('save')
            ->assertHasErrors('toCounterId');
    }

    public function test_transfer_fails_when_insufficient_stock(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockTransferForm::class, ['type' => 'borrow'])
            ->set('fromCounterId', $this->counter->id)
            ->set('toCounterId', $this->counter2->id)
            ->set('currencyCode', 'USD')
            ->set('denominationId', $this->denomination->id)
            ->set('amount', '9999') // more than available
            ->call('save')
            ->assertHasErrors('amount');
    }

    public function test_disbursement_transfer(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockTransferForm::class, ['type' => 'disbursement'])
            ->set('fromCounterId', $this->counter->id)
            ->set('toCounterId', $this->counter2->id)
            ->set('currencyCode', 'USD')
            ->set('denominationId', $this->denomination->id)
            ->set('amount', '500')
            ->call('save')
            ->assertHasNoErrors();

        $transfer = StockTransfer::where('transfer_type', 'disbursement')->first();
        $this->assertNotNull($transfer);
        $this->assertStringStartsWith('TD', $transfer->transfer_no);
    }
}
