<?php

namespace App\Services\Sanction;

use App\Models\SanctionEntryName;
use App\Models\SanctionNameToken;
use Illuminate\Support\Facades\DB;

/**
 * ดัชนีความถี่ของคำในรายชื่อบุคคลที่ถูกกำหนด
 *
 * ใช้ตอบว่า "การตรงกันที่ชื่อนี้ เป็นหลักฐานแค่ไหน" — คำที่ปรากฏใน 157 รายชื่อ
 * บอกอะไรแทบไม่ได้ ส่วนคำที่ปรากฏชื่อเดียวบอกได้เกือบทั้งหมด
 *
 * สร้างใหม่หลัง sync รายชื่อสำเร็จและมีการเปลี่ยนแปลง ไม่ใช่คำนวณสดทุกครั้ง
 * ที่ตรวจลูกค้า เพราะตาราง sanction_entry_names มีหลายพันแถว
 */
class NameRarityIndex
{
    /**
     * จำนวนชื่อขั้นต่ำที่ทำให้สถิติความถี่เชื่อถือได้
     *
     * ถ้าคลังรายชื่อเล็กกว่านี้ ทุกคำจะดูหายากหรือสามัญพอ ๆ กันหมดโดยไม่มี
     * ความหมาย — เกิดได้ตอนเพิ่งติดตั้ง ยังไม่ได้ sync หรือดัชนียังไม่ถูกสร้าง
     *
     * กรณีนั้นต้อง "ไม่ลดคะแนนเลย" ไม่ใช่ลดแบบมั่ว ๆ
     * การไม่มีสถิติต้องไม่ทำให้ระบบเตือนน้อยลง — ของจริงบน production
     * มี ~2,700 ชื่อ ส่วนในเทสมีไม่กี่ชื่อ
     */
    private const MIN_CORPUS_FOR_STATISTICS = 200;

    /** @var array<string, int>|null */
    private ?array $frequencies = null;

    private ?int $totalNames = null;

    /**
     * คำนวณความถี่ใหม่ทั้งหมดจาก sanction_entry_names
     *
     * @return int จำนวนคำที่ไม่ซ้ำ
     */
    public function rebuild(): int
    {
        $frequencies = [];

        SanctionEntryName::select(['id', 'name_normalized'])
            ->chunkById(1000, function ($rows) use (&$frequencies): void {
                foreach ($rows as $row) {
                    foreach ($this->tokensOf((string) $row->name_normalized) as $token) {
                        $frequencies[$token] = ($frequencies[$token] ?? 0) + 1;
                    }
                }
            });

        DB::transaction(function () use ($frequencies): void {
            SanctionNameToken::query()->delete();

            foreach (array_chunk($frequencies, 500, true) as $chunk) {
                $rows = [];
                foreach ($chunk as $token => $count) {
                    $rows[] = [
                        'token' => mb_substr((string) $token, 0, 191),
                        'document_frequency' => $count,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                SanctionNameToken::insert($rows);
            }
        });

        $this->frequencies = null;
        $this->totalNames = null;

        return count($frequencies);
    }

    /**
     * ค่าข้อมูลระบุตัวตนของชื่อ — ผลรวม log(N/df) ของแต่ละคำที่ไม่ซ้ำ
     *
     * ชื่อที่ประกอบด้วยคำที่พบบ่อยล้วน ๆ จะได้ค่าต่ำ ส่วนชื่อที่มีคำหายาก
     * แม้คำเดียวก็ได้ค่าสูง
     */
    public function informationOf(?string $name): float
    {
        $tokens = $this->tokensOf(NameNormalizer::normalize($name));

        if ($tokens === []) {
            return 0.0;
        }

        $this->load();

        if (! $this->hasUsableStatistics()) {
            return INF;   // INF -> rarityFactor() คืน 1.0 คือไม่ลดเลย
        }

        $total = max(1, (int) $this->totalNames);
        $sum = 0.0;

        foreach ($tokens as $token) {
            // คำที่ไม่เคยเห็นในลิสต์ถือว่าหายากที่สุด
            $df = max(1, $this->frequencies[$token] ?? 1);
            $sum += log($total / $df);
        }

        return $sum;
    }

    /** ตัวคูณพร้อมใช้ สำหรับส่งเข้า MatchScorer::applyModifiers() */
    public function factorFor(?string $name): float
    {
        return MatchScorer::rarityFactor($this->informationOf($name));
    }

    /**
     * คำอธิบายให้คนอ่าน — ใช้บอกผู้อนุมัติว่าทำไมคะแนนออกมาแบบนี้
     *
     * @return array{information: float|null, factor: float, commonest: array{token: string, count: int}|null}
     */
    public function explain(?string $name): array
    {
        $tokens = $this->tokensOf(NameNormalizer::normalize($name));
        $this->load();

        $commonest = null;
        foreach ($tokens as $token) {
            $count = $this->frequencies[$token] ?? 1;
            if ($commonest === null || $count > $commonest['count']) {
                $commonest = ['token' => $token, 'count' => $count];
            }
        }

        $information = $this->informationOf($name);

        if (! $this->hasUsableStatistics()) {
            return ['information' => null, 'factor' => 1.0, 'commonest' => null];
        }

        return [
            'information' => round($information, 1),
            'factor' => MatchScorer::rarityFactor($information),
            'commonest' => $commonest,
        ];
    }

    /** @return array<int, string> */
    private function tokensOf(string $normalized): array
    {
        return array_values(array_unique(array_filter(explode(' ', $normalized), fn ($t) => $t !== '')));
    }

    /** ดัชนีถูกสร้างแล้ว และคลังรายชื่อใหญ่พอให้ความถี่มีความหมาย */
    private function hasUsableStatistics(): bool
    {
        return $this->frequencies !== []
            && (int) $this->totalNames >= self::MIN_CORPUS_FOR_STATISTICS;
    }

    private function load(): void
    {
        if ($this->frequencies !== null) {
            return;
        }

        $this->frequencies = SanctionNameToken::pluck('document_frequency', 'token')->all();
        $this->totalNames = SanctionEntryName::count();
    }
}
