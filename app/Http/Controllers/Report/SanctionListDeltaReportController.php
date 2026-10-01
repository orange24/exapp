<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\SanctionEntry;
use App\Models\SanctionSyncRun;
use Illuminate\Http\Request;

/** พิสูจน์ว่าเราใช้รายชื่อล่าสุดเสมอ — ผู้ตรวจ ปปง. จะถามเรื่องนี้ */
class SanctionListDeltaReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = (string) $request->input('date_from', now()->subDays(90)->format('Y-m-d'));
        $dateTo = (string) $request->input('date_to', now()->format('Y-m-d'));

        $runs = SanctionSyncRun::whereDate('started_at', '>=', $dateFrom)
            ->whereDate('started_at', '<=', $dateTo)
            ->where(fn ($q) => $q->where('entries_added', '>', 0)
                ->orWhere('entries_removed', '>', 0)
                ->orWhere('entries_updated', '>', 0))
            ->orderByDesc('started_at')
            ->get();

        $recentlyAdded = SanctionEntry::whereDate('first_seen_at', '>=', $dateFrom)
            ->whereDate('first_seen_at', '<=', $dateTo)
            ->orderByDesc('first_seen_at')->limit(200)->get();

        $recentlyRemoved = SanctionEntry::whereNotNull('delisted_at')
            ->whereDate('delisted_at', '>=', $dateFrom)
            ->whereDate('delisted_at', '<=', $dateTo)
            ->orderByDesc('delisted_at')->limit(200)->get();

        return view('reports.sanction-list-delta', compact(
            'dateFrom', 'dateTo', 'runs', 'recentlyAdded', 'recentlyRemoved'
        ));
    }
}
