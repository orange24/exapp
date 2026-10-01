<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\SanctionScreening;
use Illuminate\Http\Request;

/** ลูกค้าเก่าที่กลายเป็นชื่อต้องห้ามหลังรายชื่ออัปเดต */
class SanctionRescanReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = (string) $request->input('date_from', now()->subDays(30)->format('Y-m-d'));
        $dateTo = (string) $request->input('date_to', now()->format('Y-m-d'));

        $data = SanctionScreening::with(['customer', 'matches.entry', 'decidedBy'])
            ->where('trigger', SanctionScreening::TRIGGER_RESCAN)
            ->where('result', '!=', SanctionScreening::RESULT_CLEAR)
            ->whereDate('screened_at', '>=', $dateFrom)
            ->whereDate('screened_at', '<=', $dateTo)
            ->orderByDesc('top_score')
            ->limit(2000)
            ->get();

        return view('reports.sanction-rescan', compact('dateFrom', 'dateTo', 'data'));
    }
}
