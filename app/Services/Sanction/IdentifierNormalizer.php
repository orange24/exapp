<?php

namespace App\Services\Sanction;

class IdentifierNormalizer
{
    /** ค่าที่ต้นทางใช้แทน "ไม่มีข้อมูล" */
    private const BLANK_MARKERS = ['na', 'n/a', '-', '—'];

    /**
     * แยกช่องเลขเอกสารหนึ่งช่องออกเป็นรายเล่ม
     * UN list เก็บหลายเล่มในช่องเดียวคั่นด้วย comma: "Jordan 654781 , Jordan 286062"
     *
     * @return string[]
     */
    public static function split(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '' || in_array(mb_strtolower($raw), self::BLANK_MARKERS, true)) {
            return [];
        }

        $parts = preg_split('/\s*,\s*/u', $raw) ?: [];

        $out = [];
        foreach ($parts as $part) {
            $part = trim(preg_replace('/\s+/u', ' ', $part) ?? '');
            if ($part === '' || in_array(mb_strtolower($part), self::BLANK_MARKERS, true)) {
                continue;
            }
            $out[] = $part;
        }

        return $out;
    }

    /**
     * ตัดชื่อประเทศ/ช่องว่าง/ขีดออก เหลือเฉพาะตัวเลขเอกสาร
     * "Jordan 654781"     -> "654781"       (ตัดคำที่ไม่มีตัวเลขทิ้ง = ชื่อประเทศ)
     * "OT0537103"         -> "OT0537103"
     * "ab-123 456"        -> "AB123456"     (ขีดในคำเดียวกันเป็นตัวคั่น ไม่ใช่ชื่อประเทศ)
     * "5-9605-00028-10-1" -> "5960500028101"
     * "unknown"           -> "UNKNOWN"      (ไม่มีคำไหนมีตัวเลข จึงคืนทั้งก้อน)
     */
    public static function normalize(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '';
        }

        // แยกตามช่องว่างก่อน เพราะชื่อประเทศมาเป็น "คำ" แยกจากเลขเอกสาร
        // ส่วนขีด/จุดที่อยู่ติดกันในคำเดียว เป็นแค่ตัวคั่นของเลขเอกสารเอง
        $words = preg_split('/\s+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $withDigits = array_values(array_filter(
            $words,
            static fn (string $w): bool => preg_match('/\d/', $w) === 1
        ));

        $kept = $withDigits !== [] ? $withDigits : $words;

        $cleaned = preg_replace('/[^A-Za-z0-9]+/u', '', implode('', $kept)) ?? '';

        return strtoupper($cleaned);
    }

    /**
     * ประเทศที่ออกเอกสาร = token ที่เป็นตัวอักษรล้วน (ไม่มีตัวเลขปน)
     * คืน null ถ้าไม่มี token แบบนั้น
     */
    public static function issuingCountry(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        $tokens = preg_split('/[^A-Za-z0-9]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            if (preg_match('/^[A-Za-z]+$/', $token) === 1) {
                return $token;
            }
        }

        return null;
    }

    /** เลขบัตรประชาชนไทยต้องเป็นตัวเลข 13 หลักพอดี — ใช้ตัดสินการบล็อกแข็ง */
    public static function isThaiNationalId(?string $normalized): bool
    {
        return preg_match('/^\d{13}$/', (string) $normalized) === 1;
    }
}
