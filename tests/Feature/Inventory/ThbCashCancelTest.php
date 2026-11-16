<?php

namespace Tests\Feature\Inventory;

use App\Livewire\Inventory\OpenCloseDay;
use App\Models\CounterStock;
use App\Models\StockMovement;
use App\Services\ThbCashService;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Traits\SeedsTestData;

class ThbCashCancelTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private ThbCashService $thb;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->thb = app(ThbCashService::class);
    }

    private function thbStockQty(): float
    {
        return (float) CounterStock::where('counter_id', $this->counter->id)->thb()->value('quantity');
    }

    public function test_request_alone_does_not_change_balance(): void
    {
        $this->thb->topUp($this->counter->id, 100000, $this->adminUser->id);
        $topup = $this->thb->topUp($this->counter->id, 5000, $this->staffUser->id);

        $this->thb->requestCancel($topup->id, $this->staffUser->id, 'กดผิด');

        $this->assertSame('R', $topup->fresh()->flag_cancel);
        $this->assertSame(105000.0, $this->thb->balance($this->counter->id));
    }

    public function test_approve_removes_movement_from_balance_and_counter_stock(): void
    {
        $this->thb->topUp($this->counter->id, 100000, $this->adminUser->id);
        $withdraw = $this->thb->withdraw($this->counter->id, 30000, $this->staffUser->id);

        $this->thb->requestCancel($withdraw->id, $this->staffUser->id, 'ไม่ได้นำออกจริง');
        $this->thb->approveCancel($withdraw->id, $this->adminUser->id);

        $this->assertSame('Y', $withdraw->fresh()->flag_cancel);
        $this->assertSame(100000.0, $this->thb->balance($this->counter->id));
        $this->assertSame(100000.0, $this->thbStockQty());

        $summary = $this->thb->summaryFor($this->counter->id, $this->workingDate());
        $this->assertSame(0.0, $summary['out']);
    }

    public function test_reject_returns_movement_to_normal(): void
    {
        $topup = $this->thb->topUp($this->counter->id, 5000, $this->staffUser->id);

        $this->thb->requestCancel($topup->id, $this->staffUser->id, 'กดผิด');
        $this->thb->rejectCancel($topup->id);

        $fresh = $topup->fresh();
        $this->assertSame('N', $fresh->flag_cancel);
        $this->assertNull($fresh->cancel_reason);
        $this->assertSame(5000.0, $this->thb->balance($this->counter->id));
    }

    public function test_bill_movements_cannot_be_cancelled_here(): void
    {
        $sale = $this->thb->recordSaleReceipt($this->counter->id, 3500, 1, $this->staffUser->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->thb->requestCancel($sale->id, $this->staffUser->id, 'ลองลบ');
    }

    public function test_reason_is_required(): void
    {
        $topup = $this->thb->topUp($this->counter->id, 5000, $this->staffUser->id);

        $this->expectException(\InvalidArgumentException::class);
        $this->thb->requestCancel($topup->id, $this->staffUser->id, '   ');
    }

    public function test_staff_can_request_but_not_approve(): void
    {
        $topup = $this->thb->topUp($this->counter->id, 5000, $this->staffUser->id);

        Livewire::actingAs($this->staffUser)
            ->test(OpenCloseDay::class)
            ->set('counterId', (string) $this->counter->id)
            ->set('date', $this->workingDate())
            ->call('startCancelMovement', $topup->id)
            ->set('cancelMovementReason', 'กดผิด')
            ->call('requestCancelMovement')
            ->call('approveCancelMovement', $topup->id);

        $this->assertSame('R', $topup->fresh()->flag_cancel);
        $this->assertSame(5000.0, $this->thb->balance($this->counter->id));
    }

    public function test_manager_approves_from_the_page_and_row_stays_in_history(): void
    {
        $topup = $this->thb->topUp($this->counter->id, 5000, $this->staffUser->id);
        $this->thb->requestCancel($topup->id, $this->staffUser->id, 'กดผิด');

        Livewire::actingAs($this->adminUser)
            ->test(OpenCloseDay::class)
            ->set('counterId', (string) $this->counter->id)
            ->set('date', $this->workingDate())
            ->call('approveCancelMovement', $topup->id)
            ->assertSee('ลบแล้ว')
            ->assertSee('กดผิด');

        $this->assertSame('Y', $topup->fresh()->flag_cancel);
        $this->assertSame(0.0, $this->thb->balance($this->counter->id));
        $this->assertSame(1, StockMovement::query()->thbWithCancelled()->count());
    }
}
