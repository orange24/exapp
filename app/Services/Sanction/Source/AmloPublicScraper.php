<?php

namespace App\Services\Sanction\Source;

use App\Services\Sanction\AmloHtmlParser;
use App\Services\Sanction\Dto\SanctionFetchResult;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * ดึงรายชื่อจากเว็บสาธารณะ ปปง.
 *
 * กลยุทธ์: ดึงหน้า list ก่อนเพื่อรู้ว่ามีใครบ้างและแถวไหนขยับ
 * แล้วค่อยดึงหน้า detail เฉพาะตัวที่ใหม่หรือเปลี่ยน
 *
 * ครั้งแรกต้องดึง ~1,006 หน้า ที่ 1 req/วินาที ประมาณ 17 นาที
 * ครั้งต่อไปปกติ 0-30 หน้า จบใน 30 วินาที
 */
class AmloPublicScraper implements SanctionSource
{
    /** @var array<int, array{source_ref: string, row_hash: string}>|null */
    private ?array $listRows = null;

    public function __construct(
        private readonly string $listCode,
    ) {
        if (! array_key_exists($listCode, (array) config('sanction.amlo.lists'))) {
            throw new RuntimeException("ไม่รู้จัก list_code \"{$listCode}\" — ดู config/sanction.php");
        }
    }

    public function listCode(): string
    {
        return $this->listCode;
    }

    public function adapterName(): string
    {
        return 'amlo_public_scraper';
    }

    public function fetch(array $knownRowHashes = []): SanctionFetchResult
    {
        $slug = config("sanction.amlo.lists.{$this->listCode}");
        $base = rtrim((string) config('sanction.amlo.base_url'), '/');

        $rows = $this->fetchListRows();

        $entries = [];
        $failedRefs = [];
        $asOf = null;
        $delayMs = (int) config('sanction.amlo.request_delay_ms');

        foreach ($rows as $row) {
            $ref = $row['source_ref'];

            // ข้าม detail ของตัวที่เนื้อแถวไม่เปลี่ยน — จุดที่ทำให้ sync รอบปกติเร็ว
            if (($knownRowHashes[$ref] ?? null) === $row['row_hash']) {
                continue;
            }

            usleep($delayMs * 1000);

            try {
                $detailHtml = $this->get("{$base}/{$slug}/detail/{$ref}");
                $dto = AmloHtmlParser::parseDetail($detailHtml, $ref, $row['row_hash']);
            } catch (\Throwable $e) {
                Log::warning('AMLO detail fetch failed', [
                    'list_code' => $this->listCode,
                    'source_ref' => $ref,
                    'error' => $e->getMessage(),
                ]);
                $failedRefs[] = $ref;

                continue;
            }

            $asOf ??= $dto->asOfDate;
            $entries[] = $dto;
        }

        return new SanctionFetchResult(
            listCode: $this->listCode,
            adapterName: $this->adapterName(),
            entries: $entries,
            asOfDate: $asOf,
            failedRefs: $failedRefs,
        );
    }

    /**
     * จำนวนแถวทั้งหมดบนหน้า list — sync service ใช้ตัดสิน sanity check
     * (entries ที่ fetch() คืนมาเป็นเฉพาะตัวที่เปลี่ยน จึงนับแทนกันไม่ได้)
     *
     * @return array<int, array{source_ref: string, row_hash: string}>
     */
    public function fetchListRows(): array
    {
        return $this->listRows ??= $this->loadListRows();
    }

    /**
     * SanctionSyncService เรียก fetchListRows() แล้วเรียก fetch() ต่อ ซึ่งเดิมโหลด
     * หน้า list ซ้ำอีกรอบ = ยิง server ของหน่วยงานราชการ 2 ครั้งต่อการ sync 1 ครั้ง
     * โดยไม่ได้อะไรเพิ่ม — cache ไว้ใน instance เดียวกันพอ
     */
    private function loadListRows(): array
    {
        $slug = config("sanction.amlo.lists.{$this->listCode}");
        $base = rtrim((string) config('sanction.amlo.base_url'), '/');

        return AmloHtmlParser::parseList($this->get("{$base}/{$slug}/"), $slug);
    }

    private function get(string $url): string
    {
        $contact = (string) config('sanction.amlo.contact_email');

        // ใส่อีเมลติดต่อใน User-Agent เพราะนี่คือ server ของหน่วยงานราชการ
        // ถ้าเขาเห็น traffic แปลกแล้วอยากถามว่าใคร จะติดต่อได้แทนที่จะบล็อก IP เราทิ้ง
        $agent = 'ExApp-SanctionSync/1.0' . ($contact !== '' ? " (+{$contact})" : '');

        $response = Http::withHeaders(['User-Agent' => $agent])
            ->timeout((int) config('sanction.amlo.timeout_seconds'))
            ->retry(
                (int) config('sanction.amlo.retry_times'),
                (int) config('sanction.amlo.retry_base_delay_ms'),
                throw: false,
            )
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException("ดึง {$url} ไม่สำเร็จ (HTTP {$response->status()})");
        }

        return $response->body();
    }
}
