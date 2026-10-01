<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\SanctionScreening;
use App\Models\TransactionMaster;
use Illuminate\Http\Request;

/** ธุรกรรมที่ไม่มีข้อมูลลูกค้าผูกเลย จึงไม่ได้ถูกตรวจ */
class SanctionCoverageGapReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = (string) $request->input('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = (string) $request->input('date_to', now()->format('Y-m-d'));

        $unscreened = $this->unscreenedQuery($dateFrom, $dateTo)
            ->orderByDesc('trns_datetime')
            ->limit(2000)
            ->get();

        $unscreenedTotal = $this->unscreenedQuery($dateFrom, $dateTo)->count();

        $total = TransactionMaster::whereDate('trns_datetime', '>=', $dateFrom)
            ->whereDate('trns_datetime', '<=', $dateTo)
            ->count();

        return view('reports.sanction-coverage-gap', compact(
            'dateFrom', 'dateTo', 'unscreened', 'unscreenedTotal', 'total'
        ));
    }

    /**
     * ธุรกรรมในช่วงที่ไม่มี screening ที่มีชื่อลูกค้าผูกอยู่
     *
     * ใช้ whereNotExists ไม่ใช่ pluck()+whereNotIn เพราะตาราง sanction_screenings
     * โตขึ้นทุกธุรกรรม — การดึง transaction_id ทั้งตารางมาใส่ IN (...) จะล้มในปีถัด ๆ ไป
     */
    private function unscreenedQuery(string $dateFrom, string $dateTo)
    {
        return TransactionMaster::whereDate('trns_datetime', '>=', $dateFrom)
            ->whereDate('trns_datetime', '<=', $dateTo)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')
                ->from('sanction_screenings')
                ->whereColumn('sanction_screenings.transaction_id', 'transactions_master.id')
                ->whereNotNull('sanction_screenings.input_name'));
    }
}
