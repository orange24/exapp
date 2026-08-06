<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define all menus
        $menus = [
            // Dashboard
            ['key' => 'dashboard', 'label_th' => 'หน้าหลัก', 'label_en' => 'Dashboard', 'route' => 'dashboard', 'icon' => 'home', 'parent_id' => null, 'order' => 0],

            // Trader Inventory — sits right under หน้าหลัก, both are top-level dashboards
            ['key' => 'trader.inventory', 'label_th' => 'Inventory Dashboard', 'label_en' => 'Inventory Dashboard', 'route' => 'trader.inventory', 'icon' => 'chart-bar', 'parent_id' => null, 'order' => 1],

            // Transactions (parent)
            ['key' => 'transactions', 'label_th' => 'ธุรกรรม', 'label_en' => 'Transactions', 'route' => null, 'icon' => 'document-text', 'parent_id' => null, 'order' => 2],
            ['key' => 'transaction.buy', 'label_th' => 'รับซื้อ (Buy)', 'label_en' => 'Buy', 'route' => 'transaction.buy', 'icon' => 'plus', 'parent_id' => 'transactions', 'order' => 1],
            ['key' => 'transaction.sell', 'label_th' => 'จ่ายขาย (Sell)', 'label_en' => 'Sell', 'route' => 'transaction.sell', 'icon' => 'minus', 'parent_id' => 'transactions', 'order' => 2],
            ['key' => 'transaction.my', 'label_th' => 'รายการของฉัน', 'label_en' => 'My Transactions', 'route' => 'transaction.my', 'icon' => 'clipboard-list', 'parent_id' => 'transactions', 'order' => 3],
            ['key' => 'admin.bank-sales', 'label_th' => 'ขายธนาคาร', 'label_en' => 'Bank Sales', 'route' => 'admin.bank-sales', 'icon' => 'building-library', 'parent_id' => 'transactions', 'order' => 4],
            ['key' => 'admin.bank-purchases', 'label_th' => 'ซื้อจากธนาคาร', 'label_en' => 'Bank Purchases', 'route' => 'admin.bank-purchases', 'icon' => 'building-library', 'parent_id' => 'transactions', 'order' => 5],

            // Rates (parent)
            ['key' => 'rates', 'label_th' => 'อัตราแลกเปลี่ยน', 'label_en' => 'Exchange Rates', 'route' => null, 'icon' => 'currency-dollar', 'parent_id' => null, 'order' => 3],
            ['key' => 'admin.rate.index', 'label_th' => 'ตั้งราคา', 'label_en' => 'Set Rates', 'route' => 'admin.rate.index', 'icon' => 'pencil', 'parent_id' => 'rates', 'order' => 1],
            ['key' => 'admin.rate-settings.index', 'label_th' => 'ตั้งค่าคำนวณ', 'label_en' => 'Rate Calculation', 'route' => 'admin.rate-settings.index', 'icon' => 'cog', 'parent_id' => 'rates', 'order' => 2],
            ['key' => 'admin.superrich-rates', 'label_th' => 'SuperRich Rates', 'label_en' => 'SuperRich Rates', 'route' => 'admin.superrich-rates', 'icon' => 'star', 'parent_id' => 'rates', 'order' => 3],
            ['key' => 'rate.board', 'label_th' => 'ดูเรทสาขา', 'label_en' => 'Rate Board', 'route' => 'rate.board', 'icon' => 'tv', 'parent_id' => 'rates', 'order' => 4],

            // Reports (parent)
            ['key' => 'reports', 'label_th' => 'รายงาน', 'label_en' => 'Reports', 'route' => null, 'icon' => 'document-chart-bar', 'parent_id' => null, 'order' => 4],
            ['key' => 'admin.transactions', 'label_th' => 'ค้นหารายการ', 'label_en' => 'Search Transactions', 'route' => 'admin.transactions', 'icon' => 'magnifying-glass', 'parent_id' => 'reports', 'order' => 1],
            ['key' => 'reports.daily', 'label_th' => 'สรุปประจำวัน', 'label_en' => 'Daily Summary', 'route' => 'reports.daily', 'icon' => 'calendar', 'parent_id' => 'reports', 'order' => 2],

            // Inventory (parent)
            ['key' => 'inventory', 'label_th' => 'คลังสินค้า', 'label_en' => 'Inventory', 'route' => null, 'icon' => 'archive-box', 'parent_id' => null, 'order' => 5],
            ['key' => 'inventory.dashboard', 'label_th' => 'Dashboard', 'label_en' => 'Dashboard', 'route' => 'inventory.index', 'icon' => 'squares-2x2', 'parent_id' => 'inventory', 'order' => 1],
            ['key' => 'inventory.booking', 'label_th' => 'Booking', 'label_en' => 'Booking', 'route' => 'inventory.booking', 'icon' => 'bookmark', 'parent_id' => 'inventory', 'order' => 2],
            ['key' => 'inventory.transfer', 'label_th' => 'ยืม/คืน/โอน', 'label_en' => 'Transfer', 'route' => 'inventory.borrow-return', 'icon' => 'arrow-path', 'parent_id' => 'inventory', 'order' => 3],
            ['key' => 'inventory.adjustment', 'label_th' => 'ปรับ Denomination', 'label_en' => 'Adjustment', 'route' => 'inventory.adjustment', 'icon' => 'adjustments-horizontal', 'parent_id' => 'inventory', 'order' => 4],

            // Master Data (parent)
            ['key' => 'master-data', 'label_th' => 'ข้อมูลหลัก', 'label_en' => 'Master Data', 'route' => null, 'icon' => 'database', 'parent_id' => null, 'order' => 6],
            ['key' => 'admin.currencies', 'label_th' => 'สกุลเงิน', 'label_en' => 'Currencies', 'route' => 'admin.currencies.index', 'icon' => 'banknotes', 'parent_id' => 'master-data', 'order' => 1],
            ['key' => 'admin.customers', 'label_th' => 'ลูกค้า', 'label_en' => 'Customers', 'route' => 'admin.customers.index', 'icon' => 'users', 'parent_id' => 'master-data', 'order' => 2],
            ['key' => 'admin.accounts', 'label_th' => 'ผังบัญชี', 'label_en' => 'Chart of Accounts', 'route' => 'admin.accounts.index', 'icon' => 'book-open', 'parent_id' => 'master-data', 'order' => 3],

            // Settings (parent - Admin only)
            ['key' => 'settings', 'label_th' => 'ตั้งค่า', 'label_en' => 'Settings', 'route' => null, 'icon' => 'cog-6-tooth', 'parent_id' => null, 'order' => 7],
            ['key' => 'admin.branches', 'label_th' => 'จัดการสาขา', 'label_en' => 'Branches', 'route' => 'admin.branches.index', 'icon' => 'building-office', 'parent_id' => 'settings', 'order' => 1],
            ['key' => 'admin.users', 'label_th' => 'จัดการผู้ใช้', 'label_en' => 'Users', 'route' => 'admin.users.index', 'icon' => 'user-group', 'parent_id' => 'settings', 'order' => 2],
            ['key' => 'admin.permissions', 'label_th' => 'สิทธิ์', 'label_en' => 'Permissions', 'route' => 'admin.permissions.index', 'icon' => 'shield-check', 'parent_id' => 'settings', 'order' => 3],
            ['key' => 'admin.sessions', 'label_th' => 'จัดการ Session', 'label_en' => 'Sessions', 'route' => 'admin.sessions', 'icon' => 'computer-desktop', 'parent_id' => 'settings', 'order' => 4],
        ];

        // Create menus in two passes to ensure parents exist before children
        $menuIdMap = [];

        // First pass: Create all parent menus (parent_id = null)
        foreach ($menus as $menuData) {
            if ($menuData['parent_id'] === null) {
                $menu = Menu::create($menuData);
                $menuIdMap[$menu->key] = $menu->id;
            }
        }

        // Second pass: Create all child menus (parent_id != null)
        foreach ($menus as $menuData) {
            if ($menuData['parent_id'] !== null) {
                $parentKey = $menuData['parent_id'];
                $menuData['parent_id'] = $menuIdMap[$parentKey] ?? null;

                $menu = Menu::create($menuData);
                $menuIdMap[$menu->key] = $menu->id;
            }
        }

        // Role-Menu mappings
        $this->attachMenusToRoles($menuIdMap);
    }

    protected function attachMenusToRoles(array $menuIdMap): void
    {
        $roles = Role::whereIn('name', ['admin', 'staff', 'trader', 'auditor', 'branch_manager', 'superadmin'])->get()->keyBy('name');

        // Staff: Dashboard, Buy, Sell, My Transactions, Inventory
        $roles['staff']->menus()->attach([
            $menuIdMap['dashboard'],
            $menuIdMap['transactions'],
            $menuIdMap['transaction.buy'],
            $menuIdMap['transaction.sell'],
            $menuIdMap['transaction.my'],
            $menuIdMap['inventory'],
            $menuIdMap['inventory.dashboard'],
            $menuIdMap['inventory.booking'],
            $menuIdMap['inventory.transfer'],
            $menuIdMap['inventory.adjustment'],
        ]);

        // Branch Manager: Everything except Settings and the trader-only tools
        $branchManagerMenus = collect($menuIdMap)->except(['settings', 'admin.branches', 'admin.users', 'admin.permissions', 'admin.sessions', 'trader.inventory', 'admin.bank-sales', 'admin.bank-purchases'])->values();
        $roles['branch_manager']->menus()->attach($branchManagerMenus);

        // Trader: Inventory Dashboard is their home screen (/dashboard redirects
        // there), so the generic หน้าหลัก menu is omitted — two links to the same
        // page. Plus Bank Sales, Reports (not Buy/Sell).
        $roles['trader']->menus()->attach([
            $menuIdMap['trader.inventory'],
            $menuIdMap['transactions'], // parent visible but children Buy/Sell not visible
            $menuIdMap['transaction.my'],
            $menuIdMap['admin.bank-sales'],
            $menuIdMap['admin.bank-purchases'],
            $menuIdMap['reports'],
            $menuIdMap['admin.transactions'],
            $menuIdMap['reports.daily'],
            $menuIdMap['inventory'],
            $menuIdMap['inventory.dashboard'],
        ]);

        // Auditor: Dashboard, Reports, Master Data (read-only)
        $roles['auditor']->menus()->attach([
            $menuIdMap['dashboard'],
            $menuIdMap['reports'],
            $menuIdMap['admin.transactions'],
            $menuIdMap['reports.daily'],
            $menuIdMap['master-data'],
            $menuIdMap['admin.currencies'],
            $menuIdMap['admin.customers'],
            $menuIdMap['admin.accounts'],
        ]);

        // Admin & Superadmin: All menus except the trader-only tools. Their route
        // guards are abort_unless(isTrader()), so showing them to admin would
        // render links that 403.
        $allMenuIds = collect($menuIdMap)->except(['trader.inventory', 'admin.bank-sales', 'admin.bank-purchases'])->values();
        $roles['admin']->menus()->attach($allMenuIds);
        if (isset($roles['superadmin'])) {
            $roles['superadmin']->menus()->attach($allMenuIds);
        }
    }
}
