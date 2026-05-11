<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\BotReportMaster;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class BotMonthlyReportController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', now()->subMonth()->format('m'));
        $year = $request->input('year', now()->subMonth()->format('Y'));
        $branchId = $request->input('branch_id', '');

        $branches = Branch::where('is_active', true)->orderBy('branch_name')->get();

        $settings = [
            'institution_code' => Setting::get('BOT_INSTITUTION_CODE', ''),
            'license_no' => Setting::get('BOT_LICENSE_NO', ''),
            'company_name' => Setting::get('BOT_COMPANY_NAME', Setting::get('COMPANY_NAME', '')),
        ];

        // Preview data
        $dateStart = Carbon::create($year, $month, 1)->startOfMonth();
        $dateEnd = $dateStart->copy()->endOfMonth();

        $buyData = $this->getTransactions('BUYING', $dateStart, $dateEnd, $branchId);
        $sellData = $this->getTransactions('SELLING', $dateStart, $dateEnd, $branchId);

        return view('reports.bot-monthly', compact(
            'month', 'year', 'branchId', 'branches', 'settings', 'buyData', 'sellData'
        ));
    }

    public function exportExcel(Request $request)
    {
        $month = $request->input('month', now()->subMonth()->format('m'));
        $year = $request->input('year', now()->subMonth()->format('Y'));
        $branchId = $request->input('branch_id', '');

        $institutionCode = Setting::get('BOT_INSTITUTION_CODE', '');
        $licenseNo = Setting::get('BOT_LICENSE_NO', '');
        $companyName = Setting::get('BOT_COMPANY_NAME', Setting::get('COMPANY_NAME', ''));

        $dateStart = Carbon::create($year, $month, 1)->startOfMonth();
        $dateEnd = $dateStart->copy()->endOfMonth();
        $dataDate = $dateEnd->format('Y-m-d');

        $branch = $branchId ? Branch::find($branchId) : null;
        $branchName = $branch?->branch_name ?? '';
        $branchAddress = $branch?->address ?? '';

        $buyData = $this->getTransactions('BUYING', $dateStart, $dateEnd, $branchId);
        $sellData = $this->getTransactions('SELLING', $dateStart, $dateEnd, $branchId);

        $spreadsheet = new Spreadsheet();

        // ─── Sheet 1: Provider Info ──────────────────────────
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Provider Info');

        $sheet1->getColumnDimension('A')->setWidth(40);
        $sheet1->getColumnDimension('B')->setWidth(40);

        $sheet1->mergeCells('A1:B1');
        $sheet1->setCellValue('A1', 'รายละเอียดข้อมูลของบุคคลรับอนุญาต');
        $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $providerData = [
            ['รหัสสถาบันผู้ส่งข้อมูล :', $institutionCode],
            ['ชื่อบุคคลรับอนุญาต :', $companyName],
            ['License No :', $licenseNo],
            ['ชื่อสถานประกอบการ หรือ ชื่อสาขา :', $branchName],
            ['เลขที่ หรือรหัสพื้นที่ของสถานประกอบการ :', $branchAddress],
            ['ประจำงวด (เดือน) :', $this->getThaiMonth((int) $month)],
            ['ประจำงวด (ปี) :', $year],
            ['วันที่ชุดข้อมูล (YYYY-MM-DD) :', $dataDate],
        ];

        $row = 3;
        foreach ($providerData as $item) {
            $sheet1->setCellValue("A{$row}", $item[0]);
            $sheet1->setCellValue("B{$row}", $item[1]);
            $sheet1->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $sheet1->getStyle('A3:B10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // ─── Sheet 2: Buy FX ─────────────────────────────────
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Buy FX');
        $this->buildTransactionSheet($sheet2, 'BUY', $buyData, $institutionCode, $companyName, $licenseNo, $branchName, $branchAddress, $month, $year, $dataDate);

        // ─── Sheet 3: Sell FX ────────────────────────────────
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Sell FX');
        $this->buildTransactionSheet($sheet3, 'SELL', $sellData, $institutionCode, $companyName, $licenseNo, $branchName, $branchAddress, $month, $year, $dataDate);

        $spreadsheet->setActiveSheetIndex(0);

        // File naming per BOT standard: MMCSMC{LicenseNo}_{YYYYMMDD}_EMC.xlsx
        $fileDate = $dateEnd->format('Ymd');
        $filename = "MMCSMC{$licenseNo}_{$fileDate}_EMC.xlsx";

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(fn() => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Export Excel from staging data (bot_report_transactions).
     */
    public function exportFromStaging(BotReportMaster $report)
    {
        $buyData = $report->buyTransactions()->orderBy('trns_date')->get();
        $sellData = $report->sellTransactions()->orderBy('trns_date')->get();

        $spreadsheet = new Spreadsheet();

        // Sheet 1: Provider Info
        $sheet1 = $spreadsheet->getActiveSheet();
        $sheet1->setTitle('Provider Info');
        $sheet1->getColumnDimension('A')->setWidth(40);
        $sheet1->getColumnDimension('B')->setWidth(40);
        $sheet1->mergeCells('A1:B1');
        $sheet1->setCellValue('A1', 'รายละเอียดข้อมูลของบุคคลรับอนุญาต');
        $sheet1->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $month = (int) substr($report->report_month, 5, 2);
        $year = substr($report->report_month, 0, 4);
        $dataDate = Carbon::create($year, $month, 1)->endOfMonth()->format('Y-m-d');

        $info = [
            ['รหัสสถาบันผู้ส่งข้อมูล :', $report->institution_code],
            ['ชื่อบุคคลรับอนุญาต :', $report->company_name],
            ['License No :', $report->license_no],
            ['ชื่อสถานประกอบการ หรือ ชื่อสาขา :', $report->branch_name],
            ['เลขที่ หรือรหัสพื้นที่ของสถานประกอบการ :', $report->branch_address],
            ['ประจำงวด (เดือน) :', $this->getThaiMonth($month)],
            ['ประจำงวด (ปี) :', $year],
            ['วันที่ชุดข้อมูล (YYYY-MM-DD) :', $dataDate],
        ];
        $row = 3;
        foreach ($info as $item) {
            $sheet1->setCellValue("A{$row}", $item[0]);
            $sheet1->setCellValue("B{$row}", $item[1]);
            $sheet1->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }
        $sheet1->getStyle('A3:B10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Sheet 2: Buy FX
        $sheet2 = $spreadsheet->createSheet();
        $sheet2->setTitle('Buy FX');
        $this->buildStagingSheet($sheet2, 'BUY', $buyData, $report, $month, $year, $dataDate);

        // Sheet 3: Sell FX
        $sheet3 = $spreadsheet->createSheet();
        $sheet3->setTitle('Sell FX');
        $this->buildStagingSheet($sheet3, 'SELL', $sellData, $report, $month, $year, $dataDate);

        $spreadsheet->setActiveSheetIndex(0);

        $fileDate = Carbon::create($year, $month, 1)->endOfMonth()->format('Ymd');
        $filename = "MMCSMC{$report->license_no}_{$fileDate}_EMC.xlsx";

        if ($report->status === 'confirmed') {
            $report->update(['status' => 'exported']);
        }

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(fn() => $writer->save('php://output'), $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function buildStagingSheet($sheet, string $type, $data, BotReportMaster $report, int $month, string $year, string $dataDate): void
    {
        foreach (range('A', 'P') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $sheet->setCellValue('A1', 'รหัสสถาบันผู้ส่งข้อมูล');
        $sheet->setCellValue('B1', $report->institution_code);
        $sheet->setCellValue('D1', 'วันที่ชุดข้อมูล :');
        $sheet->setCellValue('E1', $dataDate);
        $sheet->setCellValue('A2', 'ชื่อบุคคลรับอนุญาต');
        $sheet->setCellValue('B2', $report->company_name);
        $sheet->setCellValue('D2', 'ชื่อสถานประกอบการ หรือ ชื่อสาขา :');
        $sheet->setCellValue('F2', $report->branch_name);
        $sheet->setCellValue('A3', 'License No :');
        $sheet->setCellValue('B3', $report->license_no);
        $sheet->setCellValue('D3', 'เลขที่ หรือรหัสพื้นที่ :');
        $sheet->setCellValue('F3', $report->branch_address);
        $sheet->setCellValue('A4', 'ประจำงวด (เดือน) :');
        $sheet->setCellValue('B4', $month);
        $sheet->setCellValue('D4', 'ประจำงวด (ปี) :');
        $sheet->setCellValue('E4', $year);
        $sheet->getStyle('A1:A4')->getFont()->setBold(true);
        $sheet->getStyle('D1:D4')->getFont()->setBold(true);

        $isBuy = $type === 'BUY';
        $titleRow = 6;
        $title = $isBuy ? 'รายงานการซื้อเงินตราต่างประเทศของบุคคลรับอนุญาต' : 'รายงานการขายเงินตราต่างประเทศของบุคคลรับอนุญาต';
        $sheet->mergeCells("A{$titleRow}:P{$titleRow}");
        $sheet->setCellValue("A{$titleRow}", $title);
        $sheet->getStyle("A{$titleRow}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$titleRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$titleRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($isBuy ? 'C6EFCE' : 'FFF2CC');

        $headerRow = 8;
        $headers = ['วันที่เกิดธุรกรรม', 'ประเภทของลูกค้า', 'ชื่อลูกค้า', 'ประเภทรหัสลูกค้า', 'รหัสลูกค้า', 'สัญชาติ', 'วัตถุประสงค์',
            $isBuy ? 'จุดซื้อ' : 'จุดขาย', $isBuy ? 'ช่องทางซื้อเงินตรา' : 'ช่องทางขายเงินตรา',
            'รหัสสกุลเงิน', 'อัตราแลกเปลี่ยน', $isBuy ? 'จำนวนเงินซื้อ (FX)' : 'จำนวนเงินขาย (FX)',
            $isBuy ? 'จุดจ่าย' : 'จุดรับ', $isBuy ? 'ช่องทางจ่าย' : 'ช่องทางรับ', 'จำนวนเงิน (บาท)', 'หมายเหตุ'];
        foreach ($headers as $i => $h) $sheet->setCellValueByColumnAndRow($i + 1, $headerRow, $h);
        $sheet->getStyle("A{$headerRow}:P{$headerRow}")->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle("A{$headerRow}:P{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->getStyle("A{$headerRow}:P{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('D9E2F3');

        $row = $headerRow + 1;
        foreach ($data as $tx) {
            $sheet->setCellValue("A{$row}", $tx->trns_date->format('d'));
            $sheet->setCellValue("B{$row}", $tx->customer_type);
            $sheet->setCellValue("C{$row}", $tx->customer_name);
            $sheet->setCellValue("D{$row}", $tx->id_type_code);
            $sheet->setCellValue("E{$row}", $tx->id_number);
            $sheet->setCellValue("F{$row}", $tx->nationality);
            $sheet->setCellValue("G{$row}", $tx->purpose);
            $sheet->setCellValue("H{$row}", $tx->fx_point);
            $sheet->setCellValue("I{$row}", $tx->fx_channel);
            $sheet->setCellValue("J{$row}", $tx->currency_code);
            $sheet->setCellValue("K{$row}", (float) $tx->exchange_rate);
            $sheet->setCellValue("L{$row}", (float) $tx->fx_amount);
            $sheet->setCellValue("M{$row}", $tx->thb_point);
            $sheet->setCellValue("N{$row}", $tx->thb_channel);
            $sheet->setCellValue("O{$row}", (float) $tx->thb_amount);
            $sheet->setCellValue("P{$row}", $tx->remark ?? '');
            $row++;
        }
        $lastRow = max($row - 1, $headerRow);
        if ($row > $headerRow + 1) {
            $sheet->getStyle("K" . ($headerRow + 1) . ":K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00000000');
            $sheet->getStyle("L" . ($headerRow + 1) . ":L{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("O" . ($headerRow + 1) . ":O{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $sheet->getStyle("A{$headerRow}:P{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    public function updateSettings(Request $request)
    {
        $request->validate([
            'institution_code' => 'required|string|size:13',
            'license_no' => 'required|string|max:20',
            'company_name' => 'required|string|max:255',
        ]);

        Setting::set('BOT_INSTITUTION_CODE', $request->institution_code);
        Setting::set('BOT_LICENSE_NO', $request->license_no);
        Setting::set('BOT_COMPANY_NAME', $request->company_name);

        return back()->with('success', 'บันทึกข้อมูลสำเร็จ');
    }

    private function buildTransactionSheet($sheet, string $type, $data, string $institutionCode, string $companyName, string $licenseNo, string $branchName, string $branchAddress, string $month, string $year, string $dataDate): void
    {
        // Auto-size columns
        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Header area (Provider Info repeated)
        $sheet->setCellValue('A1', 'รหัสสถาบันผู้ส่งข้อมูล');
        $sheet->setCellValue('B1', $institutionCode);
        $sheet->setCellValue('D1', 'วันที่ชุดข้อมูล :');
        $sheet->setCellValue('E1', $dataDate);

        $sheet->setCellValue('A2', 'ชื่อบุคคลรับอนุญาต');
        $sheet->setCellValue('B2', $companyName);
        $sheet->setCellValue('D2', 'ชื่อสถานประกอบการ หรือ ชื่อสาขา :');
        $sheet->setCellValue('F2', $branchName);

        $sheet->setCellValue('A3', 'License No :');
        $sheet->setCellValue('B3', $licenseNo);
        $sheet->setCellValue('D3', 'เลขที่ หรือรหัสพื้นที่ของสถานประกอบการ :');
        $sheet->setCellValue('F3', $branchAddress);

        $sheet->setCellValue('A4', 'ประจำงวด (เดือน) :');
        $sheet->setCellValue('B4', (int) $month);
        $sheet->setCellValue('D4', 'ประจำงวด (ปี) :');
        $sheet->setCellValue('E4', $year);

        $sheet->getStyle('A1:A4')->getFont()->setBold(true);
        $sheet->getStyle('D1:D4')->getFont()->setBold(true);

        // Title
        $titleRow = 6;
        if ($type === 'BUY') {
            $title = 'รายงานการซื้อเงินตราต่างประเทศของบุคคลรับอนุญาต';
        } else {
            $title = 'รายงานการขายเงินตราต่างประเทศของบุคคลรับอนุญาต';
        }
        $sheet->mergeCells("A{$titleRow}:P{$titleRow}");
        $sheet->setCellValue("A{$titleRow}", $title);
        $sheet->getStyle("A{$titleRow}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$titleRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$titleRow}")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB($type === 'BUY' ? 'C6EFCE' : 'FFF2CC');

        // Column headers
        $headerRow = 8;
        $isBuy = $type === 'BUY';
        $headers = [
            'A' => 'วันที่เกิดธุรกรรม',
            'B' => 'ประเภทของลูกค้า',
            'C' => 'ชื่อลูกค้า',
            'D' => 'ประเภทรหัสลูกค้า',
            'E' => 'รหัสลูกค้า',
            'F' => 'สัญชาติ',
            'G' => 'วัตถุประสงค์',
            'H' => $isBuy ? 'จุดซื้อ' : 'จุดขาย',
            'I' => $isBuy ? 'ช่องทางซื้อเงินตรา' : 'ช่องทางขายเงินตรา',
            'J' => 'รหัสสกุลเงิน',
            'K' => 'อัตราแลกเปลี่ยน',
            'L' => $isBuy ? 'จำนวนเงินซื้อ (ตามสกุลเงินตราต่างประเทศ)' : 'จำนวนเงินขาย (ตามสกุลเงินตราต่างประเทศ)',
            'M' => $isBuy ? 'จุดจ่าย' : 'จุดรับ',
            'N' => $isBuy ? 'ช่องทางจ่าย' : 'ช่องทางรับ',
            'O' => 'จำนวนเงิน (บาท)',
            'P' => 'หมายเหตุ',
        ];

        foreach ($headers as $col => $label) {
            $sheet->setCellValue("{$col}{$headerRow}", $label);
        }
        $sheet->getStyle("A{$headerRow}:P{$headerRow}")->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle("A{$headerRow}:P{$headerRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->getStyle("A{$headerRow}:P{$headerRow}")->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('D9E2F3');

        // Data rows
        $row = $headerRow + 1;
        foreach ($data as $tx) {
            $sheet->setCellValue("A{$row}", Carbon::parse($tx->trns_date)->format('d'));
            $sheet->setCellValue("B{$row}", $this->mapCustomerType($tx->id_type));
            $sheet->setCellValue("C{$row}", $tx->cust_name ?? '');
            $sheet->setCellValue("D{$row}", $this->mapIdTypeCode($tx->id_type));
            $sheet->setCellValue("E{$row}", $tx->id_number ?? '');
            $sheet->setCellValue("F{$row}", $tx->nationality ?? '');
            $sheet->setCellValue("G{$row}", 'เดินทาง/ท่องเที่ยว');
            $sheet->setCellValue("H{$row}", 'สถานประกอบการ');
            $sheet->setCellValue("I{$row}", '0753600001');  // เงินสด
            $sheet->setCellValue("J{$row}", $tx->currency_code);
            $sheet->setCellValue("K{$row}", (float) $tx->unit_price);
            $sheet->setCellValue("L{$row}", (float) $tx->amount);
            $sheet->setCellValue("M{$row}", 'สถานประกอบการ');
            $sheet->setCellValue("N{$row}", '0753600001');  // เงินสด
            $sheet->setCellValue("O{$row}", (float) $tx->total);
            $sheet->setCellValue("P{$row}", '');
            $row++;
        }

        $lastRow = max($row - 1, $headerRow);

        // Number formats
        if ($row > $headerRow + 1) {
            $sheet->getStyle("K" . ($headerRow + 1) . ":K{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00000000');
            $sheet->getStyle("L" . ($headerRow + 1) . ":L{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("O" . ($headerRow + 1) . ":O{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // Borders
        $sheet->getStyle("A{$headerRow}:P{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function getTransactions(string $trnsType, Carbon $dateStart, Carbon $dateEnd, string $branchId)
    {
        $query = DB::table('transactions_master')
            ->join('transactions_detail', 'transactions_master.id', '=', 'transactions_detail.transaction_id')
            ->leftJoin('customers', 'transactions_master.customer_id', '=', 'customers.id')
            ->leftJoin('counters', 'transactions_master.counter_id', '=', 'counters.id')
            ->where('transactions_master.trns_type', $trnsType)
            ->where('transactions_master.flag_cancel', 'N')
            ->whereDate('transactions_master.trns_datetime', '>=', $dateStart)
            ->whereDate('transactions_master.trns_datetime', '<=', $dateEnd)
            ->select(
                DB::raw('DATE(transactions_master.trns_datetime) as trns_date'),
                'transactions_master.cust_name',
                'customers.id_type',
                'customers.id_number',
                'customers.nationality',
                'transactions_detail.currency_code',
                'transactions_detail.unit_price',
                'transactions_detail.amount',
                'transactions_detail.total',
                'counters.branch_id'
            )
            ->orderBy('transactions_master.trns_datetime');

        if ($branchId) {
            $query->where('counters.branch_id', $branchId);
        }

        return $query->get();
    }

    private function mapCustomerType(?string $idType): string
    {
        return match ($idType) {
            'national_id' => 'คนไทย',
            'passport' => 'ชาวต่างชาติ',
            'corporate_id' => 'นิติบุคคลไทย',
            default => 'ชาวต่างชาติ',
        };
    }

    private function mapIdTypeCode(?string $idType): string
    {
        return match ($idType) {
            'national_id' => '324001',
            'passport' => '324002',
            'corporate_id' => '324004',
            default => '324002',
        };
    }

    private function getThaiMonth(int $month): string
    {
        $months = [
            1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
            5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
            9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
        ];
        return $months[$month] ?? '';
    }
}
