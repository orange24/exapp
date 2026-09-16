<?php

namespace App\Services;

use App\Models\CounterStock;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\WorkingDay;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * บัญชีเงินสดสกุลบาทของแต่ละเคาน์เตอร์
 *
 * เงินบาทไม่มีแถวในตาราง currencies และไม่มี denomination — มันถูกเก็บเป็น
 * counter_stock / stock_movements ที่ currency_code='THB' และ denomination_id
 * เป็น null ซึ่งทำให้ query ฝั่งเงินตราต่างประเทศ (ที่กรอง whereNotNull
 * ('denomination_id') อยู่แล้วแทบทุกจุด) มองไม่เห็นมันโดยอัตโนมัติ และหน้า
 * ตั้งเรท / dropdown buy-sell / rate board / SuperRich ที่วนจากตาราง currencies
 * ก็ไม่มีวันหยิบมันขึ้นมาแสดง
 *
 * ยอดคงเหลือคำนวณสดจาก movement เสมอ ไม่อ่านยอดปิดของวันก่อนหน้า ยอดยกมา
 * จึงถูกต้องแม้วันก่อนจะไม่ได้ปิดยอดหรือเว้นไปหลายสัปดาห์
 *
 * counter_stock ยังต้องมีแถว THB ด้วย ไม่ใช่แค่ cache — InventoryService::
 * reverseMovement() จะ `continue` ข้ามไปเฉยๆ ถ้าหาแถว counter_stock ไม่เจอ
 * แปลว่าการยกเลิกบิลจะไม่คืนเงินบาทกลับถ้าไม่มีแถวนี้
 */
class ThbCashService
{
    public const REF_TRANSACTION = 'transaction';

    /** เงินทุนเข้าลิ้นชักจากภายนอกบัญชีนี้ (บัญชีธนาคาร/เจ้าของ) */
    public const REF_TOPUP = 'thb_topup';

    /** นำเงินบาทออกจากลิ้นชักไปนอกบัญชีนี้ (ฝากเข้าบัญชี/ส่งคืน) */
    public const REF_RETURN = 'thb_return';

    public const REF_ADJUST = 'thb_adjust';
    public const REF_WORKING_DAY = 'working_day';

    private ?string $cutoff = null;

    // ─────────────────────────────────────────────────────────────────
    // ขอบเขตวันทำการ — ต้องตรงกับ InventoryService::closeDay() เป๊ะๆ
    // ช่วงของวัน D คือ (dayStart(D), dayEnd(D)] — เปิดซ้าย ปิดขวา
    // ─────────────────────────────────────────────────────────────────

    public function cutoff(): string
    {
        return $this->cutoff ??= (string) Setting::get('WORKING_CUT_OFF', '03:00:00');
    }

    public function dayStart(string|Carbon $date): Carbon
    {
        return Carbon::parse(Carbon::parse($date)->format('Y-m-d') . ' ' . $this->cutoff());
    }

    public function dayEnd(string|Carbon $date): Carbon
    {
        return $this->dayStart($date)->addDay();
    }

    // ─────────────────────────────────────────────────────────────────
    // ยอดคงเหลือ
    // ─────────────────────────────────────────────────────────────────

    /** ยอดเงินบาทในลิ้นชักตอนนี้ */
    public function balance(int $counterId): float
    {
        return $this->balanceAsOf($counterId, now());
    }

    /**
     * ยอด ณ เวลาใดเวลาหนึ่ง — รวม movement ทุกแถวที่ moved_at <= $at
     *
     * ใช้ <= (ไม่ใช่ <) เพื่อให้เข้ากับขอบเขตวันทำการ: movement ที่เกิดตรง
     * dayEnd(D) พอดี ถือว่าอยู่ในวัน D และกลายเป็นยอดยกมาของวัน D+1
     */
    public function balanceAsOf(int $counterId, string|Carbon $at): float
    {
        return (float) StockMovement::query()
            ->thb()
            ->where('counter_id', $counterId)
            ->where('moved_at', '<=', Carbon::parse($at))
            ->sum('amount');
    }

    /** ยอดยกมาของวันทำการ */
    public function openingFor(int $counterId, string|Carbon $date): float
    {
        return $this->balanceAsOf($counterId, $this->dayStart($date));
    }

    /** ยอดปลายวันตามระบบ (ก่อนนับเงินจริง) */
    public function expectedClosingFor(int $counterId, string|Carbon $date): float
    {
        return $this->balanceAsOf($counterId, $this->dayEnd($date));
    }

    /**
     * สรุปความเคลื่อนไหวของเงินบาทในวันหนึ่ง
     *
     * @return array{opening: float, in: float, out: float, closing: float}
     */
    public function summaryFor(int $counterId, string|Carbon $date): array
    {
        $opening = $this->openingFor($counterId, $date);

        $rows = StockMovement::query()
            ->thb()
            ->where('counter_id', $counterId)
            ->where('moved_at', '>', $this->dayStart($date))
            ->where('moved_at', '<=', $this->dayEnd($date))
            ->selectRaw('
                SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as total_in,
                SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END) as total_out
            ')
            ->first();

        $in = (float) ($rows->total_in ?? 0);
        $out = (float) ($rows->total_out ?? 0); // ติดลบอยู่แล้ว

        return [
            'opening' => $opening,
            'in' => $in,
            'out' => $out,
            'closing' => $opening + $in + $out,
        ];
    }

    /** จ่ายเงินบาทออกไปเท่านี้แล้วยอดจะติดลบไหม (ใช้เตือน ไม่ใช้บล็อก) */
    public function wouldGoNegative(int $counterId, float $payout): bool
    {
        return $this->balance($counterId) - abs($payout) < 0;
    }

    // ─────────────────────────────────────────────────────────────────
    // การเขียน movement
    // ─────────────────────────────────────────────────────────────────

    /**
     * บันทึกความเคลื่อนไหวเงินบาท 1 รายการ พร้อมอัปเดตยอดใน counter_stock
     *
     * $signedAmount ติดลบ = เงินออกจากลิ้นชัก, เป็นบวก = เงินเข้า
     * ไม่บล็อกยอดติดลบ — ข้อมูลเงินทุนตั้งต้นในระบบมักไม่ครบ การบล็อกจะทำให้
     * หน้าเคาน์เตอร์ทำงานไม่ได้ ผู้เรียกควรเตือนผู้ใช้เองผ่าน wouldGoNegative()
     */
    public function apply(
        int $counterId,
        string $movementType,
        float $signedAmount,
        string $referenceType,
        ?int $referenceId,
        int $userId,
        ?string $note = null,
        string|Carbon|null $at = null,
    ): StockMovement {
        return DB::transaction(function () use (
            $counterId, $movementType, $signedAmount, $referenceType, $referenceId, $userId, $note, $at
        ) {
            $stock = CounterStock::lockForUpdate()->firstOrCreate(
                [
                    'counter_id' => $counterId,
                    'currency_code' => CounterStock::THB,
                    'denomination_id' => null,
                ],
                ['quantity' => 0, 'hold_amount' => 0, 'avg_cost' => 1, 'total_cost_value' => 0]
            );

            if (! $stock->wasRecentlyCreated) {
                $stock = CounterStock::lockForUpdate()->find($stock->id);
            }

            // เงินบาทไม่มีต้นทุน — avg_cost ตรึงไว้ที่ 1 เพื่อให้ total_cost_value
            // เท่ากับจำนวนเงินเสมอ และเพื่อให้โค้ดเดิมที่คูณด้วย avg_cost
            // (เช่น reverseMovement) ได้ค่าที่ถูกต้องโดยไม่ต้องรู้จัก THB
            $stock->avg_cost = 1;
            $stock->quantity = (float) $stock->quantity + $signedAmount;
            $stock->total_cost_value = $stock->quantity;
            $stock->save();

            return StockMovement::create([
                'counter_id' => $counterId,
                'currency_code' => StockMovement::THB,
                'denomination_id' => null,
                'movement_type' => $movementType,
                'amount' => $signedAmount,
                'unit_price' => 1,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'note' => $note,
                'moved_by' => $userId,
                'moved_at' => $at ? Carbon::parse($at) : now(),
            ]);
        });
    }

    /** รับซื้อเงินตราต่างประเทศ = จ่ายบาทออกจากลิ้นชัก */
    public function recordPurchasePayment(int $counterId, float $thbPaid, int $transactionId, int $userId): ?StockMovement
    {
        if ($thbPaid <= 0) {
            return null;
        }

        return $this->apply(
            $counterId,
            StockMovement::THB_OUT,
            -abs($thbPaid),
            self::REF_TRANSACTION,
            $transactionId,
            $userId,
            'จ่ายบาทจากการรับซื้อเงินตราต่างประเทศ',
        );
    }

    /** ขายเงินตราต่างประเทศ = รับบาทเข้าลิ้นชัก */
    public function recordSaleReceipt(int $counterId, float $thbReceived, int $transactionId, int $userId): ?StockMovement
    {
        if ($thbReceived <= 0) {
            return null;
        }

        return $this->apply(
            $counterId,
            StockMovement::THB_IN,
            abs($thbReceived),
            self::REF_TRANSACTION,
            $transactionId,
            $userId,
            'รับบาทจากการขายเงินตราต่างประเทศ',
        );
    }

    /** ปรับปรุงยอดเงินบาทด้วยมือ */
    public function adjust(int $counterId, float $signedAmount, int $userId, string $note): StockMovement
    {
        return $this->apply($counterId, 'adjustment', $signedAmount, self::REF_ADJUST, null, $userId, $note);
    }

    /**
     * เติมเงินทุนเข้าลิ้นชัก — ตัดขาเดียว
     *
     * ที่มาของเงินคือบัญชีธนาคาร/เจ้าของ ซึ่งอยู่นอกขอบเขตบัญชีเงินสดของ
     * เคาน์เตอร์ จึงไม่มีขาหักออกจากที่ไหน หลักเดียวกับที่การซื้อ/ขายกับ
     * ธนาคารไม่ถูกนับในบัญชีนี้ เพราะจ่าย-รับผ่านบัญชีไม่ผ่านลิ้นชัก
     */
    public function topUp(int $counterId, float $amount, int $userId, ?string $note = null): StockMovement
    {
        $this->assertPositive($amount);

        return $this->apply(
            $counterId,
            StockMovement::THB_IN,
            $amount,
            self::REF_TOPUP,
            null,
            $userId,
            $note ?: 'เติมเงินทุน',
        );
    }

    /** นำเงินบาทออกจากลิ้นชัก (ฝากเข้าบัญชี / ส่งคืนเจ้าของ) — ตัดขาเดียว */
    public function withdraw(int $counterId, float $amount, int $userId, ?string $note = null): StockMovement
    {
        $this->assertPositive($amount);

        return $this->apply(
            $counterId,
            StockMovement::THB_OUT,
            -$amount,
            self::REF_RETURN,
            null,
            $userId,
            $note ?: 'นำเงินบาทออกจากลิ้นชัก',
        );
    }

    private function assertPositive(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('จำนวนเงินต้องมากกว่า 0');
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // ปิดวัน
    // ─────────────────────────────────────────────────────────────────

    /**
     * โพสต์ผลต่างระหว่างเงินที่นับได้จริงกับยอดตามระบบ
     *
     * moved_at ต้องเป็น dayEnd ของวันที่ปิด ไม่ใช่ now() — ไม่งั้นถ้า admin
     * อนุมัติย้อนหลัง ผลต่างจะไปตกอยู่ในวันที่อนุมัติแทนที่จะเป็นวันที่ปิด
     * และยอดยกมาของวันถัดไปจะไม่เท่ากับเงินที่นับได้จริง
     *
     * เรียกซ้ำได้ — ถ้าเคยโพสต์ผลต่างของวันทำการนี้ไปแล้วจะไม่โพสต์ซ้ำ
     */
    public function postClosingVariance(WorkingDay $workingDay, int $userId): ?StockMovement
    {
        $variance = (float) ($workingDay->closing_thb_variance ?? 0);

        // ต่ำกว่าครึ่งสตางค์ถือว่าตรง ไม่ต้องสร้างแถวขยะ
        if (abs($variance) < 0.005) {
            return null;
        }

        $already = StockMovement::query()
            ->thb()
            ->where('reference_type', self::REF_WORKING_DAY)
            ->where('reference_id', $workingDay->id)
            ->exists();

        if ($already) {
            return null;
        }

        return $this->apply(
            (int) $workingDay->counter_id,
            'adjustment',
            $variance,
            self::REF_WORKING_DAY,
            (int) $workingDay->id,
            $userId,
            'ผลต่างยอดปิดเงินบาทวันที่ ' . Carbon::parse($workingDay->work_date)->format('d/m/Y'),
            $this->dayEnd($workingDay->work_date),
        );
    }

}
