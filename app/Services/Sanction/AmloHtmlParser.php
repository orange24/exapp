<?php

namespace App\Services\Sanction;

use App\Services\Sanction\Dto\SanctionEntryDto;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use RuntimeException;

/**
 * แปลง HTML ของเว็บสาธารณะ ปปง. เป็น DTO
 *
 * แยกออกมาจาก AmloPublicScraper โดยตั้งใจ: scraper ทำหน้าที่ "ดึง" อย่างเดียว
 * ส่วนไฟล์นี้ทำหน้าที่ "แปล" อย่างเดียว เวลา ปปง. เปลี่ยน layout จะแก้ที่เดียว
 * และเทสได้ด้วย fixture โดยไม่ยิงเน็ต
 *
 * ใช้ DOMDocument + DOMXPath (ext-dom มากับ PHP) — ห้ามเพิ่ม composer package
 */
class AmloHtmlParser
{
    /** ค่าที่ต้นทางใช้แทน "ไม่มีข้อมูล" — ต้องแปลงเป็น null ไม่ใช่เก็บสตริง "na" */
    private const BLANK_MARKERS = ['na', 'n/a', '-', '—'];

    /** ฟิลด์ที่ต้องมีบนหน้า detail ทุกหน้า ถ้าหายแปลว่า layout เปลี่ยน */
    private const REQUIRED_LABELS = [
        'Individual/Entity Name (English)',
        'Status',
    ];

    /**
     * @return array<int, array{source_ref: string, row_hash: string}>
     */
    public static function parseList(string $html, string $listSlug): array
    {
        $xpath = self::xpath($html);

        $rows = [];
        $seen = [];

        foreach ($xpath->query('//tr') as $tr) {
            $link = null;

            foreach ($xpath->query('.//a[contains(@href, "/detail/")]', $tr) as $a) {
                if (! $a instanceof DOMElement) {
                    continue;
                }

                $link = $a->getAttribute('href');
                break;
            }

            if ($link === null) {
                continue;
            }

            if (preg_match('#/' . preg_quote($listSlug, '#') . '/detail/(\d+)#', $link, $m) !== 1) {
                continue;
            }

            $ref = $m[1];

            if (isset($seen[$ref])) {
                continue;
            }
            $seen[$ref] = true;

            // hash จากเนื้อแถว (ไม่รวม href) — ใช้ตัดสินว่าต้องไปดึง detail ซ้ำไหม
            $cells = [];
            foreach ($xpath->query('./td', $tr) as $td) {
                $cells[] = self::clean($td->textContent);
            }

            $rows[] = [
                'source_ref' => $ref,
                'row_hash' => hash('sha256', implode('|', $cells)),
            ];
        }

        return $rows;
    }

    public static function parseDetail(string $html, string $sourceRef, ?string $rowHash = null): SanctionEntryDto
    {
        $xpath = self::xpath($html);

        $fields = [];

        foreach ($xpath->query('//tr[th]') as $tr) {
            $th = $xpath->query('./th', $tr)->item(0);
            $td = $xpath->query('./td', $tr)->item(0);

            if (! $th instanceof DOMNode || ! $td instanceof DOMNode) {
                continue;
            }

            $label = self::clean($th->textContent);

            if ($label === '') {
                continue;
            }

            $fields[$label] = self::nullIfBlank(self::clean($td->textContent));
        }

        foreach (self::REQUIRED_LABELS as $required) {
            if (! array_key_exists($required, $fields)) {
                throw new RuntimeException(
                    "AMLO detail page {$sourceRef}: ไม่พบฟิลด์บังคับ \"{$required}\" — layout ต้นทางอาจเปลี่ยน"
                );
            }
        }

        $banner = '';
        foreach ($xpath->query('//a[contains(@class, "block-icon")]') as $a) {
            $banner = self::clean($a->textContent);
            break;
        }

        $nameTh = $fields['Individual/Entity Name (Thailand)'] ?? null;
        $nameEn = $fields['Individual/Entity Name (English)'] ?? null;
        $aka = $fields['Also known as (a.k.a)'] ?? null;

        return new SanctionEntryDto(
            sourceRef: $sourceRef,
            referenceNumber: $fields['Reference Number'] ?? null,
            notificationNumber: $fields['Notification Number'] ?? null,
            section: self::extractSection($banner),
            nameTh: self::stripLeadingNumber($nameTh),
            nameEn: self::stripLeadingNumber($nameEn),
            aka: $aka,
            dateOfBirth: $fields['Date of Birth'] ?? null,
            nationality: $fields['Nationality'] ?? null,
            address1: $fields['Address No.1'] ?? null,
            address2: $fields['Address No.2'] ?? null,
            phone: $fields['Phone Number'] ?? null,
            email: $fields['E-mail'] ?? null,
            nationalId: $fields['National Identification Number'] ?? null,
            companyRegistrationNumber: $fields['Company Registration Number'] ?? null,
            groupName: $fields['Group'] ?? null,
            status: $fields['Status'] ?? null,
            listedOn: $fields['Listed On'] ?? null,
            asOfDate: self::extractAsOf($banner),
            names: self::collectNames($nameTh, $nameEn, $aka),
            identifiers: self::collectIdentifiers($fields),
            rowHash: $rowHash,
        );
    }

    /**
     * ชื่อทุกแบบที่ใช้ match ได้ — ชื่อเต็ม + ชื่อแยกชิ้น + aka
     * UN เก็บชื่อแบบ "1. IYAD 2. NAZMI 3. SALIH 4. KHALIL" ต้องได้ทั้งก้อนและรายชิ้น
     *
     * @return array<int, string>
     */
    private static function collectNames(?string $nameTh, ?string $nameEn, ?string $aka): array
    {
        $names = [];

        foreach ([$nameTh, $nameEn] as $raw) {
            if ($raw === null) {
                continue;
            }

            $parts = NameNormalizer::splitNumberedParts($raw);

            foreach ($parts as $part) {
                $names[] = $part;
            }

            // ชื่อเต็มที่ตัดเลขลำดับออกแล้ว (กรณี UN จะเป็น "IYAD NAZMI SALIH KHALIL")
            if (count($parts) > 1) {
                $names[] = implode(' ', $parts);
            }
        }

        if ($aka !== null) {
            foreach (preg_split('/\s*[;,]\s*/u', $aka, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $alias) {
                foreach (NameNormalizer::splitNumberedParts($alias) as $part) {
                    $names[] = $part;
                }
            }
        }

        $names = array_values(array_unique(array_filter(
            array_map('trim', $names),
            static fn (string $n): bool => $n !== ''
        )));

        return $names;
    }

    /**
     * @param array<string, string|null> $fields
     * @return array<int, array{type: string, raw: string}>
     */
    private static function collectIdentifiers(array $fields): array
    {
        $map = [
            'National Identification Number' => 'national_id',
            'Passport Number' => 'passport',
            'Company Registration Number' => 'company_reg',
        ];

        $out = [];

        foreach ($map as $label => $type) {
            foreach (IdentifierNormalizer::split($fields[$label] ?? null) as $raw) {
                $out[] = ['type' => $type, 'raw' => $raw];
            }
        }

        return $out;
    }

    /** "Examining the Name ... under Section 7 (Thailand List)" -> "7" */
    private static function extractSection(string $banner): ?string
    {
        if (preg_match_all('/Section\s+(\d+)/i', $banner, $m) >= 1 && $m[1] !== []) {
            return implode(',', array_unique($m[1]));
        }

        return null;
    }

    /** "... As Of 2026-10-01" -> "2026-10-01" */
    private static function extractAsOf(string $banner): ?string
    {
        if (preg_match('/As Of\s+(\d{4}-\d{2}-\d{2})/i', $banner, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * ตัดเลขลำดับออกจากชื่อให้หมด ไม่ใช่แค่ตัวแรก
     *
     *   TH: "1. อำรัน มิง"                       -> "อำรัน มิง"
     *   UN: "1. IYAD 2. NAZMI 3. SALIH 4. KHALIL" -> "IYAD NAZMI SALIH KHALIL"
     *
     * ถ้าตัดแค่ตัวแรก เลข 2. 3. 4. จะค้างอยู่ในคอลัมน์ name_en ซึ่งเป็นค่าที่
     * แถบเตือนหน้าเคาน์เตอร์ คิวตรวจสอบ และ noti ที่ส่งหาส่วนกลางเอาไปแสดง
     * คนที่ต้องตัดสินใจว่า "ใช่คนเดียวกันไหม" จะอ่านเทียบกับพาสปอร์ตในมือได้ยากขึ้น
     */
    private static function stripLeadingNumber(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $parts = NameNormalizer::splitNumberedParts($value);

        return self::nullIfBlank(trim(implode(' ', $parts)));
    }

    private static function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();

        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private static function clean(string $text): string
    {
        $text = str_replace("\xC2\xA0", ' ', $text);   // &nbsp;

        return trim(preg_replace('/\s+/u', ' ', $text) ?? '');
    }

    private static function nullIfBlank(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || in_array(mb_strtolower($value), self::BLANK_MARKERS, true)) {
            return null;
        }

        return $value;
    }
}
