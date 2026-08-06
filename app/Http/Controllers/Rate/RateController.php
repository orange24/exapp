<?php

namespace App\Http\Controllers\Rate;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\CounterRate;
use Illuminate\Http\Request;

class RateController extends Controller
{
    /**
     * List all active counters with their current rate status.
     * Route: GET /admin/rate
     */
    public function index(): \Illuminate\View\View
    {
        $visibleBranchIds = auth()->user()->getVisibleBranchIds();

        $counters = Counter::with(['branch', 'rates' => function ($q) {
            $q->whereDate('rate_date', today());
        }])
        ->where('is_active', true)
        ->whereIn('branch_id', $visibleBranchIds)
        ->get();

        return view('admin.rate.index', compact('counters'));
    }

    /**
     * Rate setup form for a specific counter.
     * Route: GET /admin/rate/{counter}
     */
    public function setup(Counter $counter): \Illuminate\View\View
    {
        // Check if user has access to this counter's branch
        $visibleBranchIds = auth()->user()->getVisibleBranchIds();

        if (!in_array($counter->branch_id, $visibleBranchIds)) {
            abort(403, 'คุณไม่มีสิทธิ์เข้าถึงเคาน์เตอร์นี้');
        }

        return view('admin.rate.setup', compact('counter'));
    }
}
