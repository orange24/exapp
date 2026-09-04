<?php

namespace Tests\Feature\Inventory;

use App\Models\Booking;
use App\Models\CounterStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class BookingTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    public function test_can_create_buy_booking(): void
    {
        session(['working_counter_id' => $this->counter->id]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\BookingManager::class)
            ->set('type', 'buy')
            ->set('currencyCode', 'USD')
            ->set('amount', '1000')
            ->set('rate', '35.00')
            ->set('expiresIn', '60')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('bookings', [
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'type' => 'buy',
            'status' => 'pending',
        ]);
    }

    public function test_sell_booking_holds_stock(): void
    {
        // Seed stock first
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 5000,
            'hold_amount' => 0,
            'avg_cost' => 35,
            'total_cost_value' => 175000,
        ]);

        session(['working_counter_id' => $this->counter->id]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\BookingManager::class)
            ->set('type', 'sell')
            ->set('currencyCode', 'USD')
            ->set('amount', '500')
            ->set('rate', '36.00')
            ->set('expiresIn', '30')
            ->call('save')
            ->assertHasNoErrors();

        $stock = CounterStock::where('counter_id', $this->counter->id)
            ->where('currency_code', 'USD')->first();
        $this->assertEquals(500, (float) $stock->hold_amount);
    }

    public function test_confirm_booking_releases_hold(): void
    {
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 5000,
            'hold_amount' => 500,
            'avg_cost' => 35,
            'total_cost_value' => 175000,
        ]);

        $booking = Booking::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'amount' => 500,
            'rate' => 36,
            'type' => 'sell',
            'status' => 'pending',
            'hold_amount' => 500,
            'expires_at' => now()->addHour(),
            'created_by' => $this->adminUser->id,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\BookingManager::class)
            ->call('confirmBooking', $booking->id)
            ->assertHasNoErrors();

        $this->assertEquals('confirmed', $booking->fresh()->status);

        $stock = CounterStock::where('counter_id', $this->counter->id)
            ->where('currency_code', 'USD')->first();
        $this->assertEquals(0, (float) $stock->hold_amount);
    }

    public function test_cancel_booking_releases_hold(): void
    {
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 5000,
            'hold_amount' => 500,
            'avg_cost' => 35,
            'total_cost_value' => 175000,
        ]);

        $booking = Booking::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'amount' => 500,
            'rate' => 36,
            'type' => 'sell',
            'status' => 'pending',
            'hold_amount' => 500,
            'expires_at' => now()->addHour(),
            'created_by' => $this->adminUser->id,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\BookingManager::class)
            ->call('cancelBooking', $booking->id)
            ->assertHasNoErrors();

        $this->assertEquals('cancelled', $booking->fresh()->status);
        $stock = CounterStock::where('counter_id', $this->counter->id)
            ->where('currency_code', 'USD')->first();
        $this->assertEquals(0, (float) $stock->hold_amount);
    }

    /**
     * The "ผู้สร้าง" column used to show User::name, which is a fixed
     * account label (often literally naming the staff's home office, e.g.
     * "Staff สำนักงานใหญ่ กรุงเทพฯ") — misleading once a staff member works a
     * shift at a different branch, since it never reflected where the
     * booking actually reserves stock. It must show the booking's own
     * counter's branch instead, which is what the reservation actually sits
     * against regardless of who created it or where they're normally based.
     */
    public function test_booking_list_shows_the_bookings_own_branch_not_the_creators_account_name(): void
    {
        $otherBranch = \App\Models\Branch::create([
            'branch_code' => 'HKT-01', 'branch_name' => 'Oldtown',
            'type' => 'branch', 'city' => 'Phuket', 'is_active' => true,
        ]);
        $otherCounter = \App\Models\Counter::create([
            'counter_code' => 'HKT-C1', 'counter_name' => 'Oldtown Booth',
            'branch_id' => $otherBranch->id, 'is_active' => true,
        ]);

        Booking::create([
            'counter_id' => $otherCounter->id,
            'currency_code' => 'USD',
            'amount' => 100,
            'rate' => 32.95,
            'type' => 'buy',
            'status' => 'pending',
            'hold_amount' => 0,
            'expires_at' => now()->addHour(),
            'created_by' => $this->staffUser->id,
        ]);

        session(['working_counter_id' => $otherCounter->id]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Inventory\BookingManager::class)
            ->assertSee('Oldtown')
            ->assertDontSee($this->staffUser->name);
    }
}
