<?php

namespace App\Services;

use App\Models\CounterStock;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\Inventory;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * Record a BUY transaction into inventory.
     * Buy = customer gives foreign currency → stock increases.
     */
    public function recordBuy(
        int $counterId,
        string $currencyCode,
        int $denominationId,
        float $amount,
        float $rateBuy,
        int $transactionId,
        int $userId
    ): void {
        $this->applyReceipt(
            counterId: $counterId,
            currencyCode: $currencyCode,
            denominationId: $denominationId,
            amount: $amount,
            unitCost: $rateBuy,
            referenceType: 'transaction',
            referenceId: $transactionId,
            userId: $userId,
        );
    }

    /**
     * Stock coming IN to a counter — the one place the weighted-average cost is calculated.
     *
     * ใช้ร่วมกันระหว่างการรับซื้อจากลูกค้า (recordBuy) และการรับของจากธนาคาร
     * (BankSaleService ฝั่ง direction=buy) เพื่อไม่ให้สูตร avg_cost แตกเป็นสองชุด
     */
    public function applyReceipt(
        int $counterId,
        string $currencyCode,
        int $denominationId,
        float $amount,
        float $unitCost,
        string $referenceType,
        int $referenceId,
        int $userId,
        ?string $note = null
    ): void {
        DB::transaction(function () use ($counterId, $currencyCode, $denominationId, $amount, $unitCost, $referenceType, $referenceId, $userId, $note) {
            // Lock and get/create counter_stock row
            $stock = CounterStock::lockForUpdate()->firstOrCreate(
                [
                    'counter_id' => $counterId,
                    'currency_code' => $currencyCode,
                    'denomination_id' => $denominationId,
                ],
                ['quantity' => 0, 'hold_amount' => 0, 'avg_cost' => 0, 'total_cost_value' => 0]
            );

            // If row was not locked by firstOrCreate (existed already), lock it now
            if (!$stock->wasRecentlyCreated) {
                $stock = CounterStock::lockForUpdate()->find($stock->id);
            }

            // Weighted average cost: new_avg = (old_qty * old_avg + amount * rate) / (old_qty + amount)
            $oldQty = (float) $stock->quantity;
            $oldAvg = (float) $stock->avg_cost;
            $newQty = $oldQty + $amount;

            if ($newQty > 0) {
                $stock->avg_cost = ($oldQty * $oldAvg + $amount * $unitCost) / $newQty;
            }

            $stock->quantity = $newQty;
            $stock->total_cost_value = $stock->quantity * $stock->avg_cost;
            $stock->save();

            // Record movement
            StockMovement::create([
                'counter_id' => $counterId,
                'currency_code' => $currencyCode,
                'denomination_id' => $denominationId,
                'movement_type' => 'buy',
                'amount' => $amount,
                'unit_price' => $unitCost,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
                'moved_by' => $userId,
                'moved_at' => now(),
            ]);
        });
    }

    /**
     * Record a SELL transaction into inventory.
     * Sell = customer gets foreign currency → stock decreases.
     * Average cost does NOT change on sell.
     */
    public function recordSell(
        int $counterId,
        string $currencyCode,
        int $denominationId,
        float $foreignAmount,
        float $rateSell,
        int $transactionId,
        int $userId
    ): void {
        DB::transaction(function () use ($counterId, $currencyCode, $denominationId, $foreignAmount, $rateSell, $transactionId, $userId) {
            $stock = CounterStock::lockForUpdate()->firstOrCreate(
                [
                    'counter_id' => $counterId,
                    'currency_code' => $currencyCode,
                    'denomination_id' => $denominationId,
                ],
                ['quantity' => 0, 'hold_amount' => 0, 'avg_cost' => 0, 'total_cost_value' => 0]
            );

            if (!$stock->wasRecentlyCreated) {
                $stock = CounterStock::lockForUpdate()->find($stock->id);
            }

            // Prevent negative stock
            $available = (float) $stock->quantity - (float) $stock->hold_amount;
            if ($foreignAmount > $available) {
                throw new \RuntimeException("สต็อกไม่เพียงพอ (Available: " . number_format($available, 2) . ", ต้องการ: " . number_format($foreignAmount, 2) . ")");
            }

            $stock->quantity = (float) $stock->quantity - $foreignAmount;
            $stock->total_cost_value = $stock->quantity * (float) $stock->avg_cost;
            $stock->save();

            StockMovement::create([
                'counter_id' => $counterId,
                'currency_code' => $currencyCode,
                'denomination_id' => $denominationId,
                'movement_type' => 'sell',
                'amount' => -$foreignAmount,
                'unit_price' => $rateSell,
                'reference_type' => 'transaction',
                'reference_id' => $transactionId,
                'moved_by' => $userId,
                'moved_at' => now(),
            ]);
        });
    }

    /**
     * Reverse all stock movements for a cancelled transaction.
     */
    public function reverseMovement(int $transactionId, int $userId): void
    {
        DB::transaction(function () use ($transactionId, $userId) {
            $movements = StockMovement::where('reference_type', 'transaction')
                ->where('reference_id', $transactionId)
                ->where('movement_type', '!=', 'adjustment')
                ->get();

            foreach ($movements as $movement) {
                $stock = CounterStock::lockForUpdate()->where([
                    'counter_id' => $movement->counter_id,
                    'currency_code' => $movement->currency_code,
                    'denomination_id' => $movement->denomination_id,
                ])->first();

                if (!$stock) {
                    continue;
                }

                $reverseAmount = -((float) $movement->amount);

                // If reversing a buy (positive amount → now subtracting), recalculate avg cost
                if ($movement->movement_type === 'buy') {
                    $newQty = (float) $stock->quantity + $reverseAmount; // decreasing
                    if ($newQty > 0) {
                        // Recalculate avg cost from remaining movements
                        $stock->avg_cost = $this->recalculateAvgCost(
                            $stock->counter_id,
                            $stock->currency_code,
                            $stock->denomination_id,
                            $transactionId
                        );
                    } else {
                        $stock->avg_cost = 0;
                    }
                    $stock->quantity = $newQty;
                } else {
                    // Reversing a sell: just add back
                    $stock->quantity = (float) $stock->quantity + $reverseAmount;
                }

                $stock->total_cost_value = $stock->quantity * (float) $stock->avg_cost;
                $stock->save();

                // Record adjustment movement
                StockMovement::create([
                    'counter_id' => $movement->counter_id,
                    'currency_code' => $movement->currency_code,
                    'denomination_id' => $movement->denomination_id,
                    'movement_type' => 'adjustment',
                    'amount' => $reverseAmount,
                    'unit_price' => $movement->unit_price,
                    'reference_type' => 'transaction',
                    'reference_id' => $transactionId,
                    'note' => 'ยกเลิกรายการ (Cancel reversal)',
                    'moved_by' => $userId,
                    'moved_at' => now(),
                ]);
            }
        });
    }

    /**
     * Transfer stock between counters (borrow, return, disbursement, intraday_return).
     * Decreases from_counter stock and increases to_counter stock.
     */
    public function transferStock(
        string $transferType,
        int $fromCounterId,
        int $toCounterId,
        string $currencyCode,
        int $denominationId,
        float $amount,
        int $userId,
        ?string $note = null,
        ?int $relatedTransferId = null,
    ): StockTransfer {
        return DB::transaction(function () use ($transferType, $fromCounterId, $toCounterId, $currencyCode, $denominationId, $amount, $userId, $note, $relatedTransferId) {

            // Map transfer_type to movement_type
            $outMovementType = match ($transferType) {
                'borrow' => 'transfer_out',
                'return' => 'transfer_out',
                'disbursement' => 'transfer_out',
                'intraday_return' => 'transfer_out',
                default => 'transfer_out',
            };
            $inMovementType = match ($transferType) {
                'borrow' => 'transfer_in',
                'return' => 'transfer_in',
                'disbursement' => 'transfer_in',
                'intraday_return' => 'transfer_in',
                default => 'transfer_in',
            };

            // Get avg cost from source counter
            $fromStock = CounterStock::lockForUpdate()->firstOrCreate(
                ['counter_id' => $fromCounterId, 'currency_code' => $currencyCode, 'denomination_id' => $denominationId],
                ['quantity' => 0, 'hold_amount' => 0, 'avg_cost' => 0, 'total_cost_value' => 0]
            );
            if (!$fromStock->wasRecentlyCreated) {
                $fromStock = CounterStock::lockForUpdate()->find($fromStock->id);
            }

            $unitPrice = (float) $fromStock->avg_cost;

            // Decrease source counter stock
            $fromStock->quantity = (float) $fromStock->quantity - $amount;
            $fromStock->total_cost_value = $fromStock->quantity * $unitPrice;
            $fromStock->save();

            // Increase destination counter stock (with weighted avg cost)
            $toStock = CounterStock::lockForUpdate()->firstOrCreate(
                ['counter_id' => $toCounterId, 'currency_code' => $currencyCode, 'denomination_id' => $denominationId],
                ['quantity' => 0, 'hold_amount' => 0, 'avg_cost' => 0, 'total_cost_value' => 0]
            );
            if (!$toStock->wasRecentlyCreated) {
                $toStock = CounterStock::lockForUpdate()->find($toStock->id);
            }

            $oldToQty = (float) $toStock->quantity;
            $oldToAvg = (float) $toStock->avg_cost;
            $newToQty = $oldToQty + $amount;

            if ($newToQty > 0) {
                $toStock->avg_cost = ($oldToQty * $oldToAvg + $amount * $unitPrice) / $newToQty;
            }
            $toStock->quantity = $newToQty;
            $toStock->total_cost_value = $toStock->quantity * (float) $toStock->avg_cost;
            $toStock->save();

            // Create transfer record
            $transfer = StockTransfer::create([
                'transfer_no' => StockTransfer::generateTransferNo($transferType),
                'transfer_type' => $transferType,
                'from_counter_id' => $fromCounterId,
                'to_counter_id' => $toCounterId,
                'currency_code' => $currencyCode,
                'denomination_id' => $denominationId,
                'amount' => $amount,
                'unit_price' => $unitPrice,
                'status' => 'completed',
                'related_transfer_id' => $relatedTransferId,
                'note' => $note,
                'created_by' => $userId,
                'transferred_at' => now(),
            ]);

            // Record stock movements for both counters
            StockMovement::create([
                'counter_id' => $fromCounterId,
                'currency_code' => $currencyCode,
                'denomination_id' => $denominationId,
                'movement_type' => $outMovementType,
                'amount' => -$amount,
                'unit_price' => $unitPrice,
                'reference_type' => 'transfer',
                'reference_id' => $transfer->id,
                'note' => $note ?? $this->transferNote($transferType, 'out'),
                'moved_by' => $userId,
                'moved_at' => now(),
            ]);

            StockMovement::create([
                'counter_id' => $toCounterId,
                'currency_code' => $currencyCode,
                'denomination_id' => $denominationId,
                'movement_type' => $inMovementType,
                'amount' => $amount,
                'unit_price' => $unitPrice,
                'reference_type' => 'transfer',
                'reference_id' => $transfer->id,
                'note' => $note ?? $this->transferNote($transferType, 'in'),
                'moved_by' => $userId,
                'moved_at' => now(),
            ]);

            // Auto GL Journal
            app(AutoJournalService::class)->createFromTransfer($transfer);

            return $transfer;
        });
    }

    /**
     * Cancel a completed transfer — reverse stock movements and restore quantities.
     */
    public function cancelTransfer(int $transferId, int $userId): StockTransfer
    {
        return DB::transaction(function () use ($transferId, $userId) {
            $transfer = StockTransfer::lockForUpdate()->findOrFail($transferId);

            if ($transfer->status !== 'completed') {
                throw new \RuntimeException('ไม่สามารถยกเลิกรายการที่ไม่ใช่สถานะ completed');
            }

            $amount       = (float) $transfer->amount;
            $unitPrice    = (float) $transfer->unit_price;
            $currencyCode = $transfer->currency_code;
            $denomId      = $transfer->denomination_id;

            // Reverse: add back to source (from_counter)
            $fromStock = CounterStock::lockForUpdate()->firstOrCreate(
                ['counter_id' => $transfer->from_counter_id, 'currency_code' => $currencyCode, 'denomination_id' => $denomId],
                ['quantity' => 0, 'hold_amount' => 0, 'avg_cost' => 0, 'total_cost_value' => 0]
            );
            if (!$fromStock->wasRecentlyCreated) {
                $fromStock = CounterStock::lockForUpdate()->find($fromStock->id);
            }

            $oldFromQty = (float) $fromStock->quantity;
            $oldFromAvg = (float) $fromStock->avg_cost;
            $newFromQty = $oldFromQty + $amount;
            if ($newFromQty > 0) {
                $fromStock->avg_cost = ($oldFromQty * $oldFromAvg + $amount * $unitPrice) / $newFromQty;
            }
            $fromStock->quantity = $newFromQty;
            $fromStock->total_cost_value = $fromStock->quantity * (float) $fromStock->avg_cost;
            $fromStock->save();

            // Reverse: subtract from destination (to_counter)
            $toStock = CounterStock::lockForUpdate()->firstOrCreate(
                ['counter_id' => $transfer->to_counter_id, 'currency_code' => $currencyCode, 'denomination_id' => $denomId],
                ['quantity' => 0, 'hold_amount' => 0, 'avg_cost' => 0, 'total_cost_value' => 0]
            );
            if (!$toStock->wasRecentlyCreated) {
                $toStock = CounterStock::lockForUpdate()->find($toStock->id);
            }
            $toStock->quantity = (float) $toStock->quantity - $amount;
            $toStock->total_cost_value = $toStock->quantity * (float) $toStock->avg_cost;
            $toStock->save();

            // Record reversal movements
            StockMovement::create([
                'counter_id'     => $transfer->from_counter_id,
                'currency_code'  => $currencyCode,
                'denomination_id' => $denomId,
                'movement_type'  => 'transfer_in',
                'amount'         => $amount,
                'unit_price'     => $unitPrice,
                'reference_type' => 'transfer_cancel',
                'reference_id'   => $transfer->id,
                'note'           => 'ยกเลิกรายการ ' . $transfer->transfer_no,
                'moved_by'       => $userId,
                'moved_at'       => now(),
            ]);

            StockMovement::create([
                'counter_id'     => $transfer->to_counter_id,
                'currency_code'  => $currencyCode,
                'denomination_id' => $denomId,
                'movement_type'  => 'transfer_out',
                'amount'         => -$amount,
                'unit_price'     => $unitPrice,
                'reference_type' => 'transfer_cancel',
                'reference_id'   => $transfer->id,
                'note'           => 'ยกเลิกรายการ ' . $transfer->transfer_no,
                'moved_by'       => $userId,
                'moved_at'       => now(),
            ]);

            // Mark transfer as cancelled
            $transfer->update(['status' => 'cancelled']);

            // Reverse GL journal if exists
            $originalJournal = \App\Models\JournalEntry::where('source_type', 'transfer')
                ->where('source_id', $transfer->id)->first();
            if ($originalJournal) {
                $originalJournal->load('lines');
                app(AutoJournalService::class)->createReversal($originalJournal);
            }

            return $transfer;
        });
    }

    private function transferNote(string $type, string $direction): string
    {
        return match ($type) {
            'borrow' => $direction === 'out' ? 'ให้ยืม' : 'รับยืม',
            'return' => $direction === 'out' ? 'คืนสินค้า' : 'รับคืนสินค้า',
            'disbursement' => $direction === 'out' ? 'เบิกจ่ายออก' : 'รับเบิกจ่าย',
            'intraday_return' => $direction === 'out' ? 'คืนระหว่างวัน' : 'รับคืนระหว่างวัน',
            default => $type,
        };
    }

    /**
     * Snapshot opening balances for a counter when opening the day.
     * Copies current counter_stock into inventory table with opening_balance.
     */
    public function openDay(int $counterId, string $date): void
    {
        $stocks = CounterStock::where('counter_id', $counterId)
            ->whereNotNull('denomination_id')
            ->where('quantity', '!=', 0)
            ->get();

        $counter = \App\Models\Counter::find($counterId);

        foreach ($stocks as $stock) {
            Inventory::updateOrCreate(
                [
                    'counter_id' => $counterId,
                    'branch_id' => $counter->branch_id,
                    'currency_code' => $stock->currency_code,
                    'denomination_id' => $stock->denomination_id,
                    'date' => $date,
                ],
                [
                    'opening_balance' => (float) $stock->quantity,
                    'avg_cost' => (float) $stock->avg_cost,
                ]
            );
        }
    }

    /**
     * Calculate and save closing balances when closing the day.
     */
    public function closeDay(int $counterId, string $date): void
    {
        $cutoff = Setting::get('WORKING_CUT_OFF', '03:00:00');
        $dateStart = "{$date} {$cutoff}";
        $dateEnd = Carbon::parse($date)->addDay()->format('Y-m-d') . " {$cutoff}";

        // Get today's movements grouped by denomination
        $movements = StockMovement::where('counter_id', $counterId)
            ->whereNotNull('denomination_id')
            ->where('moved_at', '>', $dateStart)
            ->where('moved_at', '<=', $dateEnd)
            ->selectRaw('
                denomination_id, currency_code,
                SUM(CASE WHEN movement_type = "buy" THEN amount ELSE 0 END) as buy_total,
                SUM(CASE WHEN movement_type = "sell" THEN ABS(amount) ELSE 0 END) as sell_total,
                SUM(CASE WHEN movement_type = "transfer_in" THEN amount ELSE 0 END) as transfer_in,
                SUM(CASE WHEN movement_type = "transfer_out" THEN ABS(amount) ELSE 0 END) as transfer_out
            ')
            ->groupBy('denomination_id', 'currency_code')
            ->get();

        $counter = \App\Models\Counter::find($counterId);

        foreach ($movements as $mv) {
            $stock = CounterStock::where('counter_id', $counterId)
                ->where('denomination_id', $mv->denomination_id)
                ->first();

            $inv = Inventory::where('counter_id', $counterId)
                ->where('currency_code', $mv->currency_code)
                ->where('denomination_id', $mv->denomination_id)
                ->whereDate('date', $date)
                ->first();

            $data = [
                'buy_total' => (float) $mv->buy_total,
                'sell_total' => (float) $mv->sell_total,
                'transfer_in' => (float) $mv->transfer_in,
                'transfer_out' => (float) $mv->transfer_out,
                'closing_balance' => $stock ? (float) $stock->quantity : 0,
                'avg_cost' => $stock ? (float) $stock->avg_cost : 0,
                'total_value' => $stock ? (float) $stock->total_cost_value : 0,
            ];

            if ($inv) {
                $inv->update($data);
            } else {
                Inventory::create(array_merge($data, [
                    'counter_id' => $counterId,
                    'branch_id' => $counter->branch_id,
                    'currency_code' => $mv->currency_code,
                    'denomination_id' => $mv->denomination_id,
                    'date' => $date,
                    'opening_balance' => 0,
                ]));
            }
        }
    }

    /**
     * Recalculate weighted average cost from all buy movements,
     * excluding a specific transaction (being cancelled).
     */
    private function recalculateAvgCost(int $counterId, string $currencyCode, int $denominationId, int $excludeTransactionId): float
    {
        $buyMovements = StockMovement::where('counter_id', $counterId)
            ->where('currency_code', $currencyCode)
            ->where('denomination_id', $denominationId)
            ->where('movement_type', 'buy')
            ->where(function ($q) use ($excludeTransactionId) {
                $q->where('reference_type', '!=', 'transaction')
                    ->orWhere('reference_id', '!=', $excludeTransactionId);
            })
            ->selectRaw('SUM(amount) as total_qty, SUM(amount * unit_price) as total_value')
            ->first();

        if ($buyMovements && $buyMovements->total_qty > 0) {
            return $buyMovements->total_value / $buyMovements->total_qty;
        }

        return 0;
    }
}
