<?php

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * Staff can switch which branch they're working at (POST /switch-branch,
 * sidebar "สาขาทำงาน" selector) — the Inventory Dashboard's counter list must
 * follow that, not the branch they were hired into. Before the fix,
 * getVisibleBranchIds() always returned the user's home branch_id, so a
 * staff member hired at HQ but working a shift at another branch saw HQ's
 * counters in the dropdown no matter where they actually were.
 */
class InventoryDashboardBranchTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function otherBranchCounter(): Counter
    {
        $otherBranch = Branch::create([
            'branch_code' => 'HKT-01',
            'branch_name' => 'Oldtown',
            'type' => 'branch',
            'city' => 'Phuket',
            'is_active' => true,
        ]);

        return Counter::create([
            'counter_code' => 'HKT-C1',
            'counter_name' => 'Oldtown Booth',
            'branch_id' => $otherBranch->id,
            'is_active' => true,
        ]);
    }

    public function test_counter_list_follows_home_branch_when_staff_never_switched(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test('inventory.inventory-dashboard')
            ->assertSee($this->counter->counter_name)
            ->assertSee($this->counter2->counter_name);
    }

    public function test_counter_list_follows_working_branch_after_staff_switches(): void
    {
        $oldtownCounter = $this->otherBranchCounter();

        $response = $this->actingAs($this->staffUser)
            ->withSession(['working_branch_id' => $oldtownCounter->branch_id])
            ->get('/inventory');

        $response->assertStatus(200)
            ->assertSee($oldtownCounter->counter_name)
            ->assertDontSee($this->counter->counter_name)
            ->assertDontSee($this->counter2->counter_name);
    }

    public function test_admin_still_sees_counters_from_every_branch(): void
    {
        $oldtownCounter = $this->otherBranchCounter();

        Livewire::actingAs($this->adminUser)
            ->test('inventory.inventory-dashboard')
            ->assertSee($this->counter->counter_name)
            ->assertSee($oldtownCounter->counter_name);
    }
}
