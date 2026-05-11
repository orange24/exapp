<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\Setting;
use App\Models\TransactionDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ProfitLossReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $counterId = $request->input('counter_id', '');

        $counters = Counter::with('branch')->where('is_active', true)->orderBy('branch_id')->get();
        $data = $this->getData($dateFrom, $dateTo, $counterId);

        return view('reports.profit-loss', compact('dateFrom', 'dateTo', 'counterId', 'counters', 'data'));
    }

    public function exportExcel(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $counterId = $request->input('counter_id', '');
        $data = $this->getData($dateFrom, $dateTo, $counterId);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Profit Loss');
        foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'รายงานกำไร-ขาดทุนจากอัตราแลกเปลี่ยน (FX Profit/Loss)');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', "วันที่ {$dateFrom} ถึง {$dateTo}");

        $headers = ['สกุลเงิน', 'ซื้อ (จำนวน)', 'ซื้อ (THB)', 'ขาย (จำนวน)', 'ขาย (THB)', 'ขาย-ซื้อ (จำนวน)', 'กำไร/ขาดทุน (THB)', 'Margin %'];
        $row = 4;
        foreach ($headers as $i => $h) $sheet->setCellValueByColumnAndRow($i + 1, $row, $h);
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);

        $row = 5;
        $totalProfit = 0;
        foreach ($data as $d) {
            $sheet->setCellValue("A{$row}", $d->currency_code);
            $sheet->setCellValue("B{$row}", (float) $d->buy_amount);
            $sheet->setCellValue("C{$row}", (float) $d->buy_thb);
            $sheet->setCellValue("D{$row}", (float) $d->sell_amount);
            $sheet->setCellValue("E{$row}", (float) $d->sell_thb);
            $sheet->setCellValue("F{$row}", (float) $d->sell_amount - (float) $d->buy_amount);
            $profit = (float) $d->sell_thb - (float) $d->buy_thb;
            $sheet->setCellValue("G{$row}", $profit);
            $margin = (float) $d->buy_thb > 0 ? ($profit / (float) $d->buy_thb * 100) : 0;
            $sheet->setCellValue("H{$row}", round($margin, 2));
            $totalProfit += $profit;
            $row++;
        }

        $lastRow = max($row - 1, 4);
        $sheet->getStyle("B5:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("H5:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A4:H{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->setCellValue("A{$row}", 'รวม');
        $sheet->setCellValue("G{$row}", $totalProfit);
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(fn() => $writer->save('php://output'), "ProfitLoss_{$dateFrom}_{$dateTo}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getData(string $dateFrom, string $dateTo, string $counterId)
    {
        $cutoff = Setting::get('WORKING_CUT_OFF', '03:00:00');
        $start = "{$dateFrom} {$cutoff}";
        $end = Carbon::parse($dateTo)->addDay()->format('Y-m-d') . " {$cutoff}";

        return DB::table('transactions_detail')
            ->join('transactions_master', 'transactions_detail.transaction_id', '=', 'transactions_master.id')
            ->where('transactions_master.flag_cancel', 'N')
            ->where('transactions_master.trns_datetime', '>', $start)
            ->where('transactions_master.trns_datetime', '<=', $end)
            ->when($counterId, fn($q) => $q->where('transactions_master.counter_id', $counterId))
            ->selectRaw('
                transactions_detail.currency_code,
                SUM(CASE WHEN trns_type="BUYING" THEN transactions_detail.amount ELSE 0 END) as buy_amount,
                SUM(CASE WHEN trns_type="BUYING" THEN transactions_detail.total ELSE 0 END) as buy_thb,
                SUM(CASE WHEN trns_type="SELLING" THEN transactions_detail.total ELSE 0 END) as sell_amount,
                SUM(CASE WHEN trns_type="SELLING" THEN transactions_detail.amount ELSE 0 END) as sell_thb
            ')
            ->groupBy('transactions_detail.currency_code')
            ->orderBy('transactions_detail.currency_code')
            ->get();
    }
}
