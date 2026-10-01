<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionScreening;
use App\Services\Sanction\Dto\ScreeningInput;
use App\Services\Sanction\SanctionScreeningService;
use Illuminate\Console\Command;

/**
 * สแกนลูกค้าเดิมใหม่หลังรายชื่ออัปเดต
 *
 * ปกติ:     ลูกค้าทั้งหมด x เฉพาะ entry ที่ใหม่/เปลี่ยน  (--since)
 * ครั้งแรก: ลูกค้าทั้งหมด x entry ทั้งหมด                (--all, รันมือครั้งเดียว)
 *
 * ความต่างนี้คือระหว่าง "งาน 30 วินาทีทุกคืน" กับ "งาน 2 ชั่วโมงทุกคืน"
 * ผลลัพธ์เหมือนกัน เพราะลูกค้าที่ไม่เปลี่ยนเทียบกับ entry ที่ไม่เปลี่ยน ย่อมได้ผลเดิม
 *
 * รันแบบ synchronous ตลอด — ห้าม dispatch() เข้า queue เพราะ production
 * ไม่มี queue worker งานจะกองในตาราง jobs โดยเงียบสนิท
 */
class SanctionsRescan extends Command
{
    protected $signature = 'sanctions:rescan
                            {--all : สแกนลูกค้าทุกคนกับรายชื่อทั้งหมด (ใช้ครั้งแรกเท่านั้น)}
                            {--since= : สแกนกับ entry ที่ใหม่/เปลี่ยนตั้งแต่เวลานี้ (Y-m-d H:i:s)}
                            {--close-delisted : ปิด hit ที่ค้างอยู่ของรายชื่อที่ถูกเพิกถอน}';

    protected $description = 'สแกนลูกค้าเดิมใหม่กับรายชื่อบุคคลที่ถูกกำหนด';

    public function handle(SanctionScreeningService $service): int
    {
        if ($this->option('close-delisted')) {
            $closed = $this->closeDelistedHits();
            $this->info("ปิด hit ของรายชื่อที่ถูกเพิกถอน {$closed} รายการ");
        }

        $since = $this->option('since');
        $all = (bool) $this->option('all');

        if (! $all && $since === null) {
            $this->warn('ไม่ได้ระบุ --all หรือ --since — ไม่สแกนอะไร');

            return self::SUCCESS;
        }

        if (! $all && ! $this->hasChangedEntriesSince((string) $since)) {
            $this->info('ไม่มีรายชื่อที่เปลี่ยนตั้งแต่ ' . $since . ' — ข้ามการสแกน');

            return self::SUCCESS;
        }

        $scanned = 0;
        $flagged = 0;

        Customer::query()->chunkById(500, function ($customers) use ($service, &$scanned, &$flagged): void {
            foreach ($customers as $customer) {
                $input = ScreeningInput::fromCustomer($customer);

                if ($input->isEmpty()) {
                    continue;
                }

                $screening = $service->screen(
                    input: $input,
                    trigger: SanctionScreening::TRIGGER_RESCAN,
                    screenedBy: null,
                    customerId: $customer->id,
                );

                $scanned++;

                if ($screening->result !== SanctionScreening::RESULT_CLEAR) {
                    $flagged++;
                }
            }
        });

        $this->info("สแกนลูกค้า {$scanned} ราย พบที่ต้องตรวจสอบ {$flagged} ราย");

        return self::SUCCESS;
    }

    /**
     * ถ้าไม่มี entry ไหนเปลี่ยนเลย ผลการสแกนย่อมเหมือนเดิมทุกประการ
     * จึงข้ามได้ทั้งรอบ — นี่คือจุดที่ทำให้งานกลางคืนเหลือ 30 วินาที
     *
     * ดู updated_at ด้วย เพราะ SanctionSyncService แตะ entry เฉพาะตัวที่แถวบน
     * หน้า list ขยับ ตัวที่ไม่ขยับ source จะไม่ส่งกลับมาเลย updated_at จึงไม่ถูกดัน
     * (รวมถึงการ stamp delisted_at ตอนถูกถอดชื่อ ซึ่งก็ดัน updated_at เหมือนกัน)
     */
    private function hasChangedEntriesSince(string $since): bool
    {
        return SanctionEntry::where(function ($q) use ($since) {
            $q->where('first_seen_at', '>=', $since)
              ->orWhere('updated_at', '>=', $since);
        })->exists();
    }

    /**
     * รายชื่อถูกเพิกถอน -> hit ที่ค้างในคิวของคนนั้นปิดอัตโนมัติ
     * ไม่ต้องให้ admin มานั่งเคลียร์เคสที่ไม่เป็นเรื่องแล้ว
     */
    private function closeDelistedHits(): int
    {
        $closed = 0;

        $screenings = SanctionScreening::awaitingDecision()->with('matches.entry')->get();

        foreach ($screenings as $screening) {
            $entries = $screening->matches->map(fn ($m) => $m->entry)->filter();

            if ($entries->isEmpty() || $entries->contains(fn ($e) => $e->delisted_at === null)) {
                continue;
            }

            $when = $entries->map(fn ($e) => $e->delisted_at?->format('d/m/Y'))->filter()->first() ?? '-';

            $screening->update([
                'decision' => SanctionScreening::DECISION_FALSE_POSITIVE,
                'decided_at' => now(),
                'decision_reason' => "รายชื่อถูกเพิกถอนเมื่อ {$when} — ระบบปิดรายการอัตโนมัติ",
            ]);

            $closed++;
        }

        return $closed;
    }
}
