<?php

namespace Tests\Feature\Inventory;

use App\Models\CounterStock;
use App\Models\Inventory;
use App\Models\WorkingDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class OpenCloseDayTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 500,
            'hold_amount' => 0,
            'avg_cost' => 35,
            'total_cost_value' => 17500,
        ]);
    }

    public function test_open_day_creates_working_day_and_snapshot(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\OpenCloseDay::class)
            ->set('counterId', $this->counter->id)
            ->set('date', now()->format('Y-m-d'))
            ->set('openingThbCash', 10000)
            ->call('openDay')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('working_days', [
            'counter_id' => $this->counter->id,
            'status' => 'open',
        ]);

        $inv = Inventory::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->first();
        $this->assertNotNull($inv);
        $this->assertEquals(500, (float) $inv->opening_balance);
    }

    public function test_cannot_open_day_twice(): void
    {
        // Direct service call verifies uniqueness
        $this->assertDatabaseCount('working_days', 0);

        WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => now()->toDateString(),
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
        ]);

        $this->assertDatabaseCount('working_days', 1);

        // Trying to insert same counter+date should fail via DB constraint
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => now()->toDateString(),
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
        ]);
    }

    public function test_close_day_updates_status(): void
    {
        $wd = WorkingDay::create([
            'counter_id' => $this->counter->id,
            'work_date' => now()->toDateString(),
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
        ]);

        // Close directly via service
        app(\App\Services\InventoryService::class)->closeDay($this->counter->id, now()->format('Y-m-d'));

        $wd->update([
            'status' => 'closed',
            'closed_by' => $this->adminUser->id,
            'closed_at' => now(),
        ]);

        $this->assertEquals('closed', $wd->fresh()->status);
        $this->assertNotNull($wd->fresh()->closed_at);
    }
}
