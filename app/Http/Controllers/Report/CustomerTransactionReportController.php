<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\TransactionMaster;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class CustomerTransactionReportController extends Controller
{
    public function index(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $search = $request->input('search', '');

        $data = $this->getData($dateFrom, $dateTo, $search);

        return view('reports.customer-transaction', compact('dateFrom', 'dateTo', 'search', 'data'));
    }

    public function exportExcel(Request $request)
    {
        $dateFrom = $request->input('date_from', now()->startOfMonth()->format('Y-m-d'));
        $dateTo = $request->input('date_to', now()->format('Y-m-d'));
        $search = $request->input('search', '');
        $data = $this->getData($dateFrom, $dateTo, $search);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Customer Transactions');
        foreach (range('A', 'H') as $col) $sheet->getColumnDimension($col)->setAutoSize(true);

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'รายงานธุรกรรมลูกค้า (Customer Transaction Report)');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->setCellValue('A2', "วันที่ {$dateFrom} ถึง {$dateTo}");

        $headers = ['ลูกค้า', 'เอกสาร', 'สัญชาติ', 'ซื้อ (ครั้ง)', 'ซื้อ (THB)', 'ขาย (ครั้ง)', 'ขาย (THB)', 'รวม (ครั้ง)'];
        $row = 4;
        foreach ($headers as $i => $h) $sheet->setCellValueByColumnAndRow($i + 1, $row, $h);
        $sheet->getStyle("A{$row}:H{$row}")->getFont()->setBold(true);

        $row = 5;
        foreach ($data as $d) {
            $sheet->setCellValue("A{$row}", $d->cust_name);
            $sheet->setCellValue("B{$row}", $d->id_number ?? '-');
            $sheet->setCellValue("C{$row}", $d->nationality ?? '-');
            $sheet->setCellValue("D{$row}", (int) $d->buy_count);
            $sheet->setCellValue("E{$row}", (float) $d->buy_thb);
            $sheet->setCellValue("F{$row}", (int) $d->sell_count);
            $sheet->setCellValue("G{$row}", (float) $d->sell_thb);
            $sheet->setCellValue("H{$row}", (int) $d->buy_count + (int) $d->sell_count);
            $row++;
        }

        $lastRow = max($row - 1, 4);
        $sheet->getStyle("E5:G{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle("A4:H{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        $writer = new Xlsx($spreadsheet);
        return response()->streamDownload(fn() => $writer->save('php://output'), "CustomerTransaction_{$dateFrom}_{$dateTo}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function getData(string $dateFrom, string $dateTo, string $search)
    {
        $cutoff = Setting::get('WORKING_CUT_OFF', '03:00:00');
        $start = "{$dateFrom} {$cutoff}";
        $end = Carbon::parse($dateTo)->addDay()->format('Y-m-d') . " {$cutoff}";

        $query = DB::table('transactions_master')
            ->leftJoin('customers', 'transactions_master.customer_id', '=', 'customers.id')
            ->where('transactions_master.flag_cancel', 'N')
            ->where('transactions_master.trns_datetime', '>', $start)
            ->where('transactions_master.trns_datetime', '<=', $end)
            ->whereNotNull('transactions_master.cust_name')
            ->where('transactions_master.cust_name', '!=', '');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('transactions_master.cust_name', 'like', "%{$search}%")
                  ->orWhere('customers.id_number', 'like', "%{$search}%")
                  ->orWhere('customers.nationality', 'like', "%{$search}%");
            });
        }

        return $query->selectRaw('
                transactions_master.cust_name,
                customers.id_number,
                customers.nationality,
                SUM(CASE WHEN trns_type="BUYING" THEN 1 ELSE 0 END) as buy_count,
                SUM(CASE WHEN trns_type="SELLING" THEN 1 ELSE 0 END) as sell_count,
                0 as buy_thb, 0 as sell_thb
            ')
            ->groupBy('transactions_master.cust_name', 'customers.id_number', 'customers.nationality')
            ->orderByRaw('SUM(1) DESC')
            ->limit(200)
            ->get()
            ->map(function ($row) use ($start, $end) {
                $thb = DB::table('transactions_master')
                    ->join('transactions_detail', 'transactions_master.id', '=', 'transactions_detail.transaction_id')
                    ->where('transactions_master.flag_cancel', 'N')
                    ->where('transactions_master.trns_datetime', '>', $start)
                    ->where('transactions_master.trns_datetime', '<=', $end)
                    ->where('transactions_master.cust_name', $row->cust_name)
                    ->selectRaw('
                        SUM(CASE WHEN trns_type="BUYING" THEN transactions_detail.total ELSE 0 END) as buy_thb,
                        SUM(CASE WHEN trns_type="SELLING" THEN transactions_detail.amount ELSE 0 END) as sell_thb
                    ')
                    ->first();
                $row->buy_thb = (float) ($thb->buy_thb ?? 0);
                $row->sell_thb = (float) ($thb->sell_thb ?? 0);
                return $row;
            });
    }
}
