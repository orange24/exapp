<?php

namespace Tests\Feature\Transaction;

use App\Models\CounterStock;
use App\Models\StockMovement;
use App\Models\TransactionMaster;
use App\Models\TransactionDetail;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class CancelTransactionTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    public function test_staff_can_request_cancellation(): void
    {
        $tx = TransactionMaster::create([
            'trns_no' => 'B20260503001',
            'trns_type' => 'BUYING',
            'counter_id' => $this->counter->id,
            'counter_name' => $this->counter->counter_name,
            'flag_cancel' => 'N',
            'trns_datetime' => now(),
            'created_by' => $this->staffUser->id,
        ]);

        $response = $this->actingAsStaff()
            ->postJson(route('transaction.request-cancel', $tx->id), [
                'cancel_reason' => 'ลูกค้าเปลี่ยนใจ',
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('R', $tx->fresh()->flag_cancel);
    }

    public function test_admin_can_approve_cancellation_and_reverse_stock(): void
    {
        // Create stock via InventoryService
        $service = app(InventoryService::class);
        $service->recordBuy(
            $this->counter->id, 'USD', $this->denomination->id,
            500, 35.00, 0, $this->adminUser->id // transactionId = 0 placeholder
        );

        // Create transaction
        $tx = TransactionMaster::create([
            'trns_no' => 'B20260503001',
            'trns_type' => 'BUYING',
            'counter_id' => $this->counter->id,
            'counter_name' => $this->counter->counter_name,
            'flag_cancel' => 'R',
            'cancel_reason' => 'ทดสอบ',
            'trns_datetime' => now(),
            'created_by' => $this->staffUser->id,
        ]);

        // Update the movement to reference this transaction
        StockMovement::where('reference_id', 0)->update(['reference_id' => $tx->id]);

        TransactionDetail::create([
            'transaction_id' => $tx->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'currency_name' => 'US Dollar',
            'unit_price' => 35.00,
            'amount' => 500,
            'total' => 17500,
            'created_by' => $this->staffUser->id,
        ]);

        $response = $this->actingAsAdmin()
            ->postJson(route('transaction.approve-cancel', $tx->id));

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertEquals('Y', $tx->fresh()->flag_cancel);

        // Stock should be reversed
        $stock = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();
        $this->assertEquals(0, (float) $stock->quantity);

        // Adjustment movement should exist
        $adj = StockMovement::where('movement_type', 'adjustment')
            ->where('reference_id', $tx->id)->first();
        $this->assertNotNull($adj);
    }
}
