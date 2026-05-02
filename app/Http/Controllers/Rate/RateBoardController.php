<?php

namespace App\Http\Controllers\Rate;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\CounterRate;
use App\Models\CurrencyDenomination;
use Illuminate\Http\Request;

class RateBoardController extends Controller
{
    /**
     * Public rate board — full-screen display for customers.
     * Route: GET /rate/{counterCode}
     */
    public function show(string $counterCode): \Illuminate\View\View
    {
        $counter = Counter::with('branch')
            ->where('counter_code', $counterCode)
            ->where('is_active', true)
            ->firstOrFail();

        $rates = CounterRate::with(['currency', 'denomination'])
            ->where('counter_id', $counter->id)
            ->whereDate('rate_date', today())
            ->whereNotNull('denomination_id')
            ->join('currency_denominations', 'counter_rates.denomination_id', '=', 'currency_denominations.id')
            ->orderBy('currency_denominations.seq')
            ->select('counter_rates.*')
            ->get();

        // If no rates for today, fall back to latest rates
        if ($rates->isEmpty()) {
            $rates = CounterRate::with(['currency', 'denomination'])
                ->where('counter_id', $counter->id)
                ->whereNotNull('denomination_id')
                ->join('currency_denominations', 'counter_rates.denomination_id', '=', 'currency_denominations.id')
                ->orderBy('currency_denominations.seq')
                ->select('counter_rates.*')
                ->get()
                ->unique('denomination_id')
                ->values();
        }

        $lastUpdate = $rates->max('updated_at')?->format('d/m/Y H:i:s') ?? '—';

        return view('rate.board', compact('counter', 'rates', 'lastUpdate'));
    }
}
