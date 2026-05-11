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

class AvgRateSummaryController extends Controller
{
    /**
     * Show the average rate summary report page.
     */
    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $selectedCounters = $request->input('counters', []);

        $counters = Counter::with('branch')
            ->where('is_active', true)
            ->orderBy('branch_id')
            ->orderBy('counter_name')
            ->get();

        // Default: select all counters
        if (empty($selectedCounters) && !$request->has('counters')) {
            $selectedCounters = $counters->pluck('id')->map(fn($id) => (string) $id)->toArray();
        }

        [$dateStart, $dateEnd] = $this->getDateRange($date);

        $buying = $this->getAvgSummary('BUYING', $selectedCounters, $dateStart, $dateEnd);
        $selling = $this->getAvgSummary('SELLING', $selectedCounters, $dateStart, $dateEnd);
        $summary = $this->getNetSummary($selectedCounters, $dateStart, $dateEnd, $buying);

        return view('reports.avg-rate-summary', compact(
            'date', 'counters', 'selectedCounters',
            'buying', 'selling', 'summary'
        ));
    }

    /**
     * Generate and download Excel file with 3 sheets: Buying, Selling, Summary.
     */
    public function exportExcel(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $selectedCounters = $request->input('counters', []);

        [$dateStart, $dateEnd] = $this->getDateRange($date);

        $buying = $this->getAvgSummary('BUYING', $selectedCounters, $dateStart, $dateEnd);
        $selling = $this->getAvgSummary('SELLING', $selectedCounters, $dateStart, $dateEnd);
        $summary = $this->getNetSummary($selectedCounters, $dateStart, $dateEnd, $buying);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        // Sheet 1: BUYING
        $sheet = $spreadsheet->createSheet(0);
        $sheet->setTitle('BUYING');
        $this->buildBuyingSellingSheet($sheet, $date, 'BUYING', 'ยอดรับซื้อ', 'รับซื้อ', 'รวมยอดรับซื้อ', $buying);

        // Sheet 2: SELLING
        $sheet = $spreadsheet->createSheet(1);
        $sheet->setTitle('SELLING');
        $this->buildBuyingSellingSheet($sheet, $date, 'SELLING', 'ยอดนำส่ง', 'ขาย', 'รวมยอดขาย', $selling);

        // Sheet 3: SUMMARY
        $sheet = $spreadsheet->createSheet(2);
        $sheet->setTitle('SUMMARY');
        $this->buildSummarySheet($sheet, $date, $summary);

        // Open on SUMMARY sheet
        $spreadsheet->setActiveSheetIndex(2);

        $filename = "AvgRateSummary_{$date}.xlsx";
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Build BUYING or SELLING Excel sheet.
     */
    private function buildBuyingSellingSheet($sheet, string $date, string $type, string $amountLabel, string $rateLabel, string $totalLabel, $data): void
    {
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getStyle('B:D')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Title
        $sheet->mergeCells('A2:D2');
        $sheet->setCellValue('A2', 'สรุปยอดการรับซื้ออัตราแลกเปลี่ยนเงินตราต่างประเทศ (Average Rate)');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Date
        $sheet->setCellValue('A4', 'ยอดประจำวันที่ ' . Carbon::parse($date)->format('d/m/Y'));
        $sheet->getStyle('A4')->getFont()->setBold(true);

        // Section header
        $row = 6;
        $sheet->setCellValue("A{$row}", $type);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $headerRow = $row;
        $sheet->setCellValue("A{$row}", 'สกุลเงิน');
        $sheet->setCellValue("B{$row}", $amountLabel);
        $sheet->setCellValue("C{$row}", 'เลท (Avg)');
        $sheet->setCellValue("D{$row}", 'ยอดรวม');
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $row++;

        $dataStartRow = $row;
        $total = 0;

        foreach ($data as $item) {
            $sheet->setCellValue("A{$row}", $item->currency_name);
            $sheet->setCellValue("B{$row}", $item->total_amount);
            $sheet->setCellValue("C{$row}", $item->avg_rate);
            $sheet->setCellValue("D{$row}", $item->total_thb);
            $total += $item->total_thb;
            $row++;
        }

        $sheet->setCellValue("A{$row}", $totalLabel);
        $sheet->setCellValue("D{$row}", $total);
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true)->setSize(15);

        $this->applyBorders($sheet, "A{$headerRow}:D{$row}");
        if ($dataStartRow <= $row) {
            $sheet->getStyle("B{$dataStartRow}:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$dataStartRow}:C{$row}")->getNumberFormat()->setFormatCode('#,##0.000000');
            $sheet->getStyle("D{$dataStartRow}:D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    /**
     * Build SUMMARY Excel sheet (net position per currency).
     */
    private function buildSummarySheet($sheet, string $date, $data): void
    {
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getStyle('B:D')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Title
        $sheet->mergeCells('A2:D2');
        $sheet->setCellValue('A2', 'สรุปยอดรวม (Summary)');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Date
        $sheet->setCellValue('A4', 'ยอดประจำวันที่ ' . Carbon::parse($date)->format('d/m/Y'));
        $sheet->getStyle('A4')->getFont()->setBold(true);

        $row = 6;
        $sheet->setCellValue("A{$row}", 'SUMMARY');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $headerRow = $row;
        $sheet->setCellValue("A{$row}", 'สกุลเงิน');
        $sheet->setCellValue("B{$row}", 'รวมเงินต่างประเทศ');
        $sheet->setCellValue("C{$row}", 'Rate (Buying)');
        $sheet->setCellValue("D{$row}", 'ยอดรวม (THB)');
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $row++;

        $dataStartRow = $row;
        $total = 0;

        foreach ($data as $item) {
            $sheet->setCellValue("A{$row}", $item['currency_name']);
            $sheet->setCellValue("B{$row}", $item['net_amount']);
            $sheet->setCellValue("C{$row}", $item['buying_avg_rate']);
            $sheet->setCellValue("D{$row}", $item['net_thb']);
            $total += $item['net_thb'];
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'รวมยอด');
        $sheet->setCellValue("D{$row}", $total);
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true)->setSize(15);

        $this->applyBorders($sheet, "A{$headerRow}:D{$row}");
        if ($dataStartRow <= $row) {
            $sheet->getStyle("B{$dataStartRow}:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$dataStartRow}:C{$row}")->getNumberFormat()->setFormatCode('#,##0.000000');
            $sheet->getStyle("D{$dataStartRow}:D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }

    /**
     * Get transaction summary with AVG rate, grouped by currency only.
     */
    private function getAvgSummary(string $trnsType, array $counterIds, string $dateStart, string $dateEnd)
    {
        $query = TransactionDetail::join('transactions_master', 'transactions_detail.transaction_id', '=', 'transactions_master.id')
            ->join('currencies', 'transactions_detail.currency_code', '=', 'currencies.currency_code')
            ->where('transactions_master.trns_type', $trnsType)
            ->where('transactions_master.flag_cancel', 'N')
            ->where('transactions_master.trns_datetime', '>', $dateStart)
            ->where('transactions_master.trns_datetime', '<=', $dateEnd)
            ->selectRaw('
                transactions_detail.currency_name,
                currencies.seq,
                AVG(transactions_detail.unit_price) as avg_rate,
                SUM(transactions_detail.amount) as total_amount,
                SUM(transactions_detail.total) as total_thb
            ')
            ->groupBy('transactions_detail.currency_name', 'currencies.seq')
            ->orderBy('currencies.seq')
            ->orderBy('transactions_detail.currency_name');

        if (!empty($counterIds)) {
            $query->whereIn('transactions_master.counter_id', $counterIds);
        }

        return $query->get();
    }

    /**
     * Get net summary per currency (buying amount - selling THB, selling amount - buying THB).
     * Uses buying avg rate for THB calculation — same logic as reference system.
     */
    private function getNetSummary(array $counterIds, string $dateStart, string $dateEnd, $buyingData)
    {
        // Cache buying avg rates
        $buyingRates = [];
        foreach ($buyingData as $item) {
            $buyingRates[$item->currency_name] = $item->avg_rate;
        }

        // Subquery: BUYING gives positive foreign amount, negative THB
        //           SELLING gives negative THB (as amount), positive foreign amount (as total)
        $subQuery = TransactionDetail::join('transactions_master', 'transactions_detail.transaction_id', '=', 'transactions_master.id')
            ->where('transactions_master.flag_cancel', 'N')
            ->where('transactions_master.trns_datetime', '>', $dateStart)
            ->where('transactions_master.trns_datetime', '<=', $dateEnd)
            ->selectRaw("
                transactions_detail.currency_name,
                transactions_detail.currency_code,
                CASE WHEN transactions_master.trns_type = 'BUYING'
                     THEN SUM(transactions_detail.amount)
                     ELSE 0 - SUM(transactions_detail.total) END as amount,
                CASE WHEN transactions_master.trns_type = 'BUYING'
                     THEN 0 - SUM(transactions_detail.total)
                     ELSE SUM(transactions_detail.amount) END as total
            ")
            ->groupBy('transactions_master.trns_type', 'transactions_detail.currency_name', 'transactions_detail.currency_code');

        if (!empty($counterIds)) {
            $subQuery->whereIn('transactions_master.counter_id', $counterIds);
        }

        // Wrap in outer query to net out per currency
        $results = DB::table(DB::raw("({$subQuery->toSql()}) as d"))
            ->mergeBindings($subQuery->getQuery())
            ->join('currencies', 'currencies.currency_code', '=', 'd.currency_code')
            ->selectRaw('d.currency_name, d.currency_code, currencies.seq, SUM(d.amount) as net_amount, SUM(d.total) as net_total')
            ->groupBy('d.currency_name', 'd.currency_code', 'currencies.seq')
            ->orderBy('currencies.seq')
            ->orderBy('d.currency_name')
            ->get();

        // Calculate net THB using cached buying rate
        return $results->map(function ($item) use ($buyingRates) {
            $buyingRate = $buyingRates[$item->currency_name] ?? 0;
            return [
                'currency_name' => $item->currency_name,
                'net_amount' => $item->net_amount,
                'buying_avg_rate' => $buyingRate,
                'net_thb' => $item->net_amount * $buyingRate,
            ];
        });
    }

    /**
     * Calculate the date range using WORKING_CUT_OFF.
     *
     * @return array{0: string, 1: string}
     */
    private function getDateRange(string $date): array
    {
        $cutoff = Setting::get('WORKING_CUT_OFF', '03:00:00');
        $dateStart = "{$date} {$cutoff}";
        $dateEnd = Carbon::parse($date)->addDay()->format('Y-m-d') . " {$cutoff}";

        return [$dateStart, $dateEnd];
    }

    /**
     * Apply thin borders to a cell range.
     */
    private function applyBorders($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
}
