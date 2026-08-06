<?php

namespace App\Services;

use App\Models\BankSale;
use App\Models\BankSaleSource;
use App\Models\Counter;
use App\Models\CounterStock;
use App\Models\RateChangeLog;
use App\Models\StockMovement;
use App\Models\TransactionAccountMapping;
use App\Models\TransactionMaster;
use App\Models\TransactionDetail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class BankSaleService
{
    /**
     * Create a bank sale and reserve stock from selected counters.
     *
     * @param array $data ['bank_name', 'bank_account', 'currency_code', 'denomination_id', 'total_amount', 'bank_rate', 'settlement_method', 'notes']
     * @param array $sources [['counter_id' => int, 'amount' => float], ...]
     */
    public function create(array $data, array $sources, int $userId): BankSale
    {
        if (($data['direction'] ?? BankSale::DIRECTION_SELL) === BankSale::DIRECTION_BUY) {
            return $this->createPurchase($data, $sources, $userId);
        }

        return DB::transaction(function () use ($data, $sources, $userId) {
            $totalAmount = (float) $data['total_amount'];
            $bankRate = (float) $data['bank_rate'];
            $totalThb = round($totalAmount * $bankRate, 2);

            // Calculate weighted avg cost from all sources
            $totalCostValue = 0;
            $sourceRecords = [];

            foreach ($sources as $src) {
                $counterId = (int) $src['counter_id'];
                $amount = (float) $src['amount'];
                if ($amount <= 0) continue;

                // Get avg cost + check availability
                $stock = CounterStock::lockForUpdate()
                    ->where('counter_id', $counterId)
                    ->where('denomination_id', $data['denomination_id'])
                    ->first();

                if (! $stock) {
                    throw new \RuntimeException("ไม่พบสต็อกของเคาน์เตอร์ " . Counter::find($counterId)?->counter_name);
                }

                $available = (float) $stock->quantity - (float) $stock->hold_amount;
                if ($amount > $available) {
                    $counterName = Counter::find($counterId)?->counter_name ?? $counterId;
                    throw new \RuntimeException("สต็อกไม่เพียงพอที่ {$counterName} (Available: " . number_format($available, 2) . ")");
                }

                $avgCost = (float) $stock->avg_cost;
                $totalCostValue += $amount * $avgCost;

                // Reserve: increase hold_amount
                $stock->increment('hold_amount', $amount);

                $sourceRecords[] = [
                    'counter_id' => $counterId,
                    'amount' => $amount,
                    'avg_cost' => $avgCost,
                ];
            }

            $weightedAvgCost = $totalAmount > 0 ? round($totalCostValue / $totalAmount, 6) : 0;
            $totalCost = round($totalAmount * $weightedAvgCost, 2);
            $profitLoss = round($totalThb - $totalCost, 2);

            $sale = BankSale::create([
                'sale_no' => BankSale::generateSaleNo(BankSale::DIRECTION_SELL),
                'direction' => BankSale::DIRECTION_SELL,
                'bank_name' => $data['bank_name'],
                'bank_account' => $data['bank_account'] ?? null,
                'currency_code' => $data['currency_code'],
                'denomination_id' => $data['denomination_id'] ?? null,
                'total_amount' => $totalAmount,
                'bank_rate' => $bankRate,
                'total_thb' => $totalThb,
                'avg_cost_at_sale' => $weightedAvgCost,
                'total_cost' => $totalCost,
                'profit_loss' => $profitLoss,
                'settlement_method' => $data['settlement_method'] ?? 'bank_transfer',
                'status' => BankSale::STATUS_RESERVED,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($sourceRecords as $src) {
                BankSaleSource::create([
                    'bank_sale_id' => $sale->id,
                    'counter_id' => $src['counter_id'],
                    'amount' => $src['amount'],
                    'avg_cost' => $src['avg_cost'],
                    'status' => 'reserved',
                ]);
            }

            return $sale;
        });
    }

    /**
     * Create a bank PURCHASE — เราจ่าย THB ให้ธนาคาร แล้วรับเงินตราเข้าเคาน์เตอร์ปลายทาง
     *
     * ต่างจากฝั่งขายตรงที่ไม่มีอะไรให้ reserve: สต็อกยังไม่มี จะเข้าตอน complete()
     * เท่านั้น จึงไม่แตะ hold_amount และไม่มีขั้น in_transit/delivered
     *
     * @param array $sources [['counter_id' => int, 'amount' => float], ...] = ปลายทางที่จะรับเข้า
     */
    public function createPurchase(array $data, array $sources, int $userId): BankSale
    {
        return DB::transaction(function () use ($data, $sources, $userId) {
            $totalAmount = (float) $data['total_amount'];
            $bankRate = (float) $data['bank_rate'];
            $totalThb = round($totalAmount * $bankRate, 2);

            $destinationRecords = [];
            foreach ($sources as $src) {
                $amount = (float) $src['amount'];
                if ($amount <= 0) continue;

                $counterId = (int) $src['counter_id'];
                if (! Counter::find($counterId)) {
                    throw new \RuntimeException("ไม่พบเคาน์เตอร์ปลายทาง #{$counterId}");
                }

                $destinationRecords[] = ['counter_id' => $counterId, 'amount' => $amount];
            }

            if (empty($destinationRecords)) {
                throw new \RuntimeException('กรุณาระบุเคาน์เตอร์ปลายทางอย่างน้อย 1 แห่ง');
            }

            $purchase = BankSale::create([
                'sale_no' => BankSale::generateSaleNo(BankSale::DIRECTION_BUY),
                'direction' => BankSale::DIRECTION_BUY,
                'bank_name' => $data['bank_name'],
                'bank_account' => $data['bank_account'] ?? null,
                'currency_code' => $data['currency_code'],
                'denomination_id' => $data['denomination_id'] ?? null,
                'total_amount' => $totalAmount,
                'bank_rate' => $bankRate,
                'total_thb' => $totalThb,
                // ซื้อเข้าไม่มีกำไร/ขาดทุน — เงินที่จ่ายคือต้นทุนของของที่ได้มา
                'avg_cost_at_sale' => $bankRate,
                'total_cost' => $totalThb,
                'profit_loss' => 0,
                'settlement_method' => $data['settlement_method'] ?? 'bank_transfer',
                'status' => BankSale::STATUS_ORDERED,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($destinationRecords as $dest) {
                BankSaleSource::create([
                    'bank_sale_id' => $purchase->id,
                    'counter_id' => $dest['counter_id'],
                    'amount' => $dest['amount'],
                    'avg_cost' => $bankRate,
                    'status' => BankSale::STATUS_ORDERED,
                ]);
            }

            return $purchase;
        });
    }

    /**
     * ยืนยันรับของจากธนาคาร — เพิ่มสต็อกเข้าเคาน์เตอร์ปลายทาง + GL
     */
    public function completePurchase(BankSale $purchase, int $userId): void
    {
        if ($purchase->status !== BankSale::STATUS_ORDERED) {
            throw new \RuntimeException('สถานะไม่ถูกต้องสำหรับการยืนยันรับของ');
        }

        $this->assertSettlementSpecified($purchase);

        DB::transaction(function () use ($purchase, $userId) {
            $purchase->load('sources.counter');

            foreach ($purchase->sources as $dest) {
                // สูตร weighted avg cost อยู่ที่เดียวใน InventoryService
                app(InventoryService::class)->applyReceipt(
                    counterId: $dest->counter_id,
                    currencyCode: $purchase->currency_code,
                    denominationId: (int) $purchase->denomination_id,
                    amount: (float) $dest->amount,
                    unitCost: (float) $purchase->bank_rate,
                    referenceType: 'bank_purchase',
                    referenceId: $purchase->id,
                    userId: $userId,
                    note: "ซื้อจากธนาคาร {$purchase->sale_no} ← {$purchase->bank_name}",
                );

                $dest->update(['status' => 'delivered', 'delivered_at' => now()]);
            }

            $entry = app(AutoJournalService::class)->createFromBankPurchase($purchase);

            $purchase->update([
                'status' => BankSale::STATUS_COMPLETED,
                'journal_entry_id' => $entry?->id,
                'approved_by' => $userId,
                'approved_at' => now(),
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * วิธีรับ/จ่ายเงินต้องระบุก่อนปิดรายการ — 'ระบุภายหลัง' ค้างไว้ไม่ได้
     * บังคับที่ service ไม่ใช่แค่ปุ่มใน UI
     */
    private function assertSettlementSpecified(BankSale $sale): void
    {
        if ($sale->settlement_method === BankSale::SETTLEMENT_PENDING) {
            throw new \RuntimeException('กรุณาระบุวิธีรับ/จ่ายเงินก่อนยืนยันรายการ (ตอนนี้เป็น "ระบุภายหลัง")');
        }
    }

    /**
     * Mark source(s) as in-transit.
     */
    public function markInTransit(BankSale $sale, ?array $sourceIds = null): void
    {
        $this->assertSellOnly($sale, 'ขนส่ง');

        $query = $sale->sources()->where('status', 'reserved');
        if ($sourceIds) {
            $query->whereIn('id', $sourceIds);
        }
        $query->update(['status' => 'in_transit', 'transit_at' => now()]);

        // Update sale status if all sources are in_transit or beyond
        if ($sale->sources()->where('status', 'reserved')->count() === 0) {
            $sale->update(['status' => BankSale::STATUS_IN_TRANSIT]);
        }
    }

    /**
     * Mark source(s) as delivered.
     */
    public function markDelivered(BankSale $sale, ?array $sourceIds = null): void
    {
        $this->assertSellOnly($sale, 'ส่งถึง');

        $query = $sale->sources()->whereIn('status', ['reserved', 'in_transit']);
        if ($sourceIds) {
            $query->whereIn('id', $sourceIds);
        }
        $query->update(['status' => 'delivered', 'delivered_at' => now()]);

        if ($sale->sources()->whereNotIn('status', ['delivered'])->count() === 0) {
            $sale->update(['status' => BankSale::STATUS_DELIVERED]);
        }
    }

    /**
     * Complete the bank sale — cut stock, create GL journal.
     */
    public function complete(BankSale $sale, int $userId): void
    {
        if ($sale->isBuy()) {
            $this->completePurchase($sale, $userId);
            return;
        }

        if (! in_array($sale->status, [BankSale::STATUS_RESERVED, BankSale::STATUS_IN_TRANSIT, BankSale::STATUS_DELIVERED])) {
            throw new \RuntimeException('สถานะไม่ถูกต้องสำหรับการยืนยันขาย');
        }

        $this->assertSettlementSpecified($sale);

        DB::transaction(function () use ($sale, $userId) {
            $sale->load('sources.counter');

            foreach ($sale->sources as $source) {
                $stock = CounterStock::lockForUpdate()
                    ->where('counter_id', $source->counter_id)
                    ->where('denomination_id', $sale->denomination_id)
                    ->first();

                if ($stock) {
                    // Release hold + cut actual stock
                    $stock->hold_amount = max(0, (float) $stock->hold_amount - $source->amount);
                    $stock->quantity = (float) $stock->quantity - $source->amount;
                    $stock->total_cost_value = $stock->quantity * (float) $stock->avg_cost;
                    $stock->save();
                }

                // Record stock movement
                StockMovement::create([
                    'counter_id' => $source->counter_id,
                    'currency_code' => $sale->currency_code,
                    'denomination_id' => $sale->denomination_id,
                    'movement_type' => 'sell',
                    'amount' => -$source->amount,
                    'unit_price' => $sale->bank_rate,
                    'reference_type' => 'bank_sale',
                    'reference_id' => $sale->id,
                    'note' => "ขายธนาคาร {$sale->sale_no} → {$sale->bank_name}",
                    'moved_by' => $userId,
                    'moved_at' => now(),
                ]);

                // Rate change log
                RateChangeLog::logChange(
                    counterId: $source->counter_id,
                    denomId: (int) $sale->denomination_id,
                    currencyCode: $sale->currency_code,
                    oldBuy: null,
                    oldSell: null,
                    newBuy: 0,
                    newSell: $sale->bank_rate,
                    source: 'bank_sale',
                    ref: $sale->sale_no,
                );

                $source->update(['status' => 'delivered', 'delivered_at' => now()]);
            }

            // Recalculate P&L with final avg cost
            $totalCostValue = $sale->sources->sum(fn ($s) => $s->amount * $s->avg_cost);
            $avgCost = $sale->total_amount > 0 ? round($totalCostValue / $sale->total_amount, 6) : 0;
            $totalCost = round($sale->total_amount * $avgCost, 2);
            $profitLoss = round($sale->total_thb - $totalCost, 2);

            // Auto GL Journal
            $entry = app(AutoJournalService::class)->createFromBankSale($sale, $totalCost, $profitLoss);

            $sale->update([
                'status' => BankSale::STATUS_COMPLETED,
                'avg_cost_at_sale' => $avgCost,
                'total_cost' => $totalCost,
                'profit_loss' => $profitLoss,
                'journal_entry_id' => $entry?->id,
                'approved_by' => $userId,
                'approved_at' => now(),
                'completed_at' => now(),
            ]);
        });
    }

    /**
     * Cancel the bank sale — release all reserved stock.
     */
    public function cancel(BankSale $sale, int $userId, ?string $reason = null): void
    {
        if ($sale->status === BankSale::STATUS_COMPLETED) {
            throw new \RuntimeException('ไม่สามารถยกเลิกรายการที่เสร็จสิ้นแล้ว');
        }

        DB::transaction(function () use ($sale, $userId, $reason) {
            $sale->load('sources');

            foreach ($sale->sources()->whereIn('status', ['reserved', 'in_transit', 'delivered', BankSale::STATUS_ORDERED])->get() as $source) {
                // ฝั่งซื้อไม่เคยจอง hold ไว้ — ถ้าเผลอ decrement จะทำให้ hold_amount
                // ติดลบถาวร แล้ว available เฟ้อเกินจริงตลอดไป
                if (! $sale->isBuy()) {
                    CounterStock::where('counter_id', $source->counter_id)
                        ->where('denomination_id', $sale->denomination_id)
                        ->decrement('hold_amount', $source->amount);
                }

                $source->update(['status' => 'released']);
            }

            $sale->update([
                'status' => BankSale::STATUS_CANCELLED,
                'notes' => $reason ? (($sale->notes ?? '') . "\nยกเลิก: {$reason}") : $sale->notes,
            ]);
        });
    }

    /** ขั้นขนส่ง/ส่งถึง มีเฉพาะฝั่งขาย — ฝั่งซื้อไปจาก ordered → completed ตรงๆ */
    private function assertSellOnly(BankSale $sale, string $action): void
    {
        if ($sale->isBuy()) {
            throw new \RuntimeException("รายการซื้อจากธนาคารไม่มีขั้นตอน \"{$action}\"");
        }
    }
}
