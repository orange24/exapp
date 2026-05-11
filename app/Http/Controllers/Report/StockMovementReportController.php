<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\CounterStock;
use App\Models\Setting;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class StockMovementReportController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $counterId = $request->input('counter_id', session('working_counter_id'));

        $counters = Counter::with('branch')->where('is_active', true)->orderBy('branch_id')->get();
        $counter = $counterId ? Counter::find($counterId) : null;
        $data = $counterId ? $this->getData($counterId, $date) : collect();

        return view('reports.stock-movement', compact('date', 'counterId', 'counters', 'counter', 'data'));
    }

    public function exportExcel(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));
        $counterId = $request->input('counter_id', session('working_counter_id'));
        $counter = $counterId ? Counter::find($counterId) : null;
        $data = $counterId ? $this->getData($counterId, $date) : collect();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Stock Movement');

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'รายงานสรุปเคลื่อนไหวสต็อกรายวัน (Daily Stock Movement)');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sheet->setCellValue('A2', 'วันที่: ' . Carbon::parse($date)->format('d/m/Y'));
        $sheet->setCellValue('D2', 'เคาน์เตอร์: ' . ($counter?->counter_name ?? '-'));

        $headers = ['สกุลเงิน', 'ธนบัตร', 'ยอดยกมา', 'ซื้อเข้า', 'ขายออก', 'โอนเข้า', 'โอนออก', 'ปรับปรุง', 'คงเหลือ'];
        $row = 4;
        foreach ($headers as $i => $h) {
            $sheet->setCellValueByColumnAndRow($i + 1, $row, $h);
        }
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("C{$row}:I{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        $row = 5;
        foreach ($data as $d) {
            $sheet->setCellValue("A{$row}", $d['currency_code']);
            $sheet->setCellValue("B{$row}", $d['denom_label']);
            $sheet->setCellValue("C{$row}", $d['opening']);
            $sheet->setCellValue("D{$row}", $d['bought']);
            $sheet->setCellValue("E{$row}", $d['sold']);
            $sheet->setCellValue("F{$row}", $d['tfr_in']);
            $sheet->setCellValue("G{$row}", $d['tfr_out']);
            $sheet->setCellValue("H{$row}", $d['adjust']);
            $sheet->setCellValue("I{$row}", $d['remaining']);
            $row++;
        }

        $lastRow = $row - 1;
        if ($lastRow >= 5) {
            $sheet->getStyle("C5:I{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("C5:I{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }
        $sheet->getStyle("A4:I{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Total row
        $sheet->setCellValue("A{$row}", 'รวม');
        $sheet->setCellValue("C{$row}", $data->sum('opening'));
        $sheet->setCellValue("D{$row}", $data->sum('bought'));
        $sheet->setCellValue("E{$row}", $data->sum('sold'));
        $sheet->setCellValue("F{$row}", $data->sum('tfr_in'));
        $sheet->setCellValue("G{$row}", $data->sum('tfr_out'));
        $sheet->setCellValue("H{$row}", $data->sum('adjust'));
        $sheet->setCellValue("I{$row}", $data->sum('remaining'));
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
        $sheet->getStyle("C{$row}:I{$row}")->getNumberFormat()->setFormatCode('#,##0.00');

        $filename = "StockMovement_{$date}.xlsx";
        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(fn() => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getData(int $counterId, string $date)
    {
        $cutoff = Setting::get('WORKING_CUT_OFF', '03:00:00');
        $dateStart = "{$date} {$cutoff}";
        $dateEnd = Carbon::parse($date)->addDay()->format('Y-m-d') . " {$cutoff}";

        $stocks = CounterStock::where('counter_id', $counterId)
            ->whereNotNull('denomination_id')
            ->with(['currency', 'denomination'])->get();

        $movements = StockMovement::where('counter_id', $counterId)
            ->whereNotNull('denomination_id')
            ->where('moved_at', '>', $dateStart)->where('moved_at', '<=', $dateEnd)
            ->selectRaw('denomination_id, currency_code,
                SUM(CASE WHEN movement_type="buy" THEN amount ELSE 0 END) as bought,
                SUM(CASE WHEN movement_type="sell" THEN ABS(amount) ELSE 0 END) as sold,
                SUM(CASE WHEN movement_type="transfer_in" THEN amount ELSE 0 END) as tfr_in,
                SUM(CASE WHEN movement_type="transfer_out" THEN ABS(amount) ELSE 0 END) as tfr_out,
                SUM(CASE WHEN movement_type="adjustment" THEN amount ELSE 0 END) as adjust')
            ->groupBy('denomination_id', 'currency_code')->get()->keyBy('denomination_id');

        return $stocks->map(function ($stock) use ($movements) {
            $mv = $movements->get($stock->denomination_id);
            $bought = $mv ? (float) $mv->bought : 0;
            $sold = $mv ? (float) $mv->sold : 0;
            $tfrIn = $mv ? (float) $mv->tfr_in : 0;
            $tfrOut = $mv ? (float) $mv->tfr_out : 0;
            $adjust = $mv ? (float) $mv->adjust : 0;
            $current = (float) $stock->quantity;
            return [
                'currency_code' => $stock->currency_code,
                'denom_label' => $stock->denomination?->denom_label ?? '-',
                'currency_seq' => $stock->currency?->seq ?? 999,
                'denom_seq' => $stock->denomination?->seq ?? 0,
                'opening' => $current - $bought + $sold - $tfrIn + $tfrOut - $adjust,
                'bought' => $bought, 'sold' => $sold,
                'tfr_in' => $tfrIn, 'tfr_out' => $tfrOut,
                'adjust' => $adjust, 'remaining' => $current,
            ];
        })->sortBy(['currency_seq', 'denom_seq'])->values();
    }
}
