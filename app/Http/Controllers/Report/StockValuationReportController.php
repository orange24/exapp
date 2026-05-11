<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\CounterStock;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StockValuationReportController extends Controller
{
    public function index(Request $request)
    {
        $counterId = $request->input('counter_id', '');
        $counters = Counter::with('branch')->where('is_active', true)->orderBy('branch_id')->get();
        $data = $this->getData($counterId);

        return view('reports.stock-valuation', compact('counterId', 'counters', 'data'));
    }

    public function exportExcel(Request $request)
    {
        $counterId = $request->input('counter_id', '');
        $data = $this->getData($counterId);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock Valuation');
        foreach (range('A', 'G') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $sheet->mergeCells('A1:G1');
        $sheet->setCellValue('A1', 'รายงานมูลค่าสต็อกคงเหลือ (Stock Valuation)');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', 'ณ วันที่ ' . now()->format('d/m/Y H:i'));

        $headers = ['เคาน์เตอร์', 'สาขา', 'สกุลเงิน', 'ธนบัตร', 'จำนวน', 'ต้นทุนเฉลี่ย', 'มูลค่า (THB)'];
        $row = 4;
        foreach ($headers as $i => $h) $sheet->setCellValueByColumnAndRow($i + 1, $row, $h);
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);

        $row = 5;
        $totalValue = 0;
        foreach ($data as $d) {
            $sheet->setCellValue("A{$row}", $d->counter?->counter_name ?? '-');
            $sheet->setCellValue("B{$row}", $d->counter?->branch?->branch_name ?? '-');
            $sheet->setCellValue("C{$row}", $d->currency_code);
            $sheet->setCellValue("D{$row}", $d->denomination?->denom_label ?? '-');
            $sheet->setCellValue("E{$row}", (float) $d->quantity);
            $sheet->setCellValue("F{$row}", (float) $d->avg_cost);
            $value = (float) $d->quantity * (float) $d->avg_cost;
            $sheet->setCellValue("G{$row}", $value);
            $totalValue += $value;
            $row++;
        }

        $lastRow = max($row - 1, 4);
        $sheet->getStyle("E5:E{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("F5:F{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.0000');
        $sheet->getStyle("G5:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A4:G{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $sheet->setCellValue("A{$row}", 'รวมมูลค่าทั้งหมด');
        $sheet->setCellValue("G{$row}", $totalValue);
        $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
        $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(fn() => $writer->save('php://output'), 'StockValuation_' . now()->format('Ymd') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getData(string $counterId)
    {
        $query = CounterStock::with(['counter.branch', 'denomination', 'currency'])
            ->whereNotNull('denomination_id')
            ->where('quantity', '!=', 0)
            ->orderBy('counter_id')
            ->orderBy('currency_code');

        if ($counterId) $query->where('counter_id', $counterId);

        return $query->get();
    }
}
