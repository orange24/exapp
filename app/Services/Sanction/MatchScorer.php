<?php

namespace App\Services\Sanction;

use App\Models\SanctionScreeningMatch;
use App\Models\Setting;

/**
 * คิดคะแนนความเหมือน — ฟังก์ชันบริสุทธิ์ทั้งหมด ไม่แตะ DB ไม่แตะ config ที่มี state
 *
 * แยกออกจาก SanctionMatcher โดยตั้งใจ: ตรงนี้คือ logic ที่ต้องเทสให้ครบทุกเคส
 * ส่วน SanctionMatcher แค่ไปหยิบ candidate จาก DB มาป้อน
 */
class MatchScorer
{
    public const SCORE_EXACT_ID = 100.0;
    public const SCORE_PASSPORT_UNVERIFIED = 90.0;
    public const SCORE_NAME_EXACT = 95.0;
    public const SCORE_TOKEN_CONTAINMENT = 85.0;
    public const SCORE_FUZZY_MIN = 60.0;
    public const SCORE_FUZZY_MAX = 80.0;
    public const SCORE_SOUNDEX = 55.0;

    /** ชื่อชิ้นเดียวที่ตรงกัน — เตือนได้ แต่ไม่ใช่หลักฐานระดับชื่อเต็ม */
    public const SCORE_SINGLE_TOKEN = 72.0;

    /** เพดาน 99 — สงวน 100 ไว้ให้ exact ID เท่านั้น ไม่ให้คะแนนชื่อไต่ไปชน */
    public const SCORE_CAP = 99.0;

    /** Levenshtein ratio ต่ำกว่านี้ถือว่าคนละชื่อ */
    private const FUZZY_MIN_RATIO = 0.85;

    /**
     * ค่าข้อมูลระบุตัวตนของชื่อ (ผลรวม log(N/df) ของแต่ละคำ) ที่ถือว่า
     * "สามัญมาก" และ "เจาะจงพอ" — วัดจากรายชื่อจริงของ ปปง.:
     *   AHMED 4.3 · MOHAMMED 4.5 · ABU 5.5 · AMAN 7.2 · MOHAMED AHMED 8.4
     *   ABU RUSDAN 12.7 · AMRAN MING 14.0 · IYAD NAZMI SALIH KHALIL 26.0
     */
    private const RARITY_COMMON = 5.0;
    private const RARITY_DISTINCT = 12.0;
    private const RARITY_FLOOR = 0.55;

    /** เมื่อวันเกิดตรงกัน ความหายากของชื่อแทบไม่สำคัญอีกต่อไป */
    private const RARITY_WHEN_DOB_MATCHES = 0.95;

    private const MODIFIER_NATIONALITY_MATCH = 1.10;
    private const MODIFIER_NATIONALITY_MISMATCH = 0.70;
    private const MODIFIER_DOB_MATCH = 1.20;
    private const MODIFIER_DOB_MISMATCH = 0.50;

    private const BLANK_MARKERS = ['na', 'n/a', '-', '—'];

    /**
     * @return array{type: string, score: float}|null  null = ไม่ match เลย
     */
    public static function nameScore(?string $customerName, ?string $entryName): ?array
    {
        $customerTokens = NameNormalizer::tokens($customerName);
        $entryTokens = NameNormalizer::tokens($entryName);

        if ($customerTokens === [] || $entryTokens === []) {
            return null;
        }

        // ชื่อชิ้นเดียวไม่ใช่การระบุตัวบุคคล
        //
        // UN list เก็บชื่อเป็นชิ้น ๆ ("1. ABU 2. RUSDAN") และเราเก็บทุกชิ้น
        // เป็นชื่อที่ค้นได้ เพื่อไม่ให้พลาดลูกค้าที่ถูกบันทึกชื่อไว้ไม่ครบ
        //
        // แต่ชิ้นเดียวเป็นหลักฐานที่อ่อนมาก ไม่ว่าจะตรงกันเป๊ะหรือไปโผล่อยู่
        // ในชื่อที่ยาวกว่า — ตอน sync ข้อมูลจริงขึ้น production ลูกค้า 13 จาก 91 ราย
        // ติดแถบแดง 85 คะแนนเพราะมีคำว่า "ABU" หรือ "AHMAD" อยู่ในชื่อเท่านั้น
        //
        // ยังเตือนอยู่ (ส้ม) ให้คนตรวจเคลียร์ครั้งเดียวแล้วจบ แต่ต้องไม่ใช่สีแดง
        // ไม่งั้นพนักงานจะชินกับแถบแดงจนกดผ่านวันที่เจอของจริง
        $entryIsNameFragment = count($entryTokens) < 2;

        if ($customerTokens === $entryTokens) {
            return $entryIsNameFragment
                ? ['type' => 'name_fuzzy', 'score' => self::SCORE_SINGLE_TOKEN]
                : ['type' => 'name_exact', 'score' => self::SCORE_NAME_EXACT];
        }

        // token ของรายชื่อต้องห้ามอยู่ในชื่อลูกค้าครบทุกตัว
        // (เช่น ลูกค้ากรอก "AMRAN BIN MING" ส่วนลิสต์เก็บ "AMRAN MING")
        if (array_diff($entryTokens, $customerTokens) === []) {
            return $entryIsNameFragment
                ? ['type' => 'name_fuzzy', 'score' => self::SCORE_SINGLE_TOKEN]
                : ['type' => 'token_containment', 'score' => self::SCORE_TOKEN_CONTAINMENT];
        }

        $ratio = self::tokenSimilarity($customerTokens, $entryTokens);

        if ($ratio >= self::FUZZY_MIN_RATIO) {
            $span = self::SCORE_FUZZY_MAX - self::SCORE_FUZZY_MIN;
            $position = ($ratio - self::FUZZY_MIN_RATIO) / (1.0 - self::FUZZY_MIN_RATIO);

            return [
                'type' => 'name_fuzzy',
                'score' => round(self::SCORE_FUZZY_MIN + ($position * $span), 2),
            ];
        }

        return null;
    }

    /**
     * ความเหมือนเฉลี่ย: แต่ละ token ของรายชื่อต้องห้าม จับคู่กับ token ของลูกค้า
     * ที่ใกล้ที่สุด แล้วเฉลี่ย
     *
     * @param array<int, string> $customerTokens
     * @param array<int, string> $entryTokens
     */
    private static function tokenSimilarity(array $customerTokens, array $entryTokens): float
    {
        $total = 0.0;

        foreach ($entryTokens as $entryToken) {
            $best = 0.0;

            foreach ($customerTokens as $customerToken) {
                $best = max($best, self::stringRatio($entryToken, $customerToken));
            }

            $total += $best;
        }

        return $total / count($entryTokens);
    }

    private static function stringRatio(string $a, string $b): float
    {
        if ($a === $b) {
            return 1.0;
        }

        $maxLen = max(mb_strlen($a), mb_strlen($b));

        if ($maxLen === 0) {
            return 0.0;
        }

        // levenshtein() ของ PHP ทำงานกับ byte ไม่ใช่ตัวอักษร multibyte
        // ชื่อไทยจะได้ระยะทางเกินจริง — ยอมรับได้เพราะชื่อไทยมี exact match
        // ด้วยเลขบัตร 13 หลักครบ 100% อยู่แล้ว fuzzy เป็นแค่ตัวเสริม
        $distance = levenshtein($a, $b);

        return max(0.0, 1.0 - ($distance / $maxLen));
    }

    /**
     * แปลงค่าข้อมูลระบุตัวตนของชื่อ เป็นตัวคูณคะแนน
     *
     * ลูกค้าตะวันออกกลางแทบทุกคนมี MOHAMED หรือ AHMED อยู่ในชื่อ การตรงกันที่
     * คำพวกนั้นจึงไม่ใช่หลักฐาน แต่ชื่ออย่าง ABU RUSDAN มีอยู่ชื่อเดียวในลิสต์
     * ทั้งหมด — ตรงกันเมื่อไหร่คือเรื่องจริงจัง
     */
    public static function rarityFactor(float $information): float
    {
        if ($information >= self::RARITY_DISTINCT) {
            return 1.0;
        }

        if ($information <= self::RARITY_COMMON) {
            return self::RARITY_FLOOR;
        }

        $span = self::RARITY_DISTINCT - self::RARITY_COMMON;
        $position = ($information - self::RARITY_COMMON) / $span;

        return round(self::RARITY_FLOOR + $position * (1.0 - self::RARITY_FLOOR), 4);
    }

    /** @return 'match'|'mismatch'|'unknown' */
    public static function compareNationality(?string $customer, ?string $entry): string
    {
        $a = self::normalizeCode($customer);
        $b = self::normalizeCode($entry);

        if ($a === null || $b === null) {
            return 'unknown';
        }

        // ต้นทางเก็บไม่สม่ำเสมอ: "TH" บ้าง "Jordan" บ้าง ส่วน OCR พาสปอร์ต
        // ส่งมาเป็น ISO-3 ("THA", "JOR") → เทียบแบบ prefix อย่างน้อย 2 ตัว
        $shorter = mb_strlen($a) <= mb_strlen($b) ? $a : $b;
        $longer = $shorter === $a ? $b : $a;

        if (mb_strlen($shorter) >= 2 && str_starts_with($longer, $shorter)) {
            return 'match';
        }

        return 'mismatch';
    }

    /** @return 'match'|'mismatch'|'unknown' */
    public static function compareDob(?string $customer, ?string $entry): string
    {
        $a = self::parseDate($customer);
        $b = self::parseDate($entry);

        if ($a === null || $b === null) {
            return 'unknown';
        }

        // ฝั่งที่มีแต่ปี (ต้นทางบางรายเก็บแค่ "1981") → เทียบแค่ปี
        if ($a['precision'] === 'year' || $b['precision'] === 'year') {
            return $a['year'] === $b['year'] ? 'match' : 'mismatch';
        }

        return $a['date'] === $b['date'] ? 'match' : 'mismatch';
    }

    /**
     * @param 'match'|'mismatch'|'unknown' $nationality
     * @param 'match'|'mismatch'|'unknown' $dob
     */
    /**
     * @param 'match'|'mismatch'|'unknown' $nationality
     * @param 'match'|'mismatch'|'unknown' $dob
     * @param float $rarityFactor ตัวคูณจาก rarityFactor() — 1.0 คือไม่ลดเลย
     *
     * วันเกิดเป็นตัวกำหนดว่าความหายากของชื่อมีน้ำหนักแค่ไหน:
     *
     *   ไม่รู้วันเกิด  → ชื่อเป็นหลักฐานเดียวที่มี ความหายากจึงเป็นตัวตัดสิน
     *   วันเกิดไม่ตรง → คนละคน กดทิ้งไม่ว่าชื่อจะหายากแค่ไหน
     *   วันเกิดตรง    → หลักฐานแรงมาก ยกเลิกการลดจากความหายาก
     *
     * โหมดที่สามสำคัญที่สุดและมองข้ามง่ายที่สุด — ลูกค้าชื่อสามัญที่บังเอิญ
     * เกิดวันเดียวกับคนในลิสต์เป๊ะ คือเคสที่ควรเป็นสีแดงที่สุด ถ้าปล่อยให้
     * ตัวคูณความหายากกดอยู่ จะกลายเป็นกดเคสที่อันตรายที่สุดลงไปเป็นสีส้ม
     */
    public static function applyModifiers(
        float $baseScore,
        string $nationality,
        string $dob,
        float $rarityFactor = 1.0,
        ?string $matchType = null,
    ): float {
        $score = $baseScore;

        $score *= match ($nationality) {
            'match' => self::MODIFIER_NATIONALITY_MATCH,
            'mismatch' => self::MODIFIER_NATIONALITY_MISMATCH,
            default => 1.0,
        };

        $score *= match ($dob) {
            'match' => max($rarityFactor, self::RARITY_WHEN_DOB_MATCHES) * self::MODIFIER_DOB_MATCH,
            'mismatch' => $rarityFactor * self::MODIFIER_DOB_MISMATCH,
            default => $rarityFactor,
        };

        // วันเกิดไม่ตรงตัดชื่อที่ตรงเป๊ะลงไปต่ำกว่าเกณฑ์เตือน แล้วเงียบสนิท
        // แต่วันเกิดมาจากมือพนักงาน ไม่ได้มาจากชิปบัตร — พิมพ์ผิดหลักเดียว
        // คนที่ติดรายชื่อจริงจะผ่านเคาน์เตอร์ไปโดยไม่มีสัญญาณอะไรเลย
        //
        // ยังออกจากแถบแดงตามเจตนาเดิม แต่ไม่หายไปทั้งใบ ให้ค้างเป็นส้มให้คน
        // ดูครั้งเดียวแล้วเคลียร์จบ
        //
        // เฉพาะชื่อที่ตรงเป๊ะหรือตรงครบทุกคำ ชื่อชิ้นเดียวและชื่อคล้ายไม่เข้าข่าย
        // ไม่งั้นพื้นนี้จะกลายเป็นแหล่งผลิต false positive ชุดใหม่แทน
        if ($dob === 'mismatch' && in_array($matchType, [
            SanctionScreeningMatch::TYPE_NAME_EXACT,
            SanctionScreeningMatch::TYPE_TOKEN_CONTAINMENT,
        ], true)) {
            $score = max($score, self::potentialThreshold());
        }

        return round(min($score, self::SCORE_CAP), 2);
    }

    /** clear | potential_match | confirmed_match */
    public static function classify(float $score): string
    {
        if ($score >= self::SCORE_EXACT_ID) {
            return 'confirmed_match';
        }

        if ($score >= self::potentialThreshold()) {
            return 'potential_match';
        }

        return 'clear';
    }

    /** red | orange | none — ใช้เลือกสีแถบเตือนหน้าเคาน์เตอร์ */
    public static function severity(float $score): string
    {
        if ($score >= self::redThreshold()) {
            return 'red';
        }

        if ($score >= self::potentialThreshold()) {
            return 'orange';
        }

        return 'none';
    }

    /**
     * เกณฑ์เก็บใน settings ไม่ hard-code — ต้องจูนหลังใช้จริง 2-3 สัปดาห์
     * และไม่ควรต้อง deploy ใหม่ทุกครั้งที่ขยับ
     */
    public static function potentialThreshold(): float
    {
        return (float) Setting::get('sanction_threshold_potential', 70);
    }

    public static function redThreshold(): float
    {
        return (float) Setting::get('sanction_threshold_red', 85);
    }

    private static function normalizeCode(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || in_array(mb_strtolower($value), self::BLANK_MARKERS, true)) {
            return null;
        }

        return strtoupper(preg_replace('/[^A-Za-z]/', '', $value) ?? '') ?: null;
    }

    /**
     * @return array{date: string, year: string, precision: 'day'|'year'}|null
     */
    private static function parseDate(?string $value): ?array
    {
        $value = trim((string) $value);

        if ($value === '' || in_array(mb_strtolower($value), self::BLANK_MARKERS, true)) {
            return null;
        }

        // ISO: 1981-12-18
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1) {
            return ['date' => "{$m[1]}-{$m[2]}-{$m[3]}", 'year' => $m[1], 'precision' => 'day'];
        }

        // ต้นทาง ปปง.: 18-12-1981
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $value, $m) === 1) {
            return ['date' => "{$m[3]}-{$m[2]}-{$m[1]}", 'year' => $m[3], 'precision' => 'day'];
        }

        // ปีอย่างเดียว
        if (preg_match('/^(\d{4})$/', $value, $m) === 1) {
            return ['date' => $m[1], 'year' => $m[1], 'precision' => 'year'];
        }

        return null;
    }
}
