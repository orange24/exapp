<?php

namespace App\Services\Sanction;

class NameNormalizer
{
    private const BLANK_MARKERS = ['na', 'n/a', '-', '—'];

    /** คำนำหน้าที่ต้องตัดก่อนเทียบ — พนักงานกรอกมาบ้างไม่กรอกบ้าง */
    private const TITLES = [
        'MR', 'MRS', 'MS', 'MISS', 'DR', 'PROF',
        'นาย', 'นาง', 'นางสาว', 'น.ส.', 'ด.ช.', 'ด.ญ.',
    ];

    /**
     * uppercase -> ตัดคำนำหน้า -> ตัดอักขระพิเศษ -> แตก token -> เรียง -> join
     * การเรียง token คือสิ่งที่ทำให้ "KHALIL IYAD" กับ "IYAD KHALIL" ตรงกัน
     */
    public static function normalize(?string $name): string
    {
        return implode(' ', self::tokens($name));
    }

    /**
     * @return string[] token ที่ normalize แล้ว เรียงตามตัวอักษร ไม่ซ้ำ
     */
    public static function tokens(?string $name): array
    {
        $name = trim((string) $name);

        if ($name === '' || in_array(mb_strtolower($name), self::BLANK_MARKERS, true)) {
            return [];
        }

        $name = mb_strtoupper($name, 'UTF-8');

        // ตัดอักขระที่ไม่ใช่ตัวอักษร/ตัวเลข/ช่องว่าง (จุด จุลภาค ขีด วงเล็บ)
        // ต้องเก็บ \p{M} ไว้ด้วย เพราะสระ/วรรณยุกต์ไทย (เช่น ั ิ ่ ้) เป็น combining mark
        // ไม่ใช่ \p{L} — ถ้าไม่เก็บ "อำรัน มิง" จะกลายเป็น "อำร น ม ง"
        $name = preg_replace('/[^\p{L}\p{N}\p{M}\s]+/u', ' ', $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '') {
            return [];
        }

        $tokens = explode(' ', $name);

        $titles = array_map(static fn (string $t): string => mb_strtoupper($t, 'UTF-8'), self::TITLES);

        $tokens = array_values(array_filter(
            $tokens,
            static fn (string $t): bool => $t !== '' && ! in_array($t, $titles, true)
        ));

        $tokens = array_values(array_unique($tokens));
        sort($tokens, SORT_STRING);

        return $tokens;
    }

    /**
     * ต้นทางเก็บชื่อแบบมีเลขลำดับ:
     *   UN: "1. IYAD 2. NAZMI 3. SALIH 4. KHALIL"  -> 4 ชิ้น
     *   TH: "1. อำรัน มิง"                          -> 1 ชิ้น
     *
     * @return string[] ชื่อดิบรายชิ้น (ยังไม่ normalize)
     */
    public static function splitNumberedParts(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '' || in_array(mb_strtolower($raw), self::BLANK_MARKERS, true)) {
            return [];
        }

        $raw = trim(preg_replace('/\s+/u', ' ', $raw) ?? '');

        // แตกตรงตำแหน่งที่มี "<เลข>." ขึ้นต้นชิ้นใหม่
        $parts = preg_split('/\s*\d+\.\s*/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $parts = array_values(array_filter(
            array_map('trim', $parts),
            static fn (string $p): bool => $p !== ''
        ));

        return $parts !== [] ? $parts : [$raw];
    }

    /** ชื่อที่มีอักษรไทยแม้ตัวเดียว ถือเป็น th */
    public static function detectScript(?string $name): string
    {
        return preg_match('/\p{Thai}/u', (string) $name) === 1 ? 'th' : 'latin';
    }

    /**
     * soundex ของชื่อ latin = soundex ราย token ต่อกัน (token ถูกเรียงแล้วจาก normalize)
     * คืนค่าว่างสำหรับชื่อไทย เพราะ soundex เป็นอัลกอริทึม ASCII ใช้กับไทยไม่ได้
     *
     * คำนวณใน PHP ทั้งตอนเขียนและตอนค้น — ห้ามเรียก SOUNDEX() ของ MySQL
     * เพราะเทสรันบน SQLite ที่ไม่มีฟังก์ชันนั้น และ index จะใช้ไม่ได้
     */
    public static function soundexOf(?string $name): string
    {
        if (self::detectScript($name) === 'th') {
            return '';
        }

        $tokens = self::tokens($name);

        if ($tokens === []) {
            return '';
        }

        return implode('', array_map(static fn (string $t): string => soundex($t), $tokens));
    }
}
