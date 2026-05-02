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

class DailyReportController extends Controller
{
    /**
     * Show the daily report page with date picker and preview.
     */
    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $counterId = session('working_counter_id');

        [$dateStart, $dateEnd] = $this->getDateRange($date);

        $counter = $counterId ? Counter::find($counterId) : null;

        $buying = $this->getSummary('BUYING', $counterId, $dateStart, $dateEnd);
        $selling = $this->getSummary('SELLING', $counterId, $dateStart, $dateEnd);

        return view('reports.daily', compact('date', 'counterId', 'counter', 'buying', 'selling'));
    }

    /**
     * Generate and download Excel file.
     */
    public function exportExcel(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $counterId = session('working_counter_id');

        [$dateStart, $dateEnd] = $this->getDateRange($date);

        $counter = $counterId ? Counter::find($counterId) : null;
        $counterName = $counter?->counter_name ?? '-';

        $buying = $this->getSummary('BUYING', $counterId, $dateStart, $dateEnd);
        $selling = $this->getSummary('SELLING', $counterId, $dateStart, $dateEnd);

        // Build spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(18);

        // Right-align numeric columns
        $sheet->getStyle('B:D')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Row 1: empty
        // Row 2: Title (merged A:D, bold, 15pt)
        $sheet->mergeCells('A2:D2');
        $sheet->setCellValue('A2', 'สรุปยอดการรับซื้ออัตราแลกเปลี่ยนเงินตราต่างประเทศ');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Row 3: empty
        // Row 4: date + counter
        $sheet->setCellValue('A4', 'ยอดประจำวันที่ ' . Carbon::parse($date)->format('d/m/Y'));
        $sheet->setCellValue('D4', $counterName);
        $sheet->getStyle('A4')->getFont()->setBold(true);

        // Row 5: empty

        // --- BUYING SECTION ---
        $row = 6;
        $sheet->mergeCells("B{$row}:C{$row}");
        $sheet->setCellValue("B{$row}", 'ยอดรับซื้อ');
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);
        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row = 7;
        $sheet->setCellValue("A{$row}", 'สกุลเงิน');
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", 'รับซื้อ');
        $sheet->setCellValue("D{$row}", 'ยอดรวม');
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);

        $row = 8;
        $buyingStartRow = $row;
        $buyingTotalThb = 0;

        foreach ($buying as $item) {
            $sheet->setCellValue("A{$row}", $item->currency_name);
            $sheet->setCellValue("B{$row}", $item->total_amount);
            $sheet->setCellValue("C{$row}", $item->unit_price);
            $sheet->setCellValue("D{$row}", $item->total_thb);
            $buyingTotalThb += $item->total_thb;
            $row++;
        }

        // Buying total row
        $sheet->setCellValue("A{$row}", 'รวมยอดรับซื้อ');
        $sheet->setCellValue("D{$row}", $buyingTotalThb);
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $buyingEndRow = $row;

        // Apply borders to buying section (rows 7 to buyingEndRow)
        $this->applyBorders($sheet, "A7:D{$buyingEndRow}");

        // Number formats for buying data rows
        if ($buyingStartRow <= $buyingEndRow) {
            $sheet->getStyle("B{$buyingStartRow}:B{$buyingEndRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$buyingStartRow}:C{$buyingEndRow}")->getNumberFormat()->setFormatCode('#,##0.000000');
            $sheet->getStyle("D{$buyingStartRow}:D{$buyingEndRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // 2 empty rows
        $row += 3;

        // --- SELLING SECTION ---
        $sheet->mergeCells("B{$row}:C{$row}");
        $sheet->setCellValue("B{$row}", 'ยอดนำส่ง');
        $sheet->getStyle("B{$row}")->getFont()->setBold(true);
        $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $row++;
        $headerRow = $row;
        $sheet->setCellValue("A{$row}", 'สกุลเงิน');
        $sheet->setCellValue("B{$row}", '');
        $sheet->setCellValue("C{$row}", 'ขาย');
        $sheet->setCellValue("D{$row}", 'ยอดรวม');
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);

        $row++;
        $sellingStartRow = $row;
        $sellingTotalThb = 0;

        foreach ($selling as $item) {
            $sheet->setCellValue("A{$row}", $item->currency_name);
            $sheet->setCellValue("B{$row}", $item->total_amount);
            $sheet->setCellValue("C{$row}", $item->unit_price);
            $sheet->setCellValue("D{$row}", $item->total_thb);
            $sellingTotalThb += $item->total_thb;
            $row++;
        }

        // Selling total row
        $sheet->setCellValue("A{$row}", 'รวมยอดขาย');
        $sheet->setCellValue("D{$row}", $sellingTotalThb);
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sellingEndRow = $row;

        // Apply borders to selling section
        $this->applyBorders($sheet, "A{$headerRow}:D{$sellingEndRow}");

        // Number formats for selling data rows
        if ($sellingStartRow <= $sellingEndRow) {
            $sheet->getStyle("B{$sellingStartRow}:B{$sellingEndRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$sellingStartRow}:C{$sellingEndRow}")->getNumberFormat()->setFormatCode('#,##0.000000');
            $sheet->getStyle("D{$sellingStartRow}:D{$sellingEndRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // Output to browser
        $filename = "SummaryReport_{$date}.xlsx";
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
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
     * Apply thin borders to a cell range.
     */
    private function applyBorders($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
}
