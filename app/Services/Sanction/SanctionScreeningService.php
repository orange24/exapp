<?php

namespace App\Services\Sanction;

use App\Models\Customer;
use App\Models\SanctionEntry;
use App\Models\SanctionFpClearance;
use App\Models\SanctionScreening;
use App\Models\SanctionScreeningMatch;
use App\Models\SanctionSyncRun;
use App\Services\Sanction\Dto\MatchCandidate;
use App\Services\Sanction\Dto\ScreeningInput;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SanctionScreeningService
{
    /** เหตุผลต้องยาวพอที่จะใช้ตอบ ปปง. ได้ — ถ้าปล่อยสั้นได้ 90% จะเป็น "ok" */
    public const MIN_REASON_LENGTH = 20;

    public function __construct(
        private readonly SanctionMatcher $matcher,
    ) {
    }

    /**
     * ตรวจและบันทึกหลักฐาน — บันทึกทุกครั้งแม้ผลเป็น clear
     * เพราะรายงานต้องตอบ ปปง. ได้ว่า "ธุรกรรมนี้ตรวจแล้ว ไม่เจอ"
     * ถ้าเก็บเฉพาะตอนเจอ เราจะพิสูจน์ไม่ได้ว่าได้ตรวจ
     */
    public function screen(
        ScreeningInput $input,
        string $trigger,
        ?int $screenedBy = null,
        ?int $customerId = null,
        ?int $transactionId = null,
        ?int $branchId = null,
        ?int $counterId = null,
    ): SanctionScreening {
        $candidates = $this->matcher->match($input);

        if ($customerId !== null) {
            $candidates = $this->dropClearedCandidates($candidates, $customerId);
        }

        $topScore = $candidates !== [] ? $candidates[0]->score : 0.0;
        $result = $this->classifyWithListRules($candidates, $topScore);

        return DB::transaction(function () use (
            $input, $trigger, $screenedBy, $customerId, $transactionId,
            $branchId, $counterId, $candidates, $topScore, $result
        ): SanctionScreening {
            $screening = SanctionScreening::create([
                'customer_id' => $customerId,
                'transaction_id' => $transactionId,
                'branch_id' => $branchId,
                'counter_id' => $counterId,
                'screened_by' => $screenedBy,
                'screened_at' => now(),
                'input_name' => $input->name,
                'input_id_type' => $input->idType,
                'input_id_number' => $input->idNumber,
                'input_nationality' => $input->nationality,
                'input_dob' => $input->dob,
                'sync_run_id' => $this->latestSuccessfulSyncRunId(),
                'trigger' => $trigger,
                'result' => $result,
                'top_score' => $topScore,
            ]);

            foreach ($candidates as $candidate) {
                SanctionScreeningMatch::create([
                    'screening_id' => $screening->id,
                    'sanction_entry_id' => $candidate->sanctionEntryId,
                    'match_type' => $candidate->matchType,
                    'score' => $candidate->score,
                    'matched_on' => $candidate->matchedOn,
                ]);
            }

            return $screening;
        });
    }

    /**
     * บันทึกการตัดสินใจ — ใช้ทั้งจากหน้าเคาน์เตอร์และหน้า review queue
     */
    public function decide(
        SanctionScreening $screening,
        string $decision,
        int $decidedBy,
        string $reason,
    ): SanctionScreening {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException(
                'ต้องระบุเหตุผลอย่างน้อย ' . self::MIN_REASON_LENGTH . ' ตัวอักษร'
            );
        }

        $allowed = [
            SanctionScreening::DECISION_FALSE_POSITIVE,
            SanctionScreening::DECISION_TRUE_MATCH,
            SanctionScreening::DECISION_ESCALATED,
        ];

        if (! in_array($decision, $allowed, true)) {
            throw new InvalidArgumentException("ไม่รู้จักการตัดสินใจ \"{$decision}\"");
        }

        return DB::transaction(function () use ($screening, $decision, $decidedBy, $reason): SanctionScreening {
            $screening->update([
                'decision' => $decision,
                'decided_by' => $decidedBy,
                'decided_at' => now(),
                'decision_reason' => $reason,
            ]);

            if ($decision === SanctionScreening::DECISION_FALSE_POSITIVE && $screening->customer_id !== null) {
                $this->recordClearances($screening, $decidedBy, $reason);
            }

            return $screening->fresh();
        });
    }

    /**
     * hr_02 / hr_08 ห้ามบล็อกแข็งทุกกรณี — เป็นข้อมูลประกอบการประเมินความเสี่ยง
     * ไม่ใช่หน้าที่อายัดตามกฎหมายเหมือน FREEZE-04/05
     *
     * logic บล็อกจึงผูกกับ list_code ไม่ใช่ผูกกับคะแนนเพียวๆ
     * เติมบัญชีใหม่เข้ามาแล้วพฤติกรรมไม่เพี้ยน
     *
     * @param array<int, MatchCandidate> $candidates
     */
    private function classifyWithListRules(array $candidates, float $topScore): string
    {
        $classification = MatchScorer::classify($topScore);

        if ($classification !== SanctionScreening::RESULT_CONFIRMED_MATCH) {
            return $classification;
        }

        foreach ($candidates as $candidate) {
            if ($candidate->score >= MatchScorer::SCORE_EXACT_ID
                && in_array($candidate->listCode, SanctionEntry::FREEZE_LISTS, true)) {
                return SanctionScreening::RESULT_CONFIRMED_MATCH;
            }
        }

        return SanctionScreening::RESULT_POTENTIAL_MATCH;
    }

    /**
     * ตัด candidate ที่ถูกตัดสินว่า false positive ไปแล้วออก
     * แต่ยังบันทึก screening อยู่ (หลักฐานไม่ขาดช่วง)
     *
     * clearance หมดอายุอัตโนมัติเมื่อฝั่งใดฝั่งหนึ่งเปลี่ยน
     *
     * @param array<int, MatchCandidate> $candidates
     * @return array<int, MatchCandidate>
     */
    private function dropClearedCandidates(array $candidates, int $customerId): array
    {
        if ($candidates === []) {
            return [];
        }

        $customer = Customer::find($customerId);

        if ($customer === null) {
            return $candidates;
        }

        $identityHash = SanctionFpClearance::identityHashFor($customer);

        $clearances = SanctionFpClearance::where('customer_id', $customerId)
            ->whereIn('sanction_entry_id', array_map(
                static fn (MatchCandidate $c): int => $c->sanctionEntryId,
                $candidates
            ))
            ->get()
            ->keyBy('sanction_entry_id');

        if ($clearances->isEmpty()) {
            return $candidates;
        }

        $entryHashes = SanctionEntry::whereIn('id', $clearances->keys())
            ->pluck('content_hash', 'id');

        return array_values(array_filter($candidates, static function (MatchCandidate $candidate) use ($clearances, $entryHashes, $identityHash): bool {
            $clearance = $clearances->get($candidate->sanctionEntryId);

            if ($clearance === null) {
                return true;
            }

            if ($clearance->customer_identity_hash !== $identityHash) {
                return true;   // ลูกค้าแก้ชื่อ/เปลี่ยนเอกสาร -> clearance หมดอายุ
            }

            if ($clearance->entry_content_hash !== ($entryHashes[$candidate->sanctionEntryId] ?? null)) {
                return true;   // ปปง. แก้ข้อมูลรายชื่อ -> clearance หมดอายุ
            }

            return false;
        }));
    }

    private function recordClearances(SanctionScreening $screening, int $clearedBy, string $reason): void
    {
        $customer = Customer::find($screening->customer_id);

        if ($customer === null) {
            return;
        }

        $identityHash = SanctionFpClearance::identityHashFor($customer);

        foreach ($screening->matches as $match) {
            $entry = SanctionEntry::find($match->sanction_entry_id);

            if ($entry === null) {
                continue;
            }

            SanctionFpClearance::updateOrCreate(
                [
                    'customer_id' => $customer->id,
                    'sanction_entry_id' => $entry->id,
                ],
                [
                    'cleared_by' => $clearedBy,
                    'cleared_at' => now(),
                    'reason' => $reason,
                    'entry_content_hash' => (string) $entry->content_hash,
                    'customer_identity_hash' => $identityHash,
                ]
            );
        }
    }

    /** ตรวจกับรายชื่อเวอร์ชันไหน — รายงานต้องตอบคำถามนี้ได้ */
    private function latestSuccessfulSyncRunId(): ?int
    {
        return SanctionSyncRun::where('status', SanctionSyncRun::STATUS_SUCCESS)
            ->latest('finished_at')
            ->value('id');
    }
}
