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

class MySummaryReportController extends Controller
{
    /**
     * Show the "my summary" page — date picker + export, scoped to the
     * logged-in user's own transactions at their current working counter.
     */
    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $counter = $this->workingCounter();

        return view('reports.my-summary', compact('date', 'counter'));
    }

    /**
     * Generate and download Excel file for the logged-in user's own
     * transactions at their current working counter.
     */
    public function exportExcel(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $counter = $this->workingCounter();

        [$dateStart, $dateEnd] = $this->getDateRange($date);

        $buying = $this->getSummary('BUYING', $counter?->id, $dateStart, $dateEnd);
        $selling = $this->getSummary('SELLING', $counter?->id, $dateStart, $dateEnd);

        // Build spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheetName = $counter->counter_name ?? 'Summary';
        $sheetName = str_replace(['/', '\\', '?', '*', '[', ']', ':'], '-', $sheetName);
        $sheet->setTitle(mb_substr($sheetName, 0, 31, 'UTF-8'));

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(25);
        $sheet->getColumnDimension('B')->setWidth(15);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(18);

        // Right-align numeric columns
        $sheet->getStyle('B:D')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Row 2: Title
        $sheet->mergeCells('A2:D2');
        $sheet->setCellValue('A2', 'สรุปยอดการรับซื้ออัตราแลกเปลี่ยนเงินตราต่างประเทศ');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(15);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

        // Row 4: date + counter
        $sheet->setCellValue('A4', 'ยอดประจำวันที่ ' . Carbon::parse($date)->format('d/m/Y'));
        $sheet->setCellValue('D4', $counter->counter_name ?? '-');
        $sheet->getStyle('A4')->getFont()->setBold(true);

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
            $sheet->setCellValue("B{$row}", $item->fc_amount);
            $sheet->setCellValue("C{$row}", $item->unit_price);
            $sheet->setCellValue("D{$row}", $item->thb_amount);
            $buyingTotalThb += $item->thb_amount;
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'รวมยอดรับซื้อ');
        $sheet->setCellValue("D{$row}", $buyingTotalThb);
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $buyingEndRow = $row;

        $this->applyBorders($sheet, "A7:D{$buyingEndRow}");

        if ($buyingStartRow <= $buyingEndRow) {
            $sheet->getStyle("B{$buyingStartRow}:B{$buyingEndRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$buyingStartRow}:C{$buyingEndRow}")->getNumberFormat()->setFormatCode('#,##0.000000');
            $sheet->getStyle("D{$buyingStartRow}:D{$buyingEndRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

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
            $sheet->setCellValue("B{$row}", $item->fc_amount);
            $sheet->setCellValue("C{$row}", $item->unit_price);
            $sheet->setCellValue("D{$row}", $item->thb_amount);
            $sellingTotalThb += $item->thb_amount;
            $row++;
        }

        $sheet->setCellValue("A{$row}", 'รวมยอดขาย');
        $sheet->setCellValue("D{$row}", $sellingTotalThb);
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
        $sellingEndRow = $row;

        $this->applyBorders($sheet, "A{$headerRow}:D{$sellingEndRow}");

        if ($sellingStartRow <= $sellingEndRow) {
            $sheet->getStyle("B{$sellingStartRow}:B{$sellingEndRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C{$sellingStartRow}:C{$sellingEndRow}")->getNumberFormat()->setFormatCode('#,##0.000000');
            $sheet->getStyle("D{$sellingStartRow}:D{$sellingEndRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        $filename = "SummaryReport_{$date}.xlsx";
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function workingCounter(): ?Counter
    {
        $counterId = session('working_counter_id');

        return $counterId ? Counter::find($counterId) : null;
    }

    /**
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
     * Get transaction summary for the logged-in user's own transactions at
     * one counter, grouped by currency and rate.
     *
     * BUYING:  transactions_detail.amount = foreign currency, .total = THB
     * SELLING: transactions_detail.amount = THB, .total = foreign currency
     * — so which column is "fc_amount" vs "thb_amount" flips by $trnsType.
     */
    private function getSummary(string $trnsType, ?int $counterId, string $dateStart, string $dateEnd)
    {
        $fcColumn = $trnsType === 'BUYING' ? 'amount' : 'total';
        $thbColumn = $trnsType === 'BUYING' ? 'total' : 'amount';

        $query = TransactionDetail::join('transactions_master', 'transactions_detail.transaction_id', '=', 'transactions_master.id')
            ->where('transactions_master.trns_type', $trnsType)
            ->where('transactions_master.flag_cancel', 'N')
            ->where('transactions_master.created_by', auth()->id())
            ->where('transactions_master.trns_datetime', '>', $dateStart)
            ->where('transactions_master.trns_datetime', '<=', $dateEnd)
            ->selectRaw("transactions_detail.currency_name, transactions_detail.unit_price, SUM(transactions_detail.{$fcColumn}) as fc_amount, SUM(transactions_detail.{$thbColumn}) as thb_amount")
            ->groupBy('transactions_detail.currency_name', 'transactions_detail.unit_price')
            ->orderBy('transactions_detail.currency_name');

        if ($counterId) {
            $query->where('transactions_master.counter_id', $counterId);
        } else {
            // No working counter selected — a user with no counter has no transactions to show.
            $query->whereRaw('1 = 0');
        }

        return $query->get();
    }

    private function applyBorders($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }
}
