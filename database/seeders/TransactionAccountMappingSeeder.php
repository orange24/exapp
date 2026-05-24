<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\TransactionAccountMapping;
use Illuminate\Database\Seeder;

class TransactionAccountMappingSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = Account::pluck('id', 'account_code');

        $mappings = [
            [
                'trns_type'          => 'BUYING',
                'description'        => 'ซื้อเงินตราจากลูกค้า — Dr FX Inventory, Cr Cash THB',
                'debit_account_id'   => $accounts['1400'], // FX Inventory
                'credit_account_id'  => $accounts['1100'], // Cash on Hand THB
                'pl_gain_account_id' => null,
                'pl_loss_account_id' => null,
            ],
            [
                'trns_type'          => 'SELLING',
                'description'        => 'ขายเงินตราลูกค้า — Dr Cash THB, Cr FX Inventory, P&L FX Gain/Loss',
                'debit_account_id'   => $accounts['1100'], // Cash on Hand THB
                'credit_account_id'  => $accounts['1400'], // FX Inventory
                'pl_gain_account_id' => $accounts['4200'], // FX Gain
                'pl_loss_account_id' => $accounts['5200'], // FX Loss
            ],
            [
                'trns_type'          => 'SELLING_BANK',
                'description'        => 'ขายเงินตราให้ธนาคาร — Dr Bank Deposits, Cr FX Inventory, P&L',
                'debit_account_id'   => $accounts['1200'], // Bank Deposits
                'credit_account_id'  => $accounts['1400'], // FX Inventory
                'pl_gain_account_id' => $accounts['4200'], // FX Gain
                'pl_loss_account_id' => $accounts['5200'], // FX Loss
            ],
            [
                'trns_type'          => 'TRANSFER',
                'description'        => 'โอนระหว่างสาขา — Dr FX Inventory (ปลายทาง), Cr FX Inventory (ต้นทาง)',
                'debit_account_id'   => $accounts['1400'], // FX Inventory (dest)
                'credit_account_id'  => $accounts['1400'], // FX Inventory (source)
                'pl_gain_account_id' => null,
                'pl_loss_account_id' => null,
            ],
            [
                'trns_type'          => 'ADJUST',
                'description'        => 'ปรับปรุงสต็อก — Dr/Cr FX Inventory ↔ Other Expense',
                'debit_account_id'   => $accounts['1400'], // FX Inventory (for increase)
                'credit_account_id'  => $accounts['5900'], // Other Expenses (for increase) / reversed for decrease
                'pl_gain_account_id' => null,
                'pl_loss_account_id' => null,
            ],
            [
                'trns_type'          => 'VOID',
                'description'        => 'กลับรายการ — สลับ Dr/Cr ของรายการเดิม',
                'debit_account_id'   => $accounts['1400'], // placeholder
                'credit_account_id'  => $accounts['1100'], // placeholder
                'pl_gain_account_id' => null,
                'pl_loss_account_id' => null,
            ],
        ];

        foreach ($mappings as $mapping) {
            TransactionAccountMapping::updateOrCreate(
                ['trns_type' => $mapping['trns_type']],
                $mapping
            );
        }
    }
}
