<?php

namespace App\Console\Commands;

use App\Models\SanctionSyncRun;
use App\Services\Sanction\SanctionSyncService;
use App\Services\Sanction\Source\AmloPublicScraper;
use Illuminate\Console\Command;

/**
 * ห้ามลงทะเบียนคำสั่งนี้ใน routes/console.php
 *
 * exapp-scheduler รัน `schedule:run` ทุกนาที ถ้าลงทะเบียนไว้ที่นั่น
 * จะไปเรียกซ้ำกับ Cloud Run Job exapp-sanctions-sync กลายเป็นยิง server ปปง.
 * สองรอบพร้อมกัน — sync ต้องถูกเรียกจาก Job ของตัวเองเท่านั้น
 */
class SanctionsSync extends Command
{
    protected $signature = 'sanctions:sync
                            {--list=all : freeze_05_th | freeze_04_un | all}
                            {--force : ข้าม sanity check (ใช้เมื่อ ปปง. เพิกถอนรายชื่อครั้งใหญ่จริง)}
                            {--dry-run : รันถึงเฟส validate แล้วรายงานผล ไม่เขียน DB}';

    protected $description = 'ดึงรายชื่อบุคคลที่ถูกกำหนดจากเว็บสาธารณะ ปปง. เข้าฐานข้อมูล';

    public function handle(SanctionSyncService $service): int
    {
        $requested = (string) $this->option('list');
        $available = array_keys((array) config('sanction.amlo.lists'));

        if ($requested === 'all') {
            $lists = $available;
        } elseif (in_array($requested, $available, true)) {
            $lists = [$requested];
        } else {
            $this->error("ไม่รู้จัก list \"{$requested}\" — ใช้ได้: " . implode(', ', $available) . ', all');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($lists as $listCode) {
            $this->line("=== {$listCode} ===");

            $source = new AmloPublicScraper($listCode);

            if ($this->option('dry-run')) {
                $rows = $source->fetchListRows();
                $this->info("dry-run: หน้า list มี " . count($rows) . " แถว — ไม่เขียน DB");

                continue;
            }

            $run = $service->sync(
                source: $source,
                force: (bool) $this->option('force'),
                forcedBy: null,
            );

            $this->reportRun($run);

            if ($run->status === SanctionSyncRun::STATUS_SUCCESS && $run->hasEntryChanges()) {
                // รันต่อท้ายในกระบวนการเดียวกัน ไม่ dispatch เข้า queue
                // เพราะ production ไม่มี queue worker (ดูแผนที่ 4)
                $this->call('sanctions:rescan', [
                    '--since' => $run->started_at->toDateTimeString(),
                    '--close-delisted' => true,
                ]);
            }

            if ($run->status !== SanctionSyncRun::STATUS_SUCCESS) {
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function reportRun(SanctionSyncRun $run): void
    {
        $summary = sprintf(
            'parsed=%d added=%d updated=%d removed=%d as_of=%s',
            $run->entries_parsed,
            $run->entries_added,
            $run->entries_updated,
            $run->entries_removed,
            $run->source_as_of?->format('Y-m-d') ?? '-'
        );

        match ($run->status) {
            SanctionSyncRun::STATUS_SUCCESS => $this->info("สำเร็จ: {$summary}"),
            SanctionSyncRun::STATUS_ABORTED_SANITY_CHECK => $this->warn("ยกเลิก: {$run->error_message}"),
            default => $this->error("ล้มเหลว: {$run->error_message}"),
        };
    }
}
