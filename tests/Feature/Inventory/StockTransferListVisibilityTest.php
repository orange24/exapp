<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * Staff working a shift away from their home branch (session working_branch_id,
 * see the "สาขาทำงาน" sidebar switcher) creates a borrow/return between two
 * counters at the branch they're currently at. The list below the form must
 * still show it — before the fix it was filtered by Auth::user()->branch_id
 * (home branch), so the transfer they just made vanished from their own list.
 */
class StockTransferListVisibilityTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function otherBranchWithTwoCounters(): array
    {
        $branch = Branch::create([
            'branch_code' => 'HKT-01',
            'branch_name' => 'Oldtown',
            'type' => 'branch',
            'city' => 'Phuket',
            'is_active' => true,
        ]);

        $from = Counter::create([
            'counter_code' => 'HKT-C1', 'counter_name' => 'Oldtown Booth',
            'branch_id' => $branch->id, 'is_active' => true,
        ]);
        $to = Counter::create([
            'counter_code' => 'HKT-C2', 'counter_name' => 'Oldtown Airport',
            'branch_id' => $branch->id, 'is_active' => true,
        ]);

        CounterStock::create([
            'counter_id' => $from->id, 'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 1000, 'hold_amount' => 0, 'avg_cost' => 35, 'total_cost_value' => 35000,
        ]);

        return [$branch, $from, $to];
    }

    public function test_staff_sees_transfer_made_at_the_branch_they_are_currently_working(): void
    {
        [$branch, $from, $to] = $this->otherBranchWithTwoCounters();

        // staffUser's home branch is $this->branch (seeded HQ Bangkok) — they are
        // currently clocked in at the other branch via the session switcher.
        $this->actingAs($this->staffUser)
            ->withSession(['working_branch_id' => $branch->id, 'working_counter_id' => $from->id]);

        $component = Livewire::test(\App\Livewire\Inventory\StockTransferForm::class, ['type' => 'borrow'])
            ->set('fromCounterId', $from->id)
            ->set('toCounterId', $to->id)
            ->set('currencyCode', 'USD')
            ->set('denominationId', $this->denomination->id)
            ->set('amount', '200')
            ->call('save')
            ->assertHasNoErrors();

        $component->assertSee($from->counter_name)
            ->assertSee($to->counter_name);
    }

    public function test_staff_does_not_see_transfers_from_branches_they_are_not_working_at(): void
    {
        [$branch, $from, $to] = $this->otherBranchWithTwoCounters();

        \App\Services\InventoryService::class; // ensure autoload before app() call below
        app(\App\Services\InventoryService::class)->transferStock(
            transferType: 'borrow',
            fromCounterId: $from->id,
            toCounterId: $to->id,
            currencyCode: 'USD',
            denominationId: $this->denomination->id,
            amount: 100,
            userId: $this->adminUser->id,
        );

        // staffUser never switched to Oldtown — still on their home branch.
        Livewire::actingAs($this->staffUser)
            ->test(\App\Livewire\Inventory\StockTransferForm::class, ['type' => 'borrow'])
            ->assertDontSee($from->counter_name)
            ->assertDontSee($to->counter_name);
    }
}
