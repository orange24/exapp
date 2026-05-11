<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CashierPerformanceReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));

        $data = $this->getData($dateFrom, $dateTo);

        return view('reports.cashier-performance', compact('dateFrom', 'dateTo', 'data'));
    }

    public function exportExcel(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $data = $this->getData($dateFrom, $dateTo);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cashier Performance');
        foreach (range('A', 'G') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'รายงานผลงานพนักงาน (Cashier Performance)');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', "วันที่ {$dateFrom} ถึง {$dateTo}");

        $headers = ['พนักงาน', 'เคาน์เตอร์', 'ซื้อ (รายการ)', 'ซื้อ (THB)', 'ขาย (รายการ)', 'ขาย (THB)', 'รวม (รายการ)'];
        $row = 4;
        foreach ($headers as $i => $h) $sheet->setCellValueByColumnAndRow($i + 1, $row, $h);
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);

        $row = 5;
        foreach ($data as $d) {
            $sheet->setCellValue("A{$row}", $d->staff_name);
            $sheet->setCellValue("B{$row}", $d->counter_name);
            $sheet->setCellValue("C{$row}", (int) $d->buy_count);
            $sheet->setCellValue("D{$row}", (float) $d->buy_thb);
            $sheet->setCellValue("E{$row}", (int) $d->sell_count);
            $sheet->setCellValue("F{$row}", (float) $d->sell_thb);
            $sheet->setCellValue("G{$row}", (int) $d->buy_count + (int) $d->sell_count);
            $row++;
        }

        $lastRow = max($row - 1, 4);
        $sheet->getStyle("D5:F{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A4:G{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(fn() => $writer->save('php://output'), "CashierPerformance_{$dateFrom}_{$dateTo}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getData(string $dateFrom, string $dateTo)
    {
        $cutoff = Setting::get('WORKING_CUT_OFF', '03:00:00');
        $start = "{$dateFrom} {$cutoff}";
        $end = Carbon::parse($dateTo)->addDay()->format('Y-m-d') . " {$cutoff}";

        return DB::table('transactions_master')
            ->join('users', 'transactions_master.created_by', '=', 'users.id')
            ->where('transactions_master.flag_cancel', 'N')
            ->where('transactions_master.trns_datetime', '>', $start)
            ->where('transactions_master.trns_datetime', '<=', $end)
            ->selectRaw('
                users.name as staff_name,
                transactions_master.counter_name,
                SUM(CASE WHEN trns_type="BUYING" THEN 1 ELSE 0 END) as buy_count,
                SUM(CASE WHEN trns_type="SELLING" THEN 1 ELSE 0 END) as sell_count,
                0 as buy_thb,
                0 as sell_thb
            ')
            ->groupBy('users.name', 'transactions_master.counter_name')
            ->orderBy('users.name')
            ->get()
            ->map(function ($row) use ($start, $end) {
                // Get THB totals per staff (separate query for detail sums)
                $thb = DB::table('transactions_master')
                    ->join('transactions_detail', 'transactions_master.id', '=', 'transactions_detail.transaction_id')
                    ->join('users', 'transactions_master.created_by', '=', 'users.id')
                    ->where('transactions_master.flag_cancel', 'N')
                    ->where('transactions_master.trns_datetime', '>', $start)
                    ->where('transactions_master.trns_datetime', '<=', $end)
                    ->where('users.name', $row->staff_name)
                    ->where('transactions_master.counter_name', $row->counter_name)
                    ->selectRaw('
                        SUM(CASE WHEN trns_type="BUYING" THEN transactions_detail.total ELSE 0 END) as buy_thb,
                        SUM(CASE WHEN trns_type="SELLING" THEN transactions_detail.amount ELSE 0 END) as sell_thb
                    ')
                    ->first();
                $row->buy_thb = (float) ($thb->buy_thb ?? 0);
                $row->sell_thb = (float) ($thb->sell_thb ?? 0);
                return $row;
            });
    }
}
