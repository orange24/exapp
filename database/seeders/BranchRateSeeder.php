<?php

namespace Database\Seeders;

use App\Models\Counter;
use App\Models\CounterRate;
use App\Models\CurrencyDenomination;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ตั้งเรทของวันนี้ให้ครบทุกสาขา โดยใช้เรทล่าสุดของคลังกลาง (สีลม) เป็นต้นแบบ
 *
 * ทุกสาขาได้เรทชุดเดียวกัน — ปรับแยกรายสาขาทีหลังได้ที่ /admin/rate หรือ
 * ตั้งส่วนต่างอัตโนมัติที่ /admin/rate-settings
 *
 * รันซ้ำได้: เขียนทับเรทของ counter+denom+วันนี้ ไม่สร้างแถวซ้ำ
 */
class BranchRateSeeder extends Seeder
{
    private const SOURCE_BRANCH_CODE = 'SILOM';

    public function run(): void
    {
        $sourceCounter = Counter::whereHas('branch', fn ($q) => $q->where('branch_code', self::SOURCE_BRANCH_CODE))
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if (! $sourceCounter) {
            $this->command?->error('ไม่พบเคาน์เตอร์ของคลังกลาง ' . self::SOURCE_BRANCH_CODE);
            return;
        }

        $baseRates = $this->latestRatesOf($sourceCounter->id);

        if ($baseRates->isEmpty()) {
            $this->command?->error('คลังกลางยังไม่มีเรทให้ใช้เป็นต้นแบบ');
            return;
        }

        $today = now()->format('Y-m-d');
        $setBy = User::whereHas('role', fn ($q) => $q->where('name', 'admin'))->value('id');
        $counters = Counter::where('is_active', true)->orderBy('id')->get();
        $written = 0;

        foreach ($counters as $counter) {
            foreach ($baseRates as $rate) {
                CounterRate::updateOrCreate(
                    [
                        'counter_id'      => $counter->id,
                        'denomination_id' => $rate->denomination_id,
                        'rate_date'       => $today,
                    ],
                    [
                        'currency_code'      => $rate->currency_code,
                        'rate_buy'           => $rate->rate_buy,
                        'rate_sell'          => $rate->rate_sell,
                        'sell_discount_rate' => $rate->sell_discount_rate,
                        'set_by'             => $setBy,
                    ]
                );
                $written++;
            }
        }

        $this->command?->info("ตั้งเรทวันที่ {$today} แล้ว {$written} รายการ ({$counters->count()} เคาน์เตอร์ × {$baseRates->count()} ธนบัตร)");
    }

    /**
     * เรทที่ใช้ได้ล่าสุดของแต่ละธนบัตร
     *
     * ต้องกรอง rate > 0 ใน subquery ด้วย ไม่ใช่กรองข้างนอก — เพราะบางธนบัตร
     * มีแถวใหม่กว่าที่เรทเป็น 0 (ตอนตั้งราคาแล้วเว้นว่างไว้) ถ้าหยิบแถวใหม่สุด
     * มาก่อนแล้วค่อยกรอง จะได้ศูนย์แถวและธนบัตรนั้นหายไปทั้งตัว
     */
    private function latestRatesOf(int $counterId)
    {
        $activeDenomIds = CurrencyDenomination::where('is_active', true)->pluck('id');

        return CounterRate::where('counter_id', $counterId)
            ->whereIn('denomination_id', $activeDenomIds)
            ->whereIn('id', function ($q) use ($counterId) {
                $q->selectRaw('MAX(id)')
                    ->from('counter_rates')
                    ->where('counter_id', $counterId)
                    ->whereNotNull('denomination_id')
                    ->where(function ($inner) {
                        $inner->where('rate_buy', '>', 0)->orWhere('rate_sell', '>', 0);
                    })
                    ->groupBy('denomination_id');
            })
            ->get();
    }
}
