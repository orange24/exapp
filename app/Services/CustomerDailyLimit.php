<?php

namespace App\Services;

use App\Models\Counter;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;

/**
 * วงเงินต่อคนต่อวันของธุรกรรมหน้าเคาน์เตอร์ (รับซื้อ / จ่ายขาย แยกกัน)
 *
 * นับเฉพาะ transactions_master ซึ่งเป็นบิลหน้าเคาน์เตอร์ — ซื้อจาก/ขายให้
 * ธนาคารเก็บในตารางของตัวเอง จึงไม่ถูกนับโดยไม่ต้องกรองอะไร
 *
 * "คน" ดูจากเลขบัตร/พาสปอร์ต ไม่ใช่ customer_id — คนเดียวกันอาจถูกบันทึก
 * เป็นลูกค้าหลายแถว (สแกนพาสปอร์ตใหม่ แก้ชื่อ) ถ้านับตาม id แยกแถวก็หลบ
 * วงเงินได้ และนับรวมทุกสาขาของบริษัทเดียวกัน ไม่ใช่สาขาเดียว
 */
class CustomerDailyLimit
{
    public const BUYING = 'BUYING';
    public const SELLING = 'SELLING';

    public const DEFAULT_AMOUNT = 800000;

    /** @return array{enabled: bool, amount: float} */
    public static function config(string $type): array
    {
        $prefix = self::prefix($type);

        return [
            'enabled' => (bool) (int) Setting::get("{$prefix}_ENABLED", 1),
            'amount' => (float) Setting::get("{$prefix}_AMOUNT", self::DEFAULT_AMOUNT),
        ];
    }

    public static function save(string $type, bool $enabled, float $amount): void
    {
        $prefix = self::prefix($type);

        Setting::set("{$prefix}_ENABLED", $enabled ? '1' : '0');
        Setting::set("{$prefix}_AMOUNT", (string) $amount);
    }

    /**
     * ข้อความบอกเหตุที่บันทึกไม่ได้ หรือ null ถ้าผ่าน
     *
     * @param  string  $idNumber  เลขบัตร/พาสปอร์ตที่กรอกในฟอร์ม — ใช้เมื่อยังไม่ได้
     *                            เลือกลูกค้าเดิม (ลูกค้าใหม่ถูกสร้างตอนบันทึกบิล)
     */
    public function violation(string $type, ?int $customerId, string $idNumber, int $counterId, float $thbAmount): ?string
    {
        $config = self::config($type);
        if (! $config['enabled']) {
            return null;
        }

        $label = $type === self::BUYING ? 'รับซื้อ' : 'จ่ายขาย';

        $customer = $customerId ? Customer::find($customerId) : null;
        $idNumber = trim((string) ($customer?->id_number ?: $idNumber));

        if ($idNumber === '' && $customer === null) {
            return "ต้องเลือกหรือสแกนข้อมูลลูกค้าก่อนบันทึก — เปิดใช้วงเงิน{$label}ต่อคนต่อวันอยู่ จึงต้องรู้ว่าบิลนี้เป็นของใคร";
        }

        $used = $this->usedToday($type, $customer, $idNumber, $counterId);
        $after = $used + $thbAmount;

        if (round($after, 2) <= round($config['amount'], 2)) {
            return null;
        }

        $remaining = max(0, $config['amount'] - $used);

        return "เกินวงเงิน{$label}ต่อคนต่อวัน " . number_format($config['amount'], 2) . ' บาท — '
            . 'วันนี้ลูกค้าคนนี้ใช้ไปแล้ว ' . number_format($used, 2) . ' บาท '
            . 'บิลนี้ ' . number_format($thbAmount, 2) . ' บาท '
            . '(ทำได้อีกไม่เกิน ' . number_format($remaining, 2) . ' บาท)';
    }

    /**
     * ยอดบาทของวันนี้ ของคนเดียวกัน ในบริษัทเดียวกับเคาน์เตอร์นี้
     */
    public function usedToday(string $type, ?Customer $customer, string $idNumber, int $counterId): float
    {
        // transactions_detail สลับความหมายคอลัมน์ตามประเภทบิล —
        // BUYING: total = บาท / SELLING: amount = บาท
        $thbColumn = $type === self::BUYING ? 'd.total' : 'd.amount';

        $query = DB::table('transactions_master as m')
            ->join('transactions_detail as d', 'd.transaction_id', '=', 'm.id')
            ->join('counters as c', 'c.id', '=', 'm.counter_id')
            ->where('m.trns_type', $type)
            ->where('m.flag_cancel', '!=', 'Y')
            ->whereBetween('m.trns_datetime', [now()->startOfDay(), now()->endOfDay()]);

        $customerIds = $idNumber !== ''
            ? Customer::where('id_number', $idNumber)->pluck('id')->all()
            : [];
        if ($customer !== null) {
            $customerIds[] = $customer->id;
        }
        if ($customerIds === []) {
            return 0.0;
        }
        $query->whereIn('m.customer_id', array_unique($customerIds));

        $companyId = Counter::with('branch')->find($counterId)?->branch?->company_id;
        if ($companyId !== null) {
            $query->whereIn('c.branch_id', DB::table('branches')->where('company_id', $companyId)->select('id'));
        }

        return (float) $query->sum($thbColumn);
    }

    private static function prefix(string $type): string
    {
        return $type === self::BUYING ? 'DAILY_LIMIT_BUY' : 'DAILY_LIMIT_SELL';
    }
}
