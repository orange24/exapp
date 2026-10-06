<?php

namespace App\Services\Sanction;

use App\Models\SanctionEntryIdentifier;
use App\Models\SanctionEntryName;
use App\Models\SanctionScreeningMatch;
use App\Services\Sanction\Dto\MatchCandidate;
use App\Services\Sanction\Dto\ScreeningInput;

class SanctionMatcher
{
    public function __construct(
        private readonly NameRarityIndex $rarity,
    ) {
    }

    /** ดึง candidate ชื่อมาคำนวณ Levenshtein ได้ไม่เกินนี้ต่อการตรวจหนึ่งครั้ง */
    private const MAX_NAME_CANDIDATES = 50;

    /**
     * @return array<int, MatchCandidate> เรียงคะแนนมาก -> น้อย หนึ่งแถวต่อหนึ่ง entry
     */
    public function match(ScreeningInput $input): array
    {
        if ($input->isEmpty()) {
            return [];
        }

        $candidates = array_merge(
            $this->matchByIdentifier($input),
            $this->matchByName($input),
        );

        return $this->bestPerEntry($candidates);
    }

    /**
     * Tier 1 — index lookup ตรง ๆ แทบไม่มีต้นทุน
     *
     * @return array<int, MatchCandidate>
     */
    private function matchByIdentifier(ScreeningInput $input): array
    {
        $normalized = IdentifierNormalizer::normalize($input->idNumber);

        if ($normalized === '') {
            return [];
        }

        $rows = SanctionEntryIdentifier::query()
            ->where('value_normalized', $normalized)
            ->whereIn('type', [
                SanctionEntryIdentifier::TYPE_NATIONAL_ID,
                SanctionEntryIdentifier::TYPE_PASSPORT,
            ])
            ->with('entry')
            ->get()
            ->filter(fn (SanctionEntryIdentifier $i): bool => $i->entry !== null && $i->entry->delisted_at === null);

        $out = [];

        foreach ($rows as $identifier) {
            $entry = $identifier->entry;

            if ($identifier->type === SanctionEntryIdentifier::TYPE_NATIONAL_ID
                && IdentifierNormalizer::isThaiNationalId($normalized)) {
                // เลขบัตรไทย 13 หลักชนกันมั่วไม่ได้ และ Thailand list มีครบ 100%
                // เคสเดียวที่บล็อกแข็งได้สนิท
                $out[] = new MatchCandidate(
                    sanctionEntryId: $entry->id,
                    matchType: SanctionScreeningMatch::TYPE_EXACT_NATIONAL_ID,
                    score: MatchScorer::SCORE_EXACT_ID,
                    matchedOn: "เลขบัตรประชาชน {$identifier->value_raw}",
                    listCode: $entry->list_code,
                );

                continue;
            }

            // พาสปอร์ต: ต้นทางมีเลขสั้น 6 หลักอย่าง "Jordan 654781" ซึ่งชนกับ
            // พาสปอร์ตประเทศอื่นได้ไม่ยาก -> ต้องมีสัญชาติหรือวันเกิดยืนยันอีกชั้น
            // ก่อนจะตัดสิทธิ์ลูกค้า
            $nationality = MatchScorer::compareNationality($input->nationality, $entry->nationality);
            $dob = MatchScorer::compareDob($input->dob, $entry->date_of_birth);

            $corroborated = $nationality === 'match' || $dob === 'match';

            $type = $identifier->type === SanctionEntryIdentifier::TYPE_NATIONAL_ID
                ? SanctionScreeningMatch::TYPE_EXACT_NATIONAL_ID
                : SanctionScreeningMatch::TYPE_EXACT_PASSPORT;

            $out[] = new MatchCandidate(
                sanctionEntryId: $entry->id,
                matchType: $type,
                score: $corroborated ? MatchScorer::SCORE_EXACT_ID : MatchScorer::SCORE_PASSPORT_UNVERIFIED,
                matchedOn: "เลขเอกสาร {$identifier->value_raw}"
                    . ($corroborated ? ($nationality === 'match' ? ' + สัญชาติตรง' : ' + วันเกิดตรง') : ''),
                listCode: $entry->list_code,
            );
        }

        return $out;
    }

    /**
     * Tier 2 — กรอง candidate ให้แคบก่อนค่อยคำนวณ Levenshtein ใน PHP
     * ตาราง sanction_entry_names มีประมาณ 3,000-5,000 แถว
     * จะเอา Levenshtein ไปไล่ทุกแถวทุกครั้งไม่ไหว
     *
     * @return array<int, MatchCandidate>
     */
    private function matchByName(ScreeningInput $input): array
    {
        $tokens = NameNormalizer::tokens($input->name);

        if ($tokens === []) {
            return [];
        }

        $normalized = implode(' ', $tokens);
        $soundex = NameNormalizer::soundexOf($input->name);

        $out = [];

        foreach ($this->nameCandidates($normalized, $soundex, $tokens) as $row) {
            $entry = $row->entry;

            if ($entry === null) {
                continue;
            }

            $base = MatchScorer::nameScore($input->name, $row->name_raw);

            if ($base === null) {
                continue;
            }

            // ถ่วงน้ำหนักด้วยความหายากของ "ชื่อที่ไปตรง" ไม่ใช่ชื่อเต็มของ entry
            // เพราะสิ่งที่เป็นหลักฐานคือคำที่ตรงกันจริง ๆ
            $score = MatchScorer::applyModifiers(
                $base['score'],
                MatchScorer::compareNationality($input->nationality, $entry->nationality),
                MatchScorer::compareDob($input->dob, $entry->date_of_birth),
                $this->rarity->factorFor($row->name_raw),
            );

            if ($score <= 0.0) {
                continue;
            }

            $out[] = new MatchCandidate(
                sanctionEntryId: $entry->id,
                matchType: $base['type'],
                score: $score,
                matchedOn: "ชื่อ \"{$row->name_raw}\"",
                listCode: $entry->list_code,
            );
        }

        return $out;
    }

    /**
     * ดึง candidate ชื่อแบบ "แยก query ตามชั้นความแม่นยำ" ไม่ใช่ OR รวมแล้ว limit ทีเดียว
     *
     * ถ้ารวมเป็น query เดียวแล้ว limit 50 ชั้น LIKE จะกินโควตาจนหมดได้
     * — token อย่าง "MOHAMMED" แมตช์เป็นร้อยแถว แล้วแถวที่ชื่อตรงเป๊ะ
     * อาจไม่ติดมาใน 50 แถวแรกเลย กลายเป็นปล่อยคนที่ควรถูกจับผ่านไปเงียบ ๆ
     * โดยที่ระบบไม่มีอะไรฟ้องว่าผิดปกติ
     *
     * เรียงชั้นจากแม่นที่สุดไปหยาบที่สุด และให้แต่ละชั้นมีโควตาของตัวเอง
     *
     * @param array<int, string> $tokens
     * @return \Illuminate\Support\Collection<int, SanctionEntryName>
     */
    private function nameCandidates(string $normalized, string $soundex, array $tokens)
    {
        $collected = collect();

        $layer = function ($apply) use (&$collected): void {
            $query = SanctionEntryName::query()
                ->whereHas('entry', fn ($q) => $q->whereNull('delisted_at'))
                ->with('entry')
                ->limit(self::MAX_NAME_CANDIDATES);

            $apply($query);

            $collected = $collected->concat($query->get());
        };

        // ชั้น 1 — ชื่อ normalize แล้วตรงทั้งก้อน
        $layer(fn ($q) => $q->where('name_normalized', $normalized));

        // ชั้น 2 — เสียงพ้อง (เฉพาะชื่อ latin, ชื่อไทยคอลัมน์นี้ว่าง)
        if ($soundex !== '') {
            $layer(fn ($q) => $q->where('name_soundex', $soundex));
        }

        // ชั้น 3 — token ตัวใดตัวหนึ่งโผล่ในชื่อ จับ token_containment และ typo
        foreach ($tokens as $token) {
            if (mb_strlen($token) >= 3) {
                $layer(fn ($q) => $q->where('name_normalized', 'like', '%' . $token . '%'));
            }
        }

        return $collected->unique('id')->values();
    }

    /**
     * entry เดียวอาจ match ได้หลายทาง (เลขบัตร + ชื่อ) — เก็บทางที่คะแนนสูงสุดทางเดียว
     * ไม่งั้นแถบเตือนหน้าเคาน์เตอร์จะโชว์คนเดียวกันซ้ำหลายบรรทัด
     *
     * @param array<int, MatchCandidate> $candidates
     * @return array<int, MatchCandidate>
     */
    private function bestPerEntry(array $candidates): array
    {
        $best = [];

        foreach ($candidates as $candidate) {
            $id = $candidate->sanctionEntryId;

            if (! isset($best[$id]) || $candidate->score > $best[$id]->score) {
                $best[$id] = $candidate;
            }
        }

        $out = array_values($best);

        usort($out, static fn (MatchCandidate $a, MatchCandidate $b): int => $b->score <=> $a->score);

        return $out;
    }
}
