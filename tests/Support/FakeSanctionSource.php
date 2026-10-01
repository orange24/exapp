<?php

namespace Tests\Support;

use App\Services\Sanction\Dto\SanctionEntryDto;
use App\Services\Sanction\Dto\SanctionFetchResult;
use App\Services\Sanction\Source\SanctionSource;

/**
 * source ปลอม — เทส sync ทั้งหมดใช้ตัวนี้ ไม่ยิงเน็ต
 */
class FakeSanctionSource implements SanctionSource
{
    /**
     * @param array<int, SanctionEntryDto> $entries
     * @param array<int, array{source_ref: string, row_hash: string}> $listRows
     * @param array<int, string> $failedRefs
     */
    public function __construct(
        private readonly string $listCode,
        private readonly array $entries,
        private readonly array $listRows,
        private readonly ?string $asOfDate = '2026-10-01',
        private readonly array $failedRefs = [],
    ) {
    }

    public function listCode(): string
    {
        return $this->listCode;
    }

    public function adapterName(): string
    {
        return 'fake';
    }

    public function fetch(array $knownRowHashes = []): SanctionFetchResult
    {
        $entries = array_values(array_filter(
            $this->entries,
            fn (SanctionEntryDto $e): bool => ($knownRowHashes[$e->sourceRef] ?? null) !== $e->rowHash
        ));

        return new SanctionFetchResult(
            listCode: $this->listCode,
            adapterName: $this->adapterName(),
            entries: $entries,
            asOfDate: $this->asOfDate,
            failedRefs: $this->failedRefs,
        );
    }

    /** @return array<int, array{source_ref: string, row_hash: string}> */
    public function fetchListRows(): array
    {
        return $this->listRows;
    }

    /** helper สร้าง DTO สำหรับเทส */
    public static function entry(string $ref, string $nameEn, ?string $nationalId = null): SanctionEntryDto
    {
        return new SanctionEntryDto(
            sourceRef: $ref,
            nameEn: $nameEn,
            nationality: 'TH',
            nationalId: $nationalId,
            status: 'Designated person',
            asOfDate: '2026-10-01',
            names: [$nameEn],
            identifiers: $nationalId !== null
                ? [['type' => 'national_id', 'raw' => $nationalId]]
                : [],
            // เทสใช้ contentHash เป็น rowHash ด้วย — ของจริงมาจากหน้า list คนละค่า
            // แต่พฤติกรรมที่เทสต้องการพิสูจน์ (ไม่เปลี่ยน = ไม่ส่งกลับมา) เหมือนกัน
            rowHash: hash('sha256', $ref . '|' . $nameEn . '|' . (string) $nationalId),
        );
    }

    /**
     * @param array<int, SanctionEntryDto> $entries
     * @return array<int, array{source_ref: string, row_hash: string}>
     */
    public static function listRowsFor(array $entries): array
    {
        return array_map(
            static fn (SanctionEntryDto $e): array => [
                'source_ref' => $e->sourceRef,
                'row_hash' => (string) $e->rowHash,
            ],
            $entries
        );
    }
}
