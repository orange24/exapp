<?php

namespace App\Services\Sanction\Dto;

/**
 * รูปแบบกลางของ "หนึ่งรายชื่อ" ที่ source ทุกตัวต้อง return
 * scraper วันนี้และ APS API วันหน้าต้องผลิตสิ่งนี้เหมือนกัน
 */
class SanctionEntryDto
{
    /**
     * @param array<int, string> $names        ชื่อดิบทุกชื่อ (ชื่อหลัก + ชื่อแยกชิ้น + aka)
     * @param array<int, array{type: string, raw: string}> $identifiers
     */
    public function __construct(
        public readonly string $sourceRef,
        public readonly ?string $referenceNumber = null,
        public readonly ?string $notificationNumber = null,
        public readonly ?string $section = null,
        public readonly ?string $nameTh = null,
        public readonly ?string $nameEn = null,
        public readonly ?string $aka = null,
        public readonly ?string $dateOfBirth = null,
        public readonly ?string $nationality = null,
        public readonly ?string $address1 = null,
        public readonly ?string $address2 = null,
        public readonly ?string $phone = null,
        public readonly ?string $email = null,
        public readonly ?string $nationalId = null,
        public readonly ?string $companyRegistrationNumber = null,
        public readonly ?string $groupName = null,
        public readonly ?string $status = null,
        public readonly ?string $listedOn = null,
        public readonly ?string $asOfDate = null,
        public readonly array $names = [],
        public readonly array $identifiers = [],
        public readonly ?string $rowHash = null,
    ) {
    }

    /**
     * hash ของเนื้อหาที่มีความหมาย — ใช้ 2 อย่าง:
     *   1. ข้าม entry ที่ไม่เปลี่ยนตอน sync (ไม่ต้องเขียน DB ซ้ำ)
     *   2. ทำให้ false-positive clearance หมดอายุเมื่อ ปปง. แก้ข้อมูล (แผนที่ 2)
     *
     * ห้ามใส่ asOfDate ลงใน hash — ค่านั้นเปลี่ยนทุกวันแม้ข้อมูลคนไม่เปลี่ยน
     * ถ้าใส่ จะกลายเป็นว่าทุก entry "เปลี่ยน" ทุกวัน แล้ว re-scan จะวิ่งเต็มทุกคืน
     *
     * ห้ามใส่ rowHash ด้วย — เป็น hash ของหน้า list คนละชุดฟิลด์กัน
     */
    public function contentHash(): string
    {
        return hash('sha256', json_encode([
            $this->sourceRef,
            $this->referenceNumber,
            $this->notificationNumber,
            $this->section,
            $this->nameTh,
            $this->nameEn,
            $this->aka,
            $this->dateOfBirth,
            $this->nationality,
            $this->address1,
            $this->address2,
            $this->phone,
            $this->email,
            $this->nationalId,
            $this->companyRegistrationNumber,
            $this->groupName,
            $this->status,
            $this->listedOn,
            $this->names,
            $this->identifiers,
        ], JSON_UNESCAPED_UNICODE));
    }
}
