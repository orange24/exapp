<?php

namespace App\Services\Sanction\Dto;

class SanctionFetchResult
{
    /**
     * @param array<int, SanctionEntryDto> $entries
     * @param array<int, string> $failedRefs source_ref ที่ดึง detail ไม่สำเร็จ
     */
    public function __construct(
        public readonly string $listCode,
        public readonly string $adapterName,
        public readonly array $entries,
        public readonly ?string $asOfDate = null,
        public readonly array $failedRefs = [],
    ) {
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
