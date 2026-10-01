<?php

namespace App\Services\Sanction\Source;

use App\Services\Sanction\Dto\SanctionFetchResult;

/**
 * จุดเปลี่ยนแนวทาง: วันนี้คือ AmloPublicScraper วันหน้าเพิ่ม AmloApsApiClient
 * SanctionSyncService ไม่รู้จัก HTTP / HTML / API เลย รู้จักแค่ interface นี้
 */
interface SanctionSource
{
    /** freeze_04_un | freeze_05_th | hr_02 | hr_08 */
    public function listCode(): string;

    /** amlo_public_scraper | amlo_aps_api — เก็บลง sanction_sync_runs.source_adapter */
    public function adapterName(): string;

    /**
     * รายชื่อทั้งหมดที่ยังอยู่บนลิสต์ (เบาๆ ไม่ดึงหน้า detail)
     * sync service ใช้ 2 อย่าง: ตัดสินว่าใครหายไป และนับจำนวนสำหรับ sanity check
     *
     * @return array<int, array{source_ref: string, row_hash: string}>
     */
    public function fetchListRows(): array;

    /**
     * @param array<string, string> $knownRowHashes [source_ref => source_row_hash ที่เราเก็บไว้]
     *                                              source ใช้ข้ามการดึง detail ของตัวที่แถวไม่ขยับ
     */
    public function fetch(array $knownRowHashes = []): SanctionFetchResult;
}
