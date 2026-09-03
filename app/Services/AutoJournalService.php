<?php

namespace App\Services;

use App\Models\Account;
use App\Models\BankSale;
use App\Models\CounterStock;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\StockTransfer;
use App\Models\TransactionAccountMapping;
use App\Models\TransactionDetail;
use App\Models\TransactionMaster;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AutoJournalService
{
    /**
     * Create auto journal entry from a Buy/Sell transaction.
     */
    public function createFromTransaction(TransactionMaster $txn): ?JournalEntry
    {
        $mapping = TransactionAccountMapping::where('trns_type', $txn->trns_type)
            ->where('is_active', true)->first();

        if (! $mapping) return null;

        return DB::transaction(function () use ($txn, $mapping) {
            $entry = JournalEntry::create([
                'entry_no'    => $this->generateEntryNo('AJ'),
                'entry_date'  => today(),
                'description' => "Auto: {$txn->trns_no} ({$txn->trns_type})",
                'type'        => 'auto',
                'source_type' => 'transaction',
                'source_id'   => $txn->id,
                'branch_id'   => $txn->counter?->branch_id,
                'is_posted'   => false,
            ]);

            // For BUY: detail.total = THB paid; For SELL: detail.amount = THB received, detail.total = foreign given
            if ($txn->trns_type === 'BUYING') {
                $thbAmount = (float) $txn->details->sum('total');  // THB paid to customer
            } else {
                $thbAmount = (float) $txn->details->sum('amount'); // THB received from customer
            }

            // Calculate cost value (sum of foreign_amount × avg_cost per detail line)
            $costValue = $this->calculateCostValue($txn);

            if ($txn->trns_type === 'BUYING') {
                // BUY: Dr FX Inventory (cost), Cr Cash THB (amount paid)
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $mapping->debit_account_id,  // 1400 FX Inventory
                    'debit'            => $thbAmount,
                    'credit'           => 0,
                    'description'      => "ซื้อเงินตรา {$txn->trns_no}",
                ]);
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $mapping->credit_account_id, // 1100 Cash THB
                    'debit'            => 0,
                    'credit'           => $thbAmount,
                    'description'      => "จ่ายเงิน THB {$txn->trns_no}",
                ]);
            } elseif (in_array($txn->trns_type, ['SELLING', 'SELLING_BANK'])) {
                // SELL: Dr Cash THB (revenue), Cr FX Inventory (cost), Dr/Cr P&L (gain/loss)
                $revenue = $thbAmount;

                // Dr Cash/Bank
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $mapping->debit_account_id,  // 1100 Cash or 1200 Bank
                    'debit'            => $revenue,
                    'credit'           => 0,
                    'description'      => "รับเงิน {$txn->trns_no}",
                ]);

                // Cr FX Inventory (at cost)
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $mapping->credit_account_id, // 1400 FX Inventory
                    'debit'            => 0,
                    'credit'           => $costValue,
                    'description'      => "ตัดต้นทุน FX {$txn->trns_no}",
                ]);

                // P&L: Gain or Loss
                $diff = round($revenue - $costValue, 2);
                if ($diff > 0 && $mapping->pl_gain_account_id) {
                    // Gain → Credit 4200
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id'       => $mapping->pl_gain_account_id,
                        'debit'            => 0,
                        'credit'           => $diff,
                        'description'      => "กำไร FX {$txn->trns_no}",
                    ]);
                } elseif ($diff < 0 && $mapping->pl_loss_account_id) {
                    // Loss → Debit 5200
                    JournalLine::create([
                        'journal_entry_id' => $entry->id,
                        'account_id'       => $mapping->pl_loss_account_id,
                        'debit'            => abs($diff),
                        'credit'           => 0,
                        'description'      => "ขาดทุน FX {$txn->trns_no}",
                    ]);
                }
            }

            return $entry;
        });
    }

    /**
     * Create auto journal for stock transfer (borrow/return/disbursement).
     */
    public function createFromTransfer(StockTransfer $transfer): ?JournalEntry
    {
        $mapping = TransactionAccountMapping::where('trns_type', 'TRANSFER')
            ->where('is_active', true)->first();

        if (! $mapping) return null;

        $totalValue = round($transfer->amount * $transfer->unit_price, 2);
        if ($totalValue <= 0) return null;

        return DB::transaction(function () use ($transfer, $mapping, $totalValue) {
            $entry = JournalEntry::create([
                'entry_no'    => $this->generateEntryNo('AJ'),
                'entry_date'  => today(),
                'description' => "Auto: {$transfer->transfer_no} ({$transfer->transfer_type})",
                'type'        => 'auto',
                'source_type' => 'transfer',
                'source_id'   => $transfer->id,
                'branch_id'   => $transfer->fromCounter?->branch_id,
                'is_posted'   => false,
            ]);

            // Dr FX Inventory (destination branch)
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $mapping->debit_account_id,
                'debit'            => $totalValue,
                'credit'           => 0,
                'description'      => "รับโอน {$transfer->currency_code} → {$transfer->toCounter?->counter_name}",
            ]);

            // Cr FX Inventory (source branch)
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $mapping->credit_account_id,
                'debit'            => 0,
                'credit'           => $totalValue,
                'description'      => "โอนออก {$transfer->currency_code} ← {$transfer->fromCounter?->counter_name}",
            ]);

            return $entry;
        });
    }

    /**
     * Create auto journal for stock adjustment.
     */
    public function createFromAdjustment(int $counterId, string $currencyCode, float $amount, float $unitPrice, string $reason): ?JournalEntry
    {
        $mapping = TransactionAccountMapping::where('trns_type', 'ADJUST')
            ->where('is_active', true)->first();

        if (! $mapping) return null;

        $totalValue = round(abs($amount) * $unitPrice, 2);
        if ($totalValue <= 0) return null;

        $counter = \App\Models\Counter::find($counterId);

        return DB::transaction(function () use ($mapping, $totalValue, $amount, $counter, $currencyCode, $reason) {
            $entry = JournalEntry::create([
                'entry_no'    => $this->generateEntryNo('AJ'),
                'entry_date'  => today(),
                'description' => "Auto Adjust: {$currencyCode} {$reason}",
                'type'        => 'auto',
                'source_type' => 'adjustment',
                'source_id'   => null,
                'branch_id'   => $counter?->branch_id,
                'is_posted'   => false,
            ]);

            if ($amount > 0) {
                // Stock increase: Dr FX Inventory, Cr Other Expense
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $mapping->debit_account_id,
                    'debit'            => $totalValue,
                    'credit'           => 0,
                    'description'      => "ปรับเพิ่มสต็อก {$currencyCode}",
                ]);
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $mapping->credit_account_id,
                    'debit'            => 0,
                    'credit'           => $totalValue,
                    'description'      => "ปรับเพิ่ม: {$reason}",
                ]);
            } else {
                // Stock decrease: Dr Other Expense, Cr FX Inventory
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $mapping->credit_account_id, // Expense
                    'debit'            => $totalValue,
                    'credit'           => 0,
                    'description'      => "ปรับลด: {$reason}",
                ]);
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $mapping->debit_account_id, // FX Inventory
                    'debit'            => 0,
                    'credit'           => $totalValue,
                    'description'      => "ปรับลดสต็อก {$currencyCode}",
                ]);
            }

            return $entry;
        });
    }

    /**
     * Create auto journal for bank sale completion.
     */
    public function createFromBankSale(BankSale $sale, float $totalCost, float $profitLoss): ?JournalEntry
    {
        $mapping = TransactionAccountMapping::where('trns_type', 'SELLING_BANK')
            ->where('is_active', true)->first();

        if (! $mapping) return null;

        return DB::transaction(function () use ($sale, $mapping, $totalCost, $profitLoss) {
            $entry = JournalEntry::create([
                'entry_no'    => $this->generateEntryNo('AJ'),
                'entry_date'  => today(),
                'description' => "Auto: Bank Sale {$sale->sale_no} → {$sale->bank_name}",
                'type'        => 'auto',
                'source_type' => 'bank_sale',
                'source_id'   => $sale->id,
                'branch_id'   => $sale->sources->first()?->counter?->branch_id,
                'is_posted'   => false,
            ]);

            // Dr Bank Deposits (revenue)
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $mapping->debit_account_id,  // 1200 Bank Deposits
                'debit'            => $sale->total_thb,
                'credit'           => 0,
                'description'      => "รับเงินจากธนาคาร {$sale->bank_name}",
                'currency_code'    => $sale->currency_code,
            ]);

            // Cr FX Inventory (cost)
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $mapping->credit_account_id, // 1400 FX Inventory
                'debit'            => 0,
                'credit'           => $totalCost,
                'description'      => "ตัดต้นทุน {$sale->currency_code} {$sale->sale_no}",
                'currency_code'    => $sale->currency_code,
            ]);

            // P&L
            if ($profitLoss > 0 && $mapping->pl_gain_account_id) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $mapping->pl_gain_account_id,
                    'debit'            => 0,
                    'credit'           => $profitLoss,
                    'description'      => "กำไร FX ขายธนาคาร {$sale->sale_no}",
                    'currency_code'    => $sale->currency_code,
                ]);
            } elseif ($profitLoss < 0 && $mapping->pl_loss_account_id) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $mapping->pl_loss_account_id,
                    'debit'            => abs($profitLoss),
                    'credit'           => 0,
                    'description'      => "ขาดทุน FX ขายธนาคาร {$sale->sale_no}",
                    'currency_code'    => $sale->currency_code,
                ]);
            }

            return $entry;
        });
    }

    /**
     * Create auto journal for a bank PURCHASE (ซื้อเงินตราจากธนาคาร).
     *
     * ซื้อเข้าไม่มีกำไร/ขาดทุน — เงินที่จ่ายคือต้นทุนของสต็อกที่รับเข้ามา
     * จึงมีแค่ Dr FX Inventory / Cr Bank Deposits เท่านั้น
     */
    public function createFromBankPurchase(BankSale $purchase): ?JournalEntry
    {
        $mapping = TransactionAccountMapping::where('trns_type', 'BUYING_BANK')
            ->where('is_active', true)->first();

        if (! $mapping) return null;

        return DB::transaction(function () use ($purchase, $mapping) {
            $purchase->loadMissing('destinationCounter');

            $entry = JournalEntry::create([
                'entry_no'    => $this->generateEntryNo('AJ'),
                'entry_date'  => today(),
                'description' => "Auto: Bank Purchase {$purchase->sale_no} ← {$purchase->bank_name}",
                'type'        => 'auto',
                'source_type' => 'bank_purchase',
                'source_id'   => $purchase->id,
                // ของเข้ากองกลางที่เดียว — สาขาของ entry คือสาขาของกองกลางนั้น
                'branch_id'   => $purchase->destinationCounter?->branch_id
                    ?? $purchase->sources->first()?->counter?->branch_id,
                'is_posted'   => false,
            ]);

            // ใบที่มีหลายสกุลปนกันไม่มี currency_code เดียวที่ถูกต้อง — ปล่อยว่างดีกว่าใส่ MIXED
            $isMixed = $purchase->currency_code === BankSale::CURRENCY_MIXED;
            $lineCurrency = $isMixed ? null : $purchase->currency_code;
            $received = $isMixed ? 'เงินตราหลายสกุล' : $purchase->currency_code;

            // Dr FX Inventory (สต็อกที่รับเข้า ตีราคาด้วยเรทที่ซื้อ)
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $mapping->debit_account_id,  // 1400 FX Inventory
                'debit'            => $purchase->total_thb,
                'credit'           => 0,
                'description'      => "รับ {$received} เข้าสต็อก {$purchase->sale_no}",
                'currency_code'    => $lineCurrency,
            ]);

            // Cr Bank Deposits (เงินที่จ่ายออก)
            JournalLine::create([
                'journal_entry_id' => $entry->id,
                'account_id'       => $mapping->credit_account_id, // 1200 Bank Deposits
                'debit'            => 0,
                'credit'           => $purchase->total_thb,
                'description'      => "จ่ายเงินให้ธนาคาร {$purchase->bank_name}",
                'currency_code'    => $lineCurrency,
            ]);

            return $entry;
        });
    }

    /**
     * Create reversal journal entry (for void/cancel).
     */
    public function createReversal(JournalEntry $originalEntry): ?JournalEntry
    {
        return DB::transaction(function () use ($originalEntry) {
            $entry = JournalEntry::create([
                'entry_no'    => $this->generateEntryNo('RV'),
                'entry_date'  => today(),
                'description' => "Reversal: {$originalEntry->entry_no}",
                'type'        => 'reversal',
                'source_type' => $originalEntry->source_type,
                'source_id'   => $originalEntry->source_id,
                'branch_id'   => $originalEntry->branch_id,
                'is_posted'   => false,
            ]);

            // Swap Dr/Cr from original
            foreach ($originalEntry->lines as $line) {
                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id'       => $line->account_id,
                    'debit'            => (float) $line->credit,  // swap
                    'credit'           => (float) $line->debit,   // swap
                    'description'      => "กลับรายการ: {$line->description}",
                    'currency_code'    => $line->currency_code,
                ]);
            }

            return $entry;
        });
    }

    /**
     * Calculate cost value for a transaction based on avg_cost at time of transaction.
     */
    private function calculateCostValue(TransactionMaster $txn): float
    {
        $total = 0;
        foreach ($txn->details as $detail) {
            $stock = CounterStock::where('counter_id', $txn->counter_id)
                ->where('denomination_id', $detail->denomination_id)
                ->first();
            $avgCost = $stock ? (float) $stock->avg_cost : (float) $detail->unit_price;
            // For SELL: amount field = THB from customer, total field = foreign amount given
            // Cost = foreign_amount × avg_cost
            if ($txn->trns_type === 'BUYING') {
                $total += (float) $detail->amount * $avgCost;
            } else {
                // SELLING: total = foreign amount
                $total += (float) $detail->total * $avgCost;
            }
        }
        return round($total, 2);
    }

    private function generateEntryNo(string $prefix): string
    {
        $today = now()->format('Ymd');
        $last = JournalEntry::where('entry_no', 'like', $prefix . $today . '%')
            ->orderByDesc('entry_no')->first();
        $seq = $last ? (int) substr($last->entry_no, -4) + 1 : 1;
        return $prefix . $today . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
