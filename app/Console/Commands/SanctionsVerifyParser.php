<?php

namespace App\Console\Commands;

use App\Services\Sanction\AmloHtmlParser;
use App\Services\Sanction\Source\AmloPublicScraper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Canary — ยิงหน้าจริงของ ปปง. เพื่อยืนยันว่า parser ยังอ่านได้
 *
 * ไม่เขียน DB อะไรเลย และ **ไม่อยู่ใน CI** เพราะต้องใช้เน็ตและจะ flaky
 * ให้ Cloud Scheduler รันสัปดาห์ละครั้ง
 *
 * ต่างจาก sanity check ใน SanctionSyncService ตรงที่ตัวนั้นทำงานตอนพังไปแล้ว
 * ส่วนตัวนี้เป็นการเตือนล่วงหน้า
 */
class SanctionsVerifyParser extends Command
{
    protected $signature = 'sanctions:verify-parser';

    protected $description = 'ยิงหน้าจริงของ ปปง. ตรวจว่า parser ยังอ่านฟิลด์บังคับได้ครบ (ไม่เขียน DB)';

    public function handle(): int
    {
        $base = rtrim((string) config('sanction.amlo.base_url'), '/');
        $lists = (array) config('sanction.amlo.lists');

        $problems = [];

        foreach ($lists as $listCode => $slug) {
            $this->line("=== {$listCode} ({$slug}) ===");

            try {
                $source = new AmloPublicScraper($listCode);
                $rows = $source->fetchListRows();
            } catch (Throwable $e) {
                $problems[] = "{$listCode}: ดึงหน้า list ไม่ได้ — {$e->getMessage()}";
                $this->error("  ดึงหน้า list ไม่ได้: {$e->getMessage()}");

                continue;
            }

            if ($rows === []) {
                $problems[] = "{$listCode}: หน้า list parse ได้ 0 แถว";
                $this->error('  parse ได้ 0 แถว');

                continue;
            }

            $this->info('  หน้า list: ' . count($rows) . ' แถว');

            $ref = $rows[0]['source_ref'];

            try {
                $html = Http::withHeaders(['User-Agent' => 'ExApp-SanctionSync/1.0'])
                    ->timeout((int) config('sanction.amlo.timeout_seconds'))
                    ->get("{$base}/{$slug}/detail/{$ref}")
                    ->throw()
                    ->body();

                $dto = AmloHtmlParser::parseDetail($html, $ref);
            } catch (Throwable $e) {
                $problems[] = "{$listCode}: parse หน้า detail {$ref} ไม่ได้ — {$e->getMessage()}";
                $this->error("  หน้า detail {$ref}: {$e->getMessage()}");

                continue;
            }

            if ($dto->nameEn === null && $dto->nameTh === null) {
                $problems[] = "{$listCode}: หน้า detail {$ref} ไม่มีชื่อเลย";
                $this->error("  หน้า detail {$ref}: ไม่มีชื่อ");

                continue;
            }

            $this->info("  หน้า detail {$ref}: ok (as_of=" . ($dto->asOfDate ?? '-') . ')');
        }

        if ($problems !== []) {
            $this->newLine();
            $this->error('parser ใช้กับหน้าเว็บปัจจุบันไม่ได้:');

            foreach ($problems as $problem) {
                $this->error("  - {$problem}");
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('parser ยังอ่านหน้าเว็บ ปปง. ได้ครบทุกลิสต์');

        return self::SUCCESS;
    }
}
