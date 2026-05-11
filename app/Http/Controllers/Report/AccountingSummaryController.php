<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\Setting;
use App\Models\TransactionDetail;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AccountingSummaryController extends Controller
{
    /**
     * Show the accounting summary report page (all branches).
     */
    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        [$dateStart, $dateEnd] = $this->getDateRange($date);

        // Get all active counters that have transactions for this date
        $counters = Counter::with('branch')
            ->where('is_active', true)
            ->orderBy('branch_id')
            ->orderBy('counter_name')
            ->get();

        // Build summary per counter
        $counterSummaries = [];
        foreach ($counters as $counter) {
            $buying = $this->getSummary('BUYING', $counter->id, $dateStart, $dateEnd);
            $selling = $this->getSummary('SELLING', $counter->id, $dateStart, $dateEnd);
            $commission = $this->getCommission($counter->id, $dateStart, $dateEnd);

            // Skip counters with no transactions
            if ($buying->isEmpty() && $selling->isEmpty()) {
                continue;
            }

            $counterSummaries[] = [
                'counter' => $counter,
                'buying' => $buying,
                'selling' => $selling,
                'commission' => $commission,
                'buying_total' => $buying->sum('total_thb'),
                'selling_total' => $selling->sum('total_thb'),
                'commission_total' => $commission->sum('commission'),
            ];
        }

        // All-branch combined summary
        $allBuying = $this->getSummary('BUYING', null, $dateStart, $dateEnd);
        $allSelling = $this->getSummary('SELLING', null, $dateStart, $dateEnd);
        $allCommission = $this->getCommission(null, $dateStart, $dateEnd);

        return view('reports.accounting-summary', compact(
            'date', 'counterSummaries',
            'allBuying', 'allSelling', 'allCommission'
        ));
    }

    /**
     * Generate and download Excel file with per-counter sheets + All sheet.
     */
    public function exportExcel(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        [$dateStart, $dateEnd] = $this->getDateRange($date);

        $counters = Counter::with('branch')
            ->where('is_active', true)
            ->orderBy('branch_id')
            ->orderBy('counter_name')
            ->get();

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0); // Remove default sheet
        $sheetIndex = 0;

        // --- Per-counter sheets ---
        foreach ($counters as $counter) {
            $buying = $this->getSummary('BUYING', $counter->id, $dateStart, $dateEnd);
            $selling = $this->getSummary('SELLING', $counter->id, $dateStart, $dateEnd);
            $commission = $this->getCommission($counter->id, $dateStart, $dateEnd);

            // Skip counters with no transactions
            if ($buying->isEmpty() && $selling->isEmpty()) {
                continue;
            }

            $sheet = $spreadsheet->createSheet($sheetIndex);
            $sheetName = str_replace('/', '-', $counter->counter_name);
            $sheet->setTitle(mb_substr($sheetName, 0, 31));

            $this->buildSheet($sheet, $date, $counter->counter_name, $buying, $selling, $commission);
            $sheetIndex++;
        }

        // --- All-branch combined sheet ---
        $allBuying = $this->getSummary('BUYING', null, $dateStart, $dateEnd);
        $allSelling = $this->getSummary('SELLING', null, $dateStart, $dateEnd);
        $allCommission = $this->getCommission(null, $dateStart, $dateEnd);

        if ($allBuying->isNotEmpty() || $allSelling->isNotEmpty()) {
            $sheet = $spreadsheet->createSheet($sheetIndex);
            $sheet->setTitle('All');
            $this->buildSheet($sheet, $date, 'ทุกสาขา (All Branches)', $allBuying, $allSelling, $allCommission);
        }

        // If no data at all, create an empty sheet
        if ($spreadsheet->getSheetCount() === 0) {
            $sheet = $spreadsheet->createSheet(0);
            $sheet->setTitle('All');
            $sheet->setCellValue('A2', 'ไม่มีข้อมูลธุรกรรมสำหรับวันที่ ' . Carbon::parse($date)->format('d/m/Y'));
        }

        $spreadsheet->setActiveSheetIndex(0);

        $filename = "AccountingSummary_{$date}.xlsx";
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Build a single Excel sheet with Buying, Selling, Commission sections.
     */
    private function buildSheet($sheet, string $date, string $counterName, $buying, $selling, $commission): void
    {
        // Column widths
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('E')->setWidth(15);

        // Right-align numeric columns
        $sheet->getStyle('B:E')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Row 2: Title
        $sheet->mergeCells('A2:D2');
        $sheet->setCellValue('A2', 'สรุปยอดการรับซื้ออัตราแลกเปลี่ยนเงินตราต่างประเทศ');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Row 4: Date + Counter
        $sheet->setCellValue('A4', 'ยอดประจำวันที่ ' . Carbon::parse($date)->format('d/m/Y'));
        $sheet->setCellValue('D4', $counterName);
        $sheet->getStyle('A4:D4')->getFont()->setBold(true);

        // --- BUYING SECTION ---
        $row = 6;
        $sheet->setCellValue("A{$row}", 'BUYING');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $headerRow = $row;
        $sheet->setCellValue("A{$row}", 'สกุลเงิน');
        $sheet->setCellValue("B{$row}", 'ยอดรับซื้อ');
        $sheet->setCellValue("C{$row}", 'รับซื้อ');
        $sheet->setCellValue("D{$row}", 'ยอดรวม');
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $row++;

        $dataStartRow = $row;
        $buyingTotal = 0;

        foreach ($buying as $item) {
            $sheet->setCellValue("A{$row}", $item->currency_name);
            $sheet->setCellValue("B{$row}", $item->total_amount);
            $sheet->setCellValue("C{$row}", $item->unit_price);
            $sheet->setCellValue("D{$row}", $item->total_thb);
            $buyingTotal += $item->total_thb;
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'รวมยอดรับซื้อ');
        $sheet->setCellValue("D{$row}", $buyingTotal);
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);

        $this->applyBorders($sheet, "A{$headerRow}:D{$row}");
        if ($dataStartRow <= $row) {
            $sheet->getStyle("B{$dataStartRow}:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$dataStartRow}:C{$row}")->getNumberFormat()->setFormatCode('#,##0.000000');
            $sheet->getStyle("D{$dataStartRow}:D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $row += 3;

        // --- SELLING SECTION ---
        $sheet->setCellValue("A{$row}", 'SELLING');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $headerRow = $row;
        $sheet->setCellValue("A{$row}", 'สกุลเงิน');
        $sheet->setCellValue("B{$row}", 'ยอดนำส่ง');
        $sheet->setCellValue("C{$row}", 'ขาย');
        $sheet->setCellValue("D{$row}", 'ยอดรวม');
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $row++;

        $dataStartRow = $row;
        $sellingTotal = 0;

        foreach ($selling as $item) {
            $sheet->setCellValue("A{$row}", $item->currency_name);
            $sheet->setCellValue("B{$row}", $item->total_amount);
            $sheet->setCellValue("C{$row}", $item->unit_price);
            $sheet->setCellValue("D{$row}", $item->total_thb);
            $sellingTotal += $item->total_thb;
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'รวมยอดขาย');
        $sheet->setCellValue("D{$row}", $sellingTotal);
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);

        $this->applyBorders($sheet, "A{$headerRow}:D{$row}");
        if ($dataStartRow <= $row) {
            $sheet->getStyle("B{$dataStartRow}:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$dataStartRow}:C{$row}")->getNumberFormat()->setFormatCode('#,##0.000000');
            $sheet->getStyle("D{$dataStartRow}:D{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $row += 3;

        // --- COMMISSION SECTION ---
        $sheet->setCellValue("A{$row}", 'Commission');
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        $headerRow = $row;
        $sheet->setCellValue("A{$row}", 'สกุลเงิน');
        $sheet->setCellValue("B{$row}", 'ขาย');
        $sheet->setCellValue("C{$row}", 'เลจปกติ');
        $sheet->setCellValue("D{$row}", 'Discount rate');
        $sheet->setCellValue("E{$row}", 'ค่าคอมฯ');
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $row++;

        $dataStartRow = $row;
        $comTotal = 0;

        foreach ($commission as $item) {
            $sheet->setCellValue("A{$row}", $item->currency_name);
            $sheet->setCellValue("B{$row}", $item->total_amount);
            $sheet->setCellValue("C{$row}", $item->unit_price);
            $sheet->setCellValue("D{$row}", $item->discount_rate_sell);
            $sheet->setCellValue("E{$row}", $item->commission);
            $comTotal += $item->commission;
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'รวมยอดคอมฯ');
        $sheet->setCellValue("E{$row}", $comTotal);
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);

        $this->applyBorders($sheet, "A{$headerRow}:E{$row}");
        if ($dataStartRow <= $row) {
            $sheet->getStyle("B{$dataStartRow}:B{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$dataStartRow}:C{$row}")->getNumberFormat()->setFormatCode('#,##0.000000');
            $sheet->getStyle("D{$dataStartRow}:D{$row}")->getNumberFormat()->setFormatCode('#,##0.000000');
            $sheet->getStyle("E{$dataStartRow}:E{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
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
     * Get transaction summary grouped by currency and rate.
     */
    private function getSummary(string $trnsType, ?int $counterId, string $dateStart, string $dateEnd)
    {
        $query = TransactionDetail::join('transactions_master', 'transactions_detail.transaction_id', '=', 'transactions_master.id')
            ->where('transactions_master.trns_type', $trnsType)
            ->where('transactions_master.flag_cancel', 'N')
            ->where('transactions_master.trns_datetime', '>', $dateStart)
            ->where('transactions_master.trns_datetime', '<=', $dateEnd)
            ->selectRaw('transactions_detail.currency_name, transactions_detail.unit_price, SUM(transactions_detail.amount) as total_amount, SUM(transactions_detail.total) as total_thb')
            ->groupBy('transactions_detail.currency_name', 'transactions_detail.unit_price')
            ->orderBy('transactions_detail.currency_name');

        if ($counterId) {
            $query->where('transactions_master.counter_id', $counterId);
        }

        return $query->get();
    }

    /**
     * Get commission summary for discount booth selling transactions.
     */
    private function getCommission(?int $counterId, string $dateStart, string $dateEnd)
    {
        $query = TransactionDetail::join('transactions_master', 'transactions_detail.transaction_id', '=', 'transactions_master.id')
            ->where('transactions_master.trns_type', 'SELLING')
            ->where('transactions_master.flag_cancel', 'N')
            ->where('transactions_master.is_discount_booth', true)
            ->where('transactions_master.trns_datetime', '>', $dateStart)
            ->where('transactions_master.trns_datetime', '<=', $dateEnd)
            ->selectRaw('
                transactions_detail.currency_name,
                transactions_detail.unit_price,
                transactions_detail.discount_rate_sell,
                SUM(transactions_detail.amount) as total_amount,
                SUM(transactions_detail.total) as total_thb,
                SUM(transactions_detail.total * transactions_detail.unit_price - transactions_detail.total * transactions_detail.discount_rate_sell) as commission
            ')
            ->groupBy('transactions_detail.currency_name', 'transactions_detail.unit_price', 'transactions_detail.discount_rate_sell')
            ->orderBy('transactions_detail.currency_name');

        if ($counterId) {
            $query->where('transactions_master.counter_id', $counterId);
        }

        return $query->get();
    }

    /**
     * Apply thin borders to a cell range.
     */
    private function applyBorders($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
}
