<?php

namespace Database\Seeders;

use App\Models\Account;
use Illuminate\Database\Seeder;

class AccountSeeder extends Seeder
{
    /**
     * Basic chart of accounts for an FX exchange business.
     */
    public function run(): void
    {
        $accounts = [
            // ── Assets ──
            ['account_code' => '1000', 'name_th' => 'สินทรัพย์',                    'name_en' => 'Assets',                      'type' => 'asset',     'parent_code' => null, 'level' => 1],
            ['account_code' => '1100', 'name_th' => 'เงินสดในมือ (THB)',             'name_en' => 'Cash on Hand (THB)',           'type' => 'asset',     'parent_code' => '1000', 'level' => 2],
            ['account_code' => '1110', 'name_th' => 'เงินสดต่างประเทศในมือ',         'name_en' => 'Foreign Cash on Hand',         'type' => 'asset',     'parent_code' => '1000', 'level' => 2],
            ['account_code' => '1200', 'name_th' => 'เงินฝากธนาคาร',                'name_en' => 'Bank Deposits',                'type' => 'asset',     'parent_code' => '1000', 'level' => 2],
            ['account_code' => '1300', 'name_th' => 'ลูกหนี้การค้า',                'name_en' => 'Accounts Receivable',          'type' => 'asset',     'parent_code' => '1000', 'level' => 2],
            ['account_code' => '1400', 'name_th' => 'สินค้าคงเหลือ (ธนบัตรต่างประเทศ)', 'name_en' => 'FX Inventory',             'type' => 'asset',     'parent_code' => '1000', 'level' => 2],
            ['account_code' => '1500', 'name_th' => 'สินทรัพย์ถาวร',                'name_en' => 'Fixed Assets',                 'type' => 'asset',     'parent_code' => '1000', 'level' => 2],
            ['account_code' => '1600', 'name_th' => 'เงินมัดจำ / ค่าใช้จ่ายล่วงหน้า', 'name_en' => 'Deposits & Prepaid',         'type' => 'asset',     'parent_code' => '1000', 'level' => 2],

            // ── Liabilities ──
            ['account_code' => '2000', 'name_th' => 'หนี้สิน',                      'name_en' => 'Liabilities',                  'type' => 'liability', 'parent_code' => null, 'level' => 1],
            ['account_code' => '2100', 'name_th' => 'เจ้าหนี้การค้า',               'name_en' => 'Accounts Payable',             'type' => 'liability', 'parent_code' => '2000', 'level' => 2],
            ['account_code' => '2200', 'name_th' => 'ภาษีค้างจ่าย',                 'name_en' => 'Tax Payable',                  'type' => 'liability', 'parent_code' => '2000', 'level' => 2],
            ['account_code' => '2300', 'name_th' => 'เงินรับล่วงหน้า (Booking)',     'name_en' => 'Advance Booking Deposits',     'type' => 'liability', 'parent_code' => '2000', 'level' => 2],
            ['account_code' => '2400', 'name_th' => 'หนี้สินหมุนเวียนอื่น',          'name_en' => 'Other Current Liabilities',    'type' => 'liability', 'parent_code' => '2000', 'level' => 2],

            // ── Equity ──
            ['account_code' => '3000', 'name_th' => 'ส่วนของเจ้าของ',               'name_en' => 'Equity',                       'type' => 'equity',    'parent_code' => null, 'level' => 1],
            ['account_code' => '3100', 'name_th' => 'ทุนจดทะเบียน',                 'name_en' => 'Registered Capital',           'type' => 'equity',    'parent_code' => '3000', 'level' => 2],
            ['account_code' => '3200', 'name_th' => 'กำไรสะสม',                     'name_en' => 'Retained Earnings',            'type' => 'equity',    'parent_code' => '3000', 'level' => 2],

            // ── Revenue ──
            ['account_code' => '4000', 'name_th' => 'รายได้',                       'name_en' => 'Revenue',                      'type' => 'revenue',   'parent_code' => null, 'level' => 1],
            ['account_code' => '4100', 'name_th' => 'รายได้จากการซื้อขายเงินตราต่างประเทศ', 'name_en' => 'FX Trading Income',     'type' => 'revenue',   'parent_code' => '4000', 'level' => 2],
            ['account_code' => '4200', 'name_th' => 'กำไรจากอัตราแลกเปลี่ยน',       'name_en' => 'FX Gain',                      'type' => 'revenue',   'parent_code' => '4000', 'level' => 2],
            ['account_code' => '4300', 'name_th' => 'รายได้ค่าธรรมเนียม',           'name_en' => 'Fee Income',                   'type' => 'revenue',   'parent_code' => '4000', 'level' => 2],
            ['account_code' => '4900', 'name_th' => 'รายได้อื่น',                   'name_en' => 'Other Income',                 'type' => 'revenue',   'parent_code' => '4000', 'level' => 2],

            // ── Expenses ──
            ['account_code' => '5000', 'name_th' => 'ค่าใช้จ่าย',                   'name_en' => 'Expenses',                     'type' => 'expense',   'parent_code' => null, 'level' => 1],
            ['account_code' => '5100', 'name_th' => 'ต้นทุนซื้อเงินตราต่างประเทศ',   'name_en' => 'FX Purchase Cost',             'type' => 'expense',   'parent_code' => '5000', 'level' => 2],
            ['account_code' => '5200', 'name_th' => 'ขาดทุนจากอัตราแลกเปลี่ยน',     'name_en' => 'FX Loss',                      'type' => 'expense',   'parent_code' => '5000', 'level' => 2],
            ['account_code' => '5300', 'name_th' => 'เงินเดือนและค่าจ้าง',          'name_en' => 'Salaries & Wages',             'type' => 'expense',   'parent_code' => '5000', 'level' => 2],
            ['account_code' => '5400', 'name_th' => 'ค่าเช่าสำนักงาน',              'name_en' => 'Office Rent',                  'type' => 'expense',   'parent_code' => '5000', 'level' => 2],
            ['account_code' => '5500', 'name_th' => 'ค่าสาธารณูปโภค',               'name_en' => 'Utilities',                    'type' => 'expense',   'parent_code' => '5000', 'level' => 2],
            ['account_code' => '5600', 'name_th' => 'ค่าเสื่อมราคา',                'name_en' => 'Depreciation',                 'type' => 'expense',   'parent_code' => '5000', 'level' => 2],
            ['account_code' => '5700', 'name_th' => 'ค่าใช้จ่ายขนส่งเงิน',          'name_en' => 'Cash Transport Costs',         'type' => 'expense',   'parent_code' => '5000', 'level' => 2],
            ['account_code' => '5900', 'name_th' => 'ค่าใช้จ่ายอื่น',               'name_en' => 'Other Expenses',               'type' => 'expense',   'parent_code' => '5000', 'level' => 2],
        ];

        foreach ($accounts as $acct) {
            Account::firstOrCreate(
                ['account_code' => $acct['account_code']],
                $acct
            );
        }
    }
}
