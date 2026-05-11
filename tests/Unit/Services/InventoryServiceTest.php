<?php

namespace Tests\Unit\Services;

use App\Models\CounterStock;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\TransactionMaster;
use App\Models\TransactionDetail;
use App\Models\WorkingDay;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private InventoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->service = app(InventoryService::class);
    }

    // ─── recordBuy ───────────────────────────────────────────────────────

    public function test_record_buy_creates_stock_and_movement(): void
    {
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            100, 35.00, 999, $this->adminUser->id
        );

        $stock = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();

        $this->assertNotNull($stock);
        $this->assertEquals(100, (float) $stock->quantity);
        $this->assertEquals(35.00, (float) $stock->avg_cost);
        $this->assertEquals(3500, (float) $stock->total_cost_value);

        $movement = StockMovement::where('movement_type', 'buy')->first();
        $this->assertNotNull($movement);
        $this->assertEquals(100, (float) $movement->amount);
        $this->assertEquals(35.00, (float) $movement->unit_price);
    }

    public function test_record_buy_calculates_weighted_average_cost(): void
    {
        // First buy: 100 @ 35.00
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            100, 35.00, 1, $this->adminUser->id
        );

        // Second buy: 200 @ 36.00
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            200, 36.00, 2, $this->adminUser->id
        );

        $stock = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();

        $this->assertEquals(300, (float) $stock->quantity);
        // WAC = (100*35 + 200*36) / 300 = 10700/300 = 35.6667
        $this->assertEqualsWithDelta(35.6667, (float) $stock->avg_cost, 0.001);
    }

    // ─── recordSell ──────────────────────────────────────────────────────

    public function test_record_sell_decreases_stock(): void
    {
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            500, 35.00, 1, $this->adminUser->id
        );

        $this->service->recordSell(
            $this->counter->id, 'USD', $this->denomination->id,
            200, 36.00, 2, $this->adminUser->id
        );

        $stock = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();

        $this->assertEquals(300, (float) $stock->quantity);
        // avg_cost should NOT change on sell
        $this->assertEquals(35.00, (float) $stock->avg_cost);
    }

    public function test_record_sell_creates_negative_movement(): void
    {
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            500, 35.00, 1, $this->adminUser->id
        );

        $this->service->recordSell(
            $this->counter->id, 'USD', $this->denomination->id,
            200, 36.00, 2, $this->adminUser->id
        );

        $movement = StockMovement::where('movement_type', 'sell')->first();
        $this->assertEquals(-200, (float) $movement->amount);
    }

    // ─── reverseMovement ─────────────────────────────────────────────────

    public function test_reverse_buy_decreases_stock_and_recalculates_avg(): void
    {
        // Buy 1: 100 @ 34
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            100, 34.00, 1, $this->adminUser->id
        );

        // Buy 2: 100 @ 36 (this one will be cancelled)
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            100, 36.00, 2, $this->adminUser->id
        );

        // Cancel buy 2
        $this->service->reverseMovement(2, $this->adminUser->id);

        $stock = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();

        $this->assertEquals(100, (float) $stock->quantity);
        // avg_cost should be recalculated from remaining buys (only buy 1: 34.00)
        $this->assertEqualsWithDelta(34.00, (float) $stock->avg_cost, 0.01);
    }

    public function test_reverse_sell_adds_stock_back(): void
    {
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            500, 35.00, 1, $this->adminUser->id
        );

        $this->service->recordSell(
            $this->counter->id, 'USD', $this->denomination->id,
            200, 36.00, 2, $this->adminUser->id
        );

        // Cancel sell
        $this->service->reverseMovement(2, $this->adminUser->id);

        $stock = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();

        $this->assertEquals(500, (float) $stock->quantity);
    }

    public function test_reverse_creates_adjustment_movement(): void
    {
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            100, 35.00, 1, $this->adminUser->id
        );

        $this->service->reverseMovement(1, $this->adminUser->id);

        $adjustment = StockMovement::where('movement_type', 'adjustment')->first();
        $this->assertNotNull($adjustment);
        $this->assertEquals(-100, (float) $adjustment->amount);
        $this->assertStringContainsString('ยกเลิกรายการ', $adjustment->note);
    }

    // ─── transferStock ───────────────────────────────────────────────────

    public function test_transfer_stock_moves_between_counters(): void
    {
        // Seed source counter with stock
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            1000, 35.00, 1, $this->adminUser->id
        );

        $transfer = $this->service->transferStock(
            'borrow',
            $this->counter->id,
            $this->counter2->id,
            'USD',
            $this->denomination->id,
            300,
            $this->adminUser->id,
            'ทดสอบยืม'
        );

        // Source decreased
        $source = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();
        $this->assertEquals(700, (float) $source->quantity);

        // Destination increased
        $dest = CounterStock::where('counter_id', $this->counter2->id)
            ->where('denomination_id', $this->denomination->id)->first();
        $this->assertEquals(300, (float) $dest->quantity);
        $this->assertEquals(35.00, (float) $dest->avg_cost);

        // Transfer record created
        $this->assertNotNull($transfer);
        $this->assertEquals('borrow', $transfer->transfer_type);
        $this->assertStringStartsWith('TB', $transfer->transfer_no);
        $this->assertEquals(300, (float) $transfer->amount);

        // Two movements created
        $out = StockMovement::where('movement_type', 'transfer_out')->first();
        $in = StockMovement::where('movement_type', 'transfer_in')->first();
        $this->assertEquals(-300, (float) $out->amount);
        $this->assertEquals(300, (float) $in->amount);
    }

    public function test_transfer_types_generate_correct_prefixes(): void
    {
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            5000, 35.00, 1, $this->adminUser->id
        );

        $types = ['borrow' => 'TB', 'return' => 'TR', 'disbursement' => 'TD', 'intraday_return' => 'TI'];

        foreach ($types as $type => $prefix) {
            $t = $this->service->transferStock(
                $type, $this->counter->id, $this->counter2->id,
                'USD', $this->denomination->id, 100, $this->adminUser->id
            );
            $this->assertStringStartsWith($prefix, $t->transfer_no, "Transfer type '{$type}' should have prefix '{$prefix}'");
        }
    }

    public function test_transfer_preserves_weighted_avg_at_destination(): void
    {
        // Source has stock @ 35.00
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            1000, 35.00, 1, $this->adminUser->id
        );

        // Destination has existing stock @ 34.00
        $this->service->recordBuy(
            $this->counter2->id, 'USD', $this->denomination->id,
            200, 34.00, 2, $this->adminUser->id
        );

        // Transfer 300 from source (avg 35.00) to destination
        $this->service->transferStock(
            'disbursement', $this->counter->id, $this->counter2->id,
            'USD', $this->denomination->id, 300, $this->adminUser->id
        );

        $dest = CounterStock::where('counter_id', $this->counter2->id)
            ->where('denomination_id', $this->denomination->id)->first();

        $this->assertEquals(500, (float) $dest->quantity);
        // WAC = (200*34 + 300*35) / 500 = 17300/500 = 34.60
        $this->assertEqualsWithDelta(34.60, (float) $dest->avg_cost, 0.01);
    }

    // ─── openDay / closeDay ──────────────────────────────────────────────

    public function test_open_day_snapshots_opening_balance(): void
    {
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            500, 35.00, 1, $this->adminUser->id
        );

        $today = now()->format('Y-m-d');
        $this->service->openDay($this->counter->id, $today);

        // Check inventory was created
        $invCount = Inventory::where('counter_id', $this->counter->id)->count();
        $this->assertGreaterThan(0, $invCount, 'Inventory rows should be created');

        $inv = Inventory::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)
            ->first();

        $this->assertNotNull($inv, 'Inventory row for denomination should exist');
        $this->assertEquals(500, (float) $inv->opening_balance);
    }

    public function test_close_day_calculates_closing_balance(): void
    {
        $this->service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            500, 35.00, 1, $this->adminUser->id
        );

        $today = now()->format('Y-m-d');
        $this->service->openDay($this->counter->id, $today);
        $this->service->closeDay($this->counter->id, $today);

        $inv = Inventory::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)
            ->first();

        $this->assertNotNull($inv);
        $this->assertEquals(500, (float) $inv->closing_balance);
    }
}
