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
    ) {
    }

    public function withScore(float $score): self
    {
        return new self($this->sanctionEntryId, $this->matchType, $score, $this->matchedOn, $this->listCode);
    }
}
