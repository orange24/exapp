<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\SanctionScreening;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * รายงานการอนุมัติ — ใช้จับสาขาที่กดผ่านบ่อยผิดปกติ
 * ซึ่งเป็นสิ่งเดียวที่กัน supervisor override ถูกใช้ในทางที่ผิดได้
 */
class SanctionDecisionReportController extends Controller
{
    public function index(Request $request)
    {
        [$dateFrom, $dateTo] = $this->range($request);

        return view('reports.sanction-decisions', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'data' => $this->getData($dateFrom, $dateTo),
            'byBranch' => $this->summaryByBranch($dateFrom, $dateTo),
        ]);
    }

    public function exportExcel(Request $request)
    {
        [$dateFrom, $dateTo] = $this->range($request);
        $data = $this->getData($dateFrom, $dateTo);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Decisions');

        foreach (range('A', 'I') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $sheet->mergeCells('A1:I1');
        $sheet->setCellValue('A1', 'รายงานการตัดสินใจเมื่อพบชื่อใกล้เคียง');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', "วันที่ {$dateFrom} ถึง {$dateTo}");

        $headers = ['วันเวลาที่ตัดสิน', 'ธุรกรรม', 'สาขา', 'พนักงานผู้ตรวจ', 'ผู้อนุมัติ',
            'ชื่อลูกค้า', 'คะแนน', 'การตัดสินใจ', 'เหตุผล'];

        $row = 4;
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, $row], $h);
        }
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);

        $row = 5;
        foreach ($data as $d) {
            $sheet->setCellValue("A{$row}", $d->decided_at?->format('d/m/Y H:i:s'));
            $sheet->setCellValue("B{$row}", $d->transaction?->trns_no ?? '-');
            $sheet->setCellValue("C{$row}", $d->branch?->branch_name ?? '-');
            $sheet->setCellValue("D{$row}", $d->screenedBy?->name ?? 'ระบบ');
            $sheet->setCellValue("E{$row}", $d->decidedBy?->name ?? '-');
            $sheet->setCellValue("F{$row}", $d->input_name ?? '-');
            $sheet->setCellValue("G{$row}", (float) $d->top_score);
            $sheet->setCellValue("H{$row}", $d->decision);
            $sheet->setCellValue("I{$row}", $d->decision_reason);
            $row++;
        }

        $last = max($row - 1, 4);
        $sheet->getStyle("A4:I{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            fn () => $writer->save('php://output'),
            "SanctionDecisions_{$dateFrom}_{$dateTo}.xlsx",
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

    private function getData(string $dateFrom, string $dateTo)
    {
        return SanctionScreening::with(['transaction', 'branch', 'screenedBy', 'decidedBy'])
            ->whereNotNull('decision')
            ->whereDate('decided_at', '>=', $dateFrom)
            ->whereDate('decided_at', '<=', $dateTo)
            ->orderByDesc('decided_at')
            ->limit(5000)
            ->get();
    }

    private function summaryByBranch(string $dateFrom, string $dateTo)
    {
        return SanctionScreening::with('branch')
            ->whereNotNull('decision')
            ->whereDate('decided_at', '>=', $dateFrom)
            ->whereDate('decided_at', '<=', $dateTo)
            ->get()
            ->groupBy(fn (SanctionScreening $s): string => $s->branch?->branch_name ?? '(ไม่ระบุสาขา)')
            ->map(fn ($group): array => [
                'total' => $group->count(),
                'false_positive' => $group->where('decision', SanctionScreening::DECISION_FALSE_POSITIVE)->count(),
                'true_match' => $group->where('decision', SanctionScreening::DECISION_TRUE_MATCH)->count(),
            ]);
    }
}
