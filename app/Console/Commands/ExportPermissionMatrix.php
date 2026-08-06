<?php

namespace App\Console\Commands;

use App\Models\Role;
use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExportPermissionMatrix extends Command
{
    protected $signature = 'permissions:export {--output=permission-matrix.xlsx}';
    protected $description = 'Export detailed permission matrix to Excel';

    public function handle()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Permission Matrix');

        // Define menu items with their corresponding module and required actions
        $menuItems = [
            // Dashboard
            ['menu' => 'หน้าหลัก (Dashboard)', 'module' => null, 'actions' => []],

            // Transactions
            ['menu' => 'รับซื้อ (Buy)', 'module' => 'module3', 'actions' => ['read', 'write']],
            ['menu' => 'จ่ายขาย (Sell)', 'module' => 'module3', 'actions' => ['read', 'write']],
            ['menu' => 'รายการของฉัน (My Trans)', 'module' => 'module3', 'actions' => ['read']],
            ['menu' => 'ขายธนาคาร (Bank Sales)', 'module' => 'module3', 'actions' => ['read', 'write'], 'custom' => 'Trader Only'],
            ['menu' => 'พิมพ์ใบเสร็จ (Print Slip)', 'module' => 'module3', 'actions' => ['print']],

            // Rates
            ['menu' => 'ตั้งราคา (Rate Setup)', 'module' => 'module3', 'actions' => ['write'], 'custom' => 'Admin/Branch Manager'],
            ['menu' => 'ตั้งค่าคำนวณ (Rate Calc)', 'module' => 'module3', 'actions' => ['write'], 'custom' => 'Admin Only'],
            ['menu' => 'อัตราอ้างอิง SuperRich', 'module' => 'module3', 'actions' => ['read'], 'custom' => 'Admin Only'],
            ['menu' => 'ดูเรทสาขา (Rate Board)', 'module' => null, 'actions' => []],

            // Reports
            ['menu' => 'ค้นหารายการ (Search Trans)', 'module' => 'module6', 'actions' => ['read'], 'custom' => 'Admin/Branch Manager'],
            ['menu' => 'สรุปประจำวัน (Daily Summary)', 'module' => 'module6', 'actions' => ['read', 'export']],
            ['menu' => 'Accounting Summary', 'module' => 'module6', 'actions' => ['read', 'export']],
            ['menu' => 'Average Rate Summary', 'module' => 'module6', 'actions' => ['read', 'export']],
            ['menu' => 'Stock Movement Report', 'module' => 'module6', 'actions' => ['read', 'export']],
            ['menu' => 'Transfer Report', 'module' => 'module6', 'actions' => ['read', 'export']],
            ['menu' => 'Profit/Loss Report', 'module' => 'module6', 'actions' => ['read', 'export']],
            ['menu' => 'Stock Valuation Report', 'module' => 'module6', 'actions' => ['read', 'export']],
            ['menu' => 'Cashier Performance', 'module' => 'module6', 'actions' => ['read', 'export']],
            ['menu' => 'Customer Transaction', 'module' => 'module6', 'actions' => ['read', 'export']],

            // Master Data
            ['menu' => 'จัดการสกุลเงิน (Currencies)', 'module' => 'module2', 'actions' => ['read', 'write']],
            ['menu' => 'ทะเบียนลูกค้า (Customers)', 'module' => 'module2', 'actions' => ['read', 'write']],
            ['menu' => 'ผังบัญชี (Chart of Accounts)', 'module' => 'module2', 'actions' => ['read', 'write']],

            // Inventory
            ['menu' => 'Inventory Dashboard (Trader)', 'module' => 'module5', 'actions' => ['read'], 'custom' => 'Trader Only'],
            ['menu' => 'Stock Summary', 'module' => 'module5', 'actions' => ['read']],
            ['menu' => 'Stock Movements', 'module' => 'module5', 'actions' => ['read']],
            ['menu' => 'Borrow/Return', 'module' => 'module5', 'actions' => ['read', 'write']],
            ['menu' => 'Disbursement', 'module' => 'module5', 'actions' => ['read', 'write']],
            ['menu' => 'Intraday Return', 'module' => 'module5', 'actions' => ['read', 'write']],
            ['menu' => 'Open/Close Day', 'module' => 'module5', 'actions' => ['read', 'write']],
            ['menu' => 'Booking', 'module' => 'module5', 'actions' => ['read', 'write']],
            ['menu' => 'Adjustment', 'module' => 'module5', 'actions' => ['read', 'write']],

            // Accounting
            ['menu' => 'Journal Entry', 'module' => 'module4', 'actions' => ['read', 'write']],
            ['menu' => 'Ledger', 'module' => 'module4', 'actions' => ['read']],

            // Settings (Admin)
            ['menu' => 'จัดการสาขา (Branches)', 'module' => 'module1', 'actions' => ['read', 'write'], 'custom' => 'Admin Only'],
            ['menu' => 'จัดการผู้ใช้ (Users)', 'module' => 'module1', 'actions' => ['read', 'write'], 'custom' => 'Admin Only'],
            ['menu' => 'สิทธิ์ (Permissions)', 'module' => 'module1', 'actions' => ['read', 'write'], 'custom' => 'Admin Only'],
            ['menu' => 'จัดการ Session', 'module' => 'module1', 'actions' => ['read', 'write'], 'custom' => 'Admin Only'],

            // User
            ['menu' => 'เปลี่ยนรหัสผ่าน', 'module' => null, 'actions' => []],
        ];

        // Get all roles
        $roles = Role::with('permissions')->orderBy('id')->get();

        // Header row
        $row = 1;
        $sheet->setCellValue('A' . $row, 'เมนู / ฟีเจอร์');
        $sheet->setCellValue('B' . $row, 'Module');

        $col = 'C';
        foreach ($roles as $role) {
            $sheet->setCellValue($col . $row, $role->display_name);
            $col++;
        }

        // Style header
        $lastCol = chr(ord('B') + count($roles));
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0e513a']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ];
        $sheet->getStyle('A1:' . $lastCol . '1')->applyFromArray($headerStyle);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Data rows
        $row = 2;
        foreach ($menuItems as $item) {
            $sheet->setCellValue('A' . $row, $item['menu']);
            $sheet->setCellValue('B' . $row, $item['module'] ?? '-');

            $col = 'C';
            foreach ($roles as $role) {
                $access = $this->checkAccess($role, $item);
                $sheet->setCellValue($col . $row, $access);

                // Color code
                if (str_contains($access, '✅')) {
                    $sheet->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('d4edda');
                } elseif (str_contains($access, '⚠️')) {
                    $sheet->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('fff3cd');
                } elseif (str_contains($access, '❌')) {
                    $sheet->getStyle($col . $row)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('f8d7da');
                }

                $col++;
            }

            // Border
            $sheet->getStyle('A' . $row . ':' . $lastCol . $row)
                ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            $row++;
        }

        // Auto size columns
        $sheet->getColumnDimension('A')->setWidth(40);
        $sheet->getColumnDimension('B')->setWidth(15);
        $col = 'C';
        for ($i = 0; $i < count($roles); $i++) {
            $sheet->getColumnDimension($col)->setWidth(20);
            $col++;
        }

        // Freeze header
        $sheet->freezePane('A2');

        // Save
        $outputPath = storage_path('app/' . $this->option('output'));
        $writer = new Xlsx($spreadsheet);
        $writer->save($outputPath);

        $this->info("Permission matrix exported to: {$outputPath}");

        return 0;
    }

    private function checkAccess($role, $item)
    {
        // Special cases
        if ($item['custom'] ?? false) {
            if ($item['custom'] === 'Admin Only') {
                return $role->name === 'admin' ? '✅ ทั้งหมด' : '❌ ไม่มีสิทธิ์';
            }
            if ($item['custom'] === 'Admin/Branch Manager') {
                return in_array($role->name, ['admin', 'branch_manager']) ? '✅ ทั้งหมด' : '❌ ไม่มีสิทธิ์';
            }
            if ($item['custom'] === 'Trader Only') {
                return $role->name === 'trader' ? '✅ ทั้งหมด' : '❌ ไม่มีสิทธิ์';
            }
        }

        // No module = accessible to all
        if (!$item['module']) {
            return '✅ ทั้งหมด';
        }

        // Admin has all
        if ($role->name === 'admin') {
            return '✅ ทั้งหมด';
        }

        // Check permissions
        $rolePermissions = $role->permissions()
            ->where('module', $item['module'])
            ->pluck('action')
            ->toArray();

        if (empty($rolePermissions)) {
            return '❌ ไม่มีสิทธิ์';
        }

        $requiredActions = $item['actions'];
        $hasAll = empty($requiredActions) || count(array_intersect($requiredActions, $rolePermissions)) === count($requiredActions);

        if ($hasAll) {
            $actions = implode(', ', array_map(fn($a) => strtoupper(substr($a, 0, 1)), $rolePermissions));
            return '✅ ' . $actions;
        } else {
            $actions = implode(', ', array_map(fn($a) => strtoupper(substr($a, 0, 1)), $rolePermissions));
            return '⚠️ ' . $actions;
        }
    }
}

