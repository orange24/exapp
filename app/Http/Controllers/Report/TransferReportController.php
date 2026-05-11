<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\StockTransfer;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class TransferReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $type = $request->input('type', '');

        $data = $this->getData($dateFrom, $dateTo, $type);
        $counters = Counter::with('branch')->where('is_active', true)->get();

        return view('reports.transfer', compact('dateFrom', 'dateTo', 'type', 'data', 'counters'));
    }

    public function exportExcel(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $type = $request->input('type', '');
        $data = $this->getData($dateFrom, $dateTo, $type);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Transfers');
        foreach (range('A', 'I') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'รายงานโอน/ยืม/คืน (Transfer Report)');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', "วันที่ {$dateFrom} ถึง {$dateTo}");

        $headers = ['เลขที่', 'วันที่', 'ประเภท', 'ต้นทาง', 'ปลายทาง', 'สกุลเงิน', 'ธนบัตร', 'จำนวน', 'ผู้ทำ'];
        $row = 4;
        foreach ($headers as $i => $h) $sheet->setCellValueByColumnAndRow($i + 1, $row, $h);
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);

        $typeLabels = ['borrow' => 'ยืม', 'return' => 'คืน', 'disbursement' => 'เบิกจ่าย', 'intraday_return' => 'คืนระหว่างวัน'];
        $row = 5;
        foreach ($data as $tf) {
            $sheet->setCellValue("A{$row}", $tf->transfer_no);
            $sheet->setCellValue("B{$row}", $tf->transferred_at?->format('d/m/Y H:i'));
            $sheet->setCellValue("C{$row}", $typeLabels[$tf->transfer_type] ?? $tf->transfer_type);
            $sheet->setCellValue("D{$row}", $tf->fromCounter?->counter_name ?? '-');
            $sheet->setCellValue("E{$row}", $tf->toCounter?->counter_name ?? '-');
            $sheet->setCellValue("F{$row}", $tf->currency_code);
            $sheet->setCellValue("G{$row}", $tf->denomination?->denom_label ?? '-');
            $sheet->setCellValue("H{$row}", $tf->amount);
            $sheet->setCellValue("I{$row}", $tf->createdByUser?->name ?? '-');
            $row++;
        }

        $lastRow = max($row - 1, 4);
        $sheet->getStyle("H5:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A4:I{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(fn() => $writer->save('php://output'), "TransferReport_{$dateFrom}_{$dateTo}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getData(string $dateFrom, string $dateTo, string $type)
    {
        $query = StockTransfer::with(['fromCounter', 'toCounter', 'denomination', 'createdByUser'])
            ->whereDate('transferred_at', '>=', $dateFrom)
            ->whereDate('transferred_at', '<=', $dateTo)
            ->orderByDesc('transferred_at');

        if ($type) $query->where('transfer_type', $type);

        return $query->get();
    }
}
