<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\SanctionScreening;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class SanctionScreeningLogController extends Controller
{
    public function index(Request $request)
    {
        [$dateFrom, $dateTo] = $this->range($request);

        return view('reports.sanction-screening-log', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'result' => $request->input('result', ''),
            'data' => $this->getData($dateFrom, $dateTo, (string) $request->input('result', '')),
        ]);
    }

    public function exportExcel(Request $request)
    {
        [$dateFrom, $dateTo] = $this->range($request);
        $data = $this->getData($dateFrom, $dateTo, (string) $request->input('result', ''));

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Screening Log');

        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->mergeCells('A1:K1');
        $sheet->setCellValue('A1', 'บันทึกการตรวจรายชื่อบุคคลที่ถูกกำหนด (Sanction Screening Log)');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', "วันที่ {$dateFrom} ถึง {$dateTo}");

        $headers = [
            'วันเวลาที่ตรวจ', 'ธุรกรรม', 'สาขา', 'ผู้ตรวจ', 'ชื่อที่ตรวจ', 'เลขเอกสาร',
            'สัญชาติ', 'ผลการตรวจ', 'คะแนนสูงสุด', 'รายชื่อเวอร์ชัน (As Of)', 'การตัดสินใจ',
        ];

        $row = 4;
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, $row], $h);
        }
        $sheet->getStyle("A{$row}:K{$row}")->getFont()->setBold(true);

        $row = 5;
        foreach ($data as $d) {
            $sheet->setCellValue("A{$row}", $d->screened_at?->format('d/m/Y H:i:s'));
            $sheet->setCellValue("B{$row}", $d->transaction?->trns_no ?? '-');
            $sheet->setCellValue("C{$row}", $d->branch?->branch_name ?? '-');
            $sheet->setCellValue("D{$row}", $d->screenedBy?->name ?? 'ระบบ');
            $sheet->setCellValue("E{$row}", $d->input_name ?? '(ไม่มีข้อมูลลูกค้า)');
            $sheet->setCellValue("F{$row}", $d->input_id_number ?? '-');
            $sheet->setCellValue("G{$row}", $d->input_nationality ?? '-');
            $sheet->setCellValue("H{$row}", $this->resultLabel($d->result));
            $sheet->setCellValue("I{$row}", (float) $d->top_score);
            $sheet->setCellValue("J{$row}", $d->syncRun?->source_as_of?->format('Y-m-d') ?? '-');
            $sheet->setCellValue("K{$row}", $d->decision ?? '-');
            $row++;
        }

        $last = max($row - 1, 4);
        $sheet->getStyle("A4:K{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            "SanctionScreeningLog_{$dateFrom}_{$dateTo}.xlsx",
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        );
    }

    /** @return array{0: string, 1: string} */
    private function range(Request $request): array
    {
        return [
            (string) $request->input('date_from', now()->startOfMonth()->format('Y-m-d')),
            (string) $request->input('date_to', now()->format('Y-m-d')),
        ];
    }

    private function getData(string $dateFrom, string $dateTo, string $result)
    {
        $query = SanctionScreening::with(['transaction', 'branch', 'screenedBy', 'syncRun'])
            ->whereDate('screened_at', '>=', $dateFrom)
            ->whereDate('screened_at', '<=', $dateTo);

        if ($result !== '') {
            $query->where('result', $result);
        }

        return $query->orderByDesc('screened_at')->limit(5000)->get();
    }

    private function resultLabel(string $result): string
    {
        return match ($result) {
            SanctionScreening::RESULT_CLEAR => 'ไม่พบ',
            SanctionScreening::RESULT_POTENTIAL_MATCH => 'พบชื่อใกล้เคียง',
            SanctionScreening::RESULT_CONFIRMED_MATCH => 'พบตรงกัน (ระงับ)',
            default => $result,
        };
    }
}
