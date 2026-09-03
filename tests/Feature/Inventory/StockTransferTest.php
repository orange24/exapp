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

    /**
     * cancelTransfer() writes a journal_entries row with type='reversal'. The
     * enum only allowed 'auto'/'manual' until 2026_09_03_150000, so every
     * cancel of an already-journaled transfer failed with a MySQL data
     * truncation error and rolled back — status stayed 'completed' and stock
     * was never restored, with no error surfaced to the user beyond a flashed
     * generic message.
     */
    public function test_cancelling_a_transfer_reverses_stock_and_writes_a_reversal_journal_entry(): void
    {
        // createFromTransfer() no-ops without a TRANSFER account mapping — the
        // original bug (journal_entries.type enum missing 'reversal') only
        // shows up once a transfer actually gets auto-journaled, same as in
        // production where this mapping is always configured.
        $debit = \App\Models\Account::create([
            'account_code' => '1210', 'name_th' => 'สินค้าคงเหลือ (ปลายทาง)', 'name_en' => 'FX Inventory (to)',
            'type' => 'asset', 'level' => 1, 'is_active' => true,
        ]);
        $credit = \App\Models\Account::create([
            'account_code' => '1211', 'name_th' => 'สินค้าคงเหลือ (ต้นทาง)', 'name_en' => 'FX Inventory (from)',
            'type' => 'asset', 'level' => 1, 'is_active' => true,
        ]);
        \App\Models\TransactionAccountMapping::create([
            'trns_type' => 'TRANSFER', 'debit_account_id' => $debit->id, 'credit_account_id' => $credit->id,
            'is_active' => true,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockTransferForm::class, ['type' => 'borrow'])
            ->set('fromCounterId', $this->counter->id)
            ->set('toCounterId', $this->counter2->id)
            ->set('currencyCode', 'USD')
            ->set('denominationId', $this->denomination->id)
            ->set('amount', '200')
            ->call('save')
            ->assertHasNoErrors();

        $transfer = StockTransfer::where('transfer_type', 'borrow')->firstOrFail();

        app(\App\Services\InventoryService::class)->cancelTransfer($transfer->id, $this->adminUser->id);

        $this->assertSame('cancelled', $transfer->fresh()->status);

        $source = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();
        $this->assertEquals(1000, (float) $source->quantity);

        $dest = CounterStock::where('counter_id', $this->counter2->id)
            ->where('denomination_id', $this->denomination->id)->first();
        $this->assertEquals(0, (float) $dest->quantity);

        $this->assertDatabaseHas('journal_entries', [
            'source_type' => 'transfer',
            'source_id' => $transfer->id,
            'type' => 'reversal',
        ]);
    }

    /**
     * The "ประเภท" filter dropdown and the "+" create-button used to be
     * unrelated: picking "คืน (Return)" only changed the list below, so the
     * button kept reading "+ ยืมสินค้า" and, if clicked, opened a borrow form
     * — confusing since nothing on screen suggested that.
     */
    public function test_button_and_form_type_follow_the_filter_dropdown(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockTransferForm::class, ['type' => 'borrow'])
            ->assertSee('+ ยืมสินค้า')
            ->set('filterType', 'return')
            ->assertSet('transferType', 'return')
            ->assertSee('+ คืนสินค้า')
            ->assertDontSee('+ ยืมสินค้า')
            ->set('showForm', true)
            ->assertSee('คืนสินค้าระหว่างเคาน์เตอร์ (Return)');
    }

    /** "ทั้งหมด" (blank) is a valid list filter but not a create-form type. */
    public function test_selecting_all_types_in_the_filter_keeps_the_current_form_type(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockTransferForm::class, ['type' => 'borrow'])
            ->set('filterType', 'disbursement')
            ->assertSet('transferType', 'disbursement')
            ->set('filterType', '')
            ->assertSet('transferType', 'disbursement')
            ->assertSee('+ เบิกจ่าย');
    }
}
