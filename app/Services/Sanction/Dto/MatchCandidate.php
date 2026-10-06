<?php

namespace App\Services\Sanction\Dto;

class MatchCandidate
{
    public function __construct(
        public readonly int $sanctionEntryId,
        public readonly string $matchType,
        public readonly float $score,
        public readonly string $matchedOn,
        public readonly string $listCode,
        /** ชื่อที่ตรงกันจริง — แยกจาก matchedOn ที่เป็นข้อความให้คนอ่าน
         *  null เมื่อ match ด้วยเลขเอกสาร ซึ่งไม่ได้ตรงที่ชื่อ */
        public readonly ?string $matchedName = null,
    ) {
    }

    public function withScore(float $score): self
    {
        return new self($this->sanctionEntryId, $this->matchType, $score, $this->matchedOn, $this->listCode, $this->matchedName);
    }
}
