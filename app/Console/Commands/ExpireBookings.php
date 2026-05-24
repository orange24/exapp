<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\CounterStock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireBookings extends Command
{
    protected $signature = 'bookings:expire';
    protected $description = 'Auto-expire pending bookings that have passed their expiry time and release held stock';

    public function handle(): int
    {
        $expired = Booking::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($expired as $booking) {
            DB::transaction(function () use ($booking) {
                $booking->update(['status' => 'expired']);

                // Release hold for sell bookings
                if ($booking->type === 'sell' && $booking->hold_amount > 0) {
                    CounterStock::where('counter_id', $booking->counter_id)
                        ->where('currency_code', $booking->currency_code)
                        ->decrement('hold_amount', (float) $booking->hold_amount);
                }
            });
            $count++;
        }

        $this->info("Expired {$count} bookings and released held stock.");
        return self::SUCCESS;
    }
}
