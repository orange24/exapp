<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * Same root cause as InventoryDashboardBranchTest and
 * StockTransferListVisibilityTest: a staff member's home branch (their user
 * record's branch_id) can differ from the branch they're currently clocked
 * in at (session working_branch_id, via the sidebar "สาขาทำงาน" switcher).
 * These three inventory pages each had their own hand-rolled
 * "Auth::user()->branch_id" counter filter instead of
 * User::getVisibleBranchIds(), so a staff member working away from home
 * only ever saw their home branch's counter in these dropdowns.
 */
class CrossBranchCounterVisibilityTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function otherBranchCounter(): Counter
    {
        $branch = Branch::create([
            'branch_code' => 'HKT-01',
            'branch_name' => 'Oldtown',
            'type' => 'branch',
            'city' => 'Phuket',
            'is_active' => true,
        ]);

        return Counter::create([
            'counter_code' => 'HKT-C1',
            'counter_name' => 'Oldtown Booth',
            'branch_id' => $branch->id,
            'is_active' => true,
        ]);
    }

    private function workingAtOtherBranch(Counter $counter): self
    {
        $this->actingAs($this->staffUser)->withSession([
            'working_branch_id' => $counter->branch_id,
            'working_counter_id' => $counter->id,
        ]);

        return $this;
    }

    public function test_stock_adjustment_counter_dropdown_follows_working_branch(): void
    {
        $counter = $this->otherBranchCounter();
        $this->workingAtOtherBranch($counter);

        Livewire::test(\App\Livewire\Inventory\StockAdjustment::class)
            ->assertSee($counter->counter_name)
            ->assertDontSee($this->counter->counter_name)
            ->assertDontSee($this->counter2->counter_name);
    }

    public function test_open_close_day_counter_dropdown_follows_working_branch(): void
    {
        $counter = $this->otherBranchCounter();
        $this->workingAtOtherBranch($counter);

        Livewire::test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->assertSee($counter->counter_name)
            ->assertDontSee($this->counter->counter_name)
            ->assertDontSee($this->counter2->counter_name);
    }

    public function test_stock_movements_counter_dropdown_follows_working_branch(): void
    {
        $counter = $this->otherBranchCounter();
        $this->workingAtOtherBranch($counter);

        Livewire::test(\App\Livewire\Inventory\StockMovements::class)
            ->assertSee($counter->counter_name)
            ->assertDontSee($this->counter->counter_name)
            ->assertDontSee($this->counter2->counter_name);
    }

    public function test_admin_still_sees_every_branchs_counters_on_all_three_pages(): void
    {
        $counter = $this->otherBranchCounter();

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockAdjustment::class)
            ->assertSee($counter->counter_name)
            ->assertSee($this->counter->counter_name);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->assertSee($counter->counter_name)
            ->assertSee($this->counter->counter_name);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\StockMovements::class)
            ->assertSee($counter->counter_name)
            ->assertSee($this->counter->counter_name);
    }
}
