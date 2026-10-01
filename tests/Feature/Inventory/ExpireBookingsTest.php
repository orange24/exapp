<?php

namespace Tests\Feature\Inventory;

use App\Models\Booking;
use App\Models\CounterStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ครอบบั๊กที่ hold_amount ค้างถาวรเมื่อลูกค้าจองแล้วไม่มา
 *
 * สต็อกที่ขายได้คำนวณจาก quantity - hold_amount (CounterStock.php:56)
 * และสูตรนี้ถูกใช้ซ้ำที่ BookingManager, BankSaleService, InventoryService,
 * BankSaleManager — hold ที่ค้างจึงลดยอดขายได้ทุกหน้าจอ
 */
class ExpireBookingsTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function stock(float $quantity = 5000, float $hold = 0): CounterStock
    {
        return CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => $quantity,
            'hold_amount' => $hold,
            'avg_cost' => 35,
            'total_cost_value' => $quantity * 35,
        ]);
    }

    private function booking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'amount' => 1000,
            'rate' => 35,
            'type' => 'sell',
            'status' => 'pending',
            'hold_amount' => 1000,
            'expires_at' => now()->subMinute(),
            'created_by' => $this->staffUser->id,
        ], $attributes));
    }

    public function test_expired_sell_booking_releases_held_stock(): void
    {
        $stock = $this->stock(hold: 1000);
        $booking = $this->booking();

        $this->artisan('bookings:expire')->assertExitCode(0);

        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertSame(0.0, (float) $stock->fresh()->hold_amount);
    }

    public function test_available_stock_returns_to_full_after_expiry(): void
    {
        $stock = $this->stock(quantity: 5000, hold: 1000);
        $this->booking();

        $this->assertSame(4000.0, (float) $stock->available);

        $this->artisan('bookings:expire');

        $this->assertSame(5000.0, (float) $stock->fresh()->available);
    }

    public function test_booking_that_has_not_expired_is_left_alone(): void
    {
        $stock = $this->stock(hold: 1000);
        $booking = $this->booking(['expires_at' => now()->addHour()]);

        $this->artisan('bookings:expire');

        $this->assertSame('pending', $booking->fresh()->status);
        $this->assertSame(1000.0, (float) $stock->fresh()->hold_amount);
    }

    public function test_buy_booking_does_not_touch_hold_amount(): void
    {
        $stock = $this->stock(hold: 0);
        $booking = $this->booking(['type' => 'buy', 'hold_amount' => 0]);

        $this->artisan('bookings:expire');

        $this->assertSame('expired', $booking->fresh()->status);
        $this->assertSame(0.0, (float) $stock->fresh()->hold_amount);
    }

    public function test_already_expired_booking_is_not_released_twice(): void
    {
        $stock = $this->stock(hold: 1000);
        $this->booking();

        $this->artisan('bookings:expire');
        $this->artisan('bookings:expire');

        $this->assertSame(
            0.0,
            (float) $stock->fresh()->hold_amount,
            'hold_amount ต้องไม่ติดลบจากการคืนซ้ำ'
        );
    }

    public function test_multiple_expired_bookings_release_all_holds(): void
    {
        $stock = $this->stock(quantity: 10000, hold: 3000);

        $this->booking(['hold_amount' => 1000]);
        $this->booking(['hold_amount' => 1000]);
        $this->booking(['hold_amount' => 1000]);

        $this->artisan('bookings:expire');

        $this->assertSame(0.0, (float) $stock->fresh()->hold_amount);
        $this->assertSame(3, Booking::where('status', 'expired')->count());
    }
}
