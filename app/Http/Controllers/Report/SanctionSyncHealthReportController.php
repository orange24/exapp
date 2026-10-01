<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\SanctionEntry;
use App\Models\SanctionSyncRun;

/** พิสูจน์ว่าไม่มีช่วงที่ระบบตรวจตายเงียบ */
class SanctionSyncHealthReportController extends Controller
{
    public function index()
    {
        $lists = array_keys((array) config('sanction.amlo.lists'));

        $status = [];

        foreach ($lists as $listCode) {
            $lastSuccess = SanctionSyncRun::where('list_code', $listCode)
                ->where('status', SanctionSyncRun::STATUS_SUCCESS)
                ->latest('finished_at')->first();

            $lastAny = SanctionSyncRun::where('list_code', $listCode)->latest('started_at')->first();

            $status[$listCode] = [
                'active_entries' => SanctionEntry::where('list_code', $listCode)->active()->count(),
                'last_success_at' => $lastSuccess?->finished_at,
                'source_as_of' => $lastSuccess?->source_as_of,
                'hours_since_success' => $lastSuccess?->finished_at
                    ? (int) $lastSuccess->finished_at->diffInHours(now())
                    : null,
                'last_status' => $lastAny?->status,
                'last_error' => $lastAny?->error_message,
            ];
        }

        $recentRuns = SanctionSyncRun::latest('started_at')->limit(50)->get();

        return view('reports.sanction-sync-health', compact('status', 'recentRuns'));
    }
}
