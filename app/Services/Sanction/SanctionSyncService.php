<?php

namespace App\Services\Sanction;

use App\Models\SanctionEntry;
use App\Models\SanctionEntryIdentifier;
use App\Models\SanctionEntryName;
use App\Models\SanctionSyncRun;
use App\Services\Sanction\Dto\SanctionEntryDto;
use App\Services\Sanction\Source\SanctionSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * diff -> validate -> commit
 *
 * คลาสนี้ไม่รู้จัก HTTP, HTML หรือ API เลย รับ SanctionFetchResult เข้ามาอย่างเดียว
 * ซึ่งแปลว่าตอนเปลี่ยนไปใช้ APS API ของ ปปง. ไฟล์นี้ไม่ต้องแก้สักบรรทัด
 *
 * สำคัญ: VALIDATE กับ COMMIT ต้องแยกกันเด็ดขาด — parse ทุกอย่างให้จบก่อน
 * ตรวจให้ผ่าน แล้วค่อยเขียน ห้ามเขียนไปตรวจไป ไม่งั้นถ้าพังกลางทาง
 * จะได้ฐานรายชื่อครึ่งๆ กลางๆ ซึ่งแย่กว่าไม่ sync เลย
 */
class SanctionSyncService
{
    public function __construct(
        private readonly SanctionNotifier $notifier,
    ) {
    }

    public function sync(SanctionSource $source, bool $force = false, ?int $forcedBy = null): SanctionSyncRun
    {
        $run = SanctionSyncRun::create([
            'list_code' => $source->listCode(),
            'source_adapter' => $source->adapterName(),
            'status' => SanctionSyncRun::STATUS_RUNNING,
            'started_at' => now(),
            'forced_by' => $force ? $forcedBy : null,
            'entries_before' => SanctionEntry::where('list_code', $source->listCode())->active()->count(),
        ]);

        try {
            return $this->runPipeline($run, $source, $force);
        } catch (Throwable $e) {
            Log::error('Sanction sync failed', [
                'list_code' => $source->listCode(),
                'error' => $e->getMessage(),
            ]);

            $run->update([
                'status' => SanctionSyncRun::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            $run = $run->fresh();

            $this->notifier->syncFailed($run);

            return $run;
        }
    }

    private function runPipeline(SanctionSyncRun $run, SanctionSource $source, bool $force): SanctionSyncRun
    {
        $listCode = $source->listCode();

        // --- เฟส 1: FETCH -------------------------------------------------
        // เซ็ตเต็มของคนที่ยังอยู่บนลิสต์ (ใช้ตัดสินว่าใครหายไป)
        $listRows = $source->fetchListRows();
        $presentRefs = array_column($listRows, 'source_ref');

        // hash ของแถวบนหน้า list ที่เราเก็บไว้ครั้งก่อน
        // ส่งให้ source ข้ามการดึงหน้า detail ของตัวที่แถวไม่ขยับ
        // (ต้องเป็น source_row_hash ไม่ใช่ content_hash — คนละหน้า คนละชุดฟิลด์
        //  ถ้าส่งผิดตัวจะไม่มีวันตรงกัน แล้วจะดึง detail ครบทุกใบทุกคืน)
        //
        // นับเฉพาะตัวที่ยัง active — คนที่เคยถูกถอดชื่อแล้วกลับขึ้นลิสต์อีกครั้ง
        // ต้องถือว่า "เปลี่ยน" เสมอ เพื่อให้ source ส่ง detail กลับมาแล้ว commit
        // เคลียร์ delisted_at ให้ ถ้าปล่อย hash เดิมไว้ source จะข้ามเขาไป
        // แล้วเขาจะค้างสถานะถอดชื่อตลอดกาลทั้งที่ ปปง. เอากลับขึ้นลิสต์แล้ว
        $knownRowHashes = SanctionEntry::where('list_code', $listCode)
            ->active()
            ->pluck('source_row_hash', 'source_ref')
            ->filter()
            ->all();

        $result = $source->fetch($knownRowHashes);

        // --- เฟส 4: VALIDATE (ก่อน COMMIT เสมอ) --------------------------
        $abort = $this->sanityCheck($run, count($listRows), count($result->failedRefs), $force);

        if ($abort !== null) {
            $run->update([
                'status' => SanctionSyncRun::STATUS_ABORTED_SANITY_CHECK,
                'error_message' => $abort,
                'entries_parsed' => count($listRows),
                'finished_at' => now(),
            ]);

            Log::warning('Sanction sync aborted by sanity check', [
                'list_code' => $listCode,
                'reason' => $abort,
            ]);

            $run = $run->fresh();

            $this->notifier->syncFailed($run);

            return $run;
        }

        // --- เฟส 5: COMMIT ------------------------------------------------
        $stats = DB::transaction(fn (): array => $this->commit($listCode, $result->entries, $presentRefs));

        $run->update([
            'status' => SanctionSyncRun::STATUS_SUCCESS,
            'source_as_of' => $result->asOfDate ?? $this->lastKnownAsOf($listCode),
            'entries_parsed' => count($listRows),
            'entries_added' => $stats['added'],
            'entries_updated' => $stats['updated'],
            'entries_removed' => $stats['removed'],
            'finished_at' => now(),
        ]);

        $fresh = $run->fresh();

        // แจ้งหลัง commit — และเฉพาะรอบที่รายชื่อขยับจริง
        // ถ้าแจ้งทุกรอบ กระดิ่งจะมีแต่ "sync สำเร็จ ไม่มีอะไรเปลี่ยน" ทุกคืน
        // แล้วคนจะเลิกกดดู รวมถึงคืนที่มีชื่อเพิ่มเข้ามาจริง
        if ($fresh->hasEntryChanges()) {
            $this->notifier->listUpdated($fresh);
        }

        return $fresh;
    }

    /**
     * รอบที่ไม่มีอะไรเปลี่ยน source จะไม่ได้ดึงหน้า detail เลย จึงไม่มี As Of กลับมา
     *
     * ถ้าปล่อยเป็น null รายงานสุขภาพการ sync จะอ่าน "ข้อมูล ปปง. ณ วันที่เท่าไหร่"
     * จากรอบล่าสุดไม่ได้ ทั้งที่ระบบทำงานปกติ — ซึ่งเป็นคำถามที่ผู้ตรวจถามตรง ๆ
     * จึงยกค่าล่าสุดที่เคยรู้มาใส่ไว้แทน
     */
    private function lastKnownAsOf(string $listCode): ?string
    {
        return SanctionSyncRun::where('list_code', $listCode)
            ->whereNotNull('source_as_of')
            ->latest('finished_at')
            ->value('source_as_of')?->format('Y-m-d');
    }

    /**
     * คืน null = ผ่าน, คืน string = เหตุผลที่ต้องยกเลิกทั้งรอบ
     *
     * เกณฑ์ 80% คือตัวกันตายของระบบทั้งก้อน วันที่ ปปง. เปลี่ยน layout หน้าเว็บ
     * parser จะอ่านได้ไม่กี่แถวแทนที่จะเป็นพันแถว ถ้าเขียนทับตามที่ parse ได้
     * รายชื่อจะหายเกือบหมดในคืนเดียว แล้วทุกคนจะผ่านฉลุยโดยไม่มีใครรู้
     * เพราะระบบจะดูทำงานปกติทุกประการ มีแต่ผลลัพธ์ clear ตลอด
     */
    private function sanityCheck(SanctionSyncRun $run, int $parsedCount, int $failedCount, bool $force): ?string
    {
        if ($force) {
            return null;
        }

        if ($parsedCount === 0) {
            return 'parse ได้ 0 แถว — ต้นทางว่างหรือ layout เปลี่ยน';
        }

        $before = (int) $run->entries_before;

        if ($before > 0) {
            $minRatio = (float) config('sanction.sanity.min_ratio_of_previous');
            $ratio = $parsedCount / $before;

            if ($ratio < $minRatio) {
                return sprintf(
                    'parse ได้ %d แถว จากเดิม %d (%.0f%%) ต่ำกว่าเกณฑ์ %.0f%% — ยกเลิกเพื่อไม่ให้รายชื่อถูกล้างทิ้ง',
                    $parsedCount,
                    $before,
                    $ratio * 100,
                    $minRatio * 100
                );
            }
        }

        if ($failedCount > 0) {
            $maxFailRatio = (float) config('sanction.sanity.max_detail_failure_ratio');
            $failRatio = $failedCount / max($parsedCount, 1);

            if ($failRatio > $maxFailRatio) {
                return sprintf(
                    'ดึงหน้า detail ไม่สำเร็จ %d จาก %d (%.0f%%) เกินเกณฑ์ %.0f%%',
                    $failedCount,
                    $parsedCount,
                    $failRatio * 100,
                    $maxFailRatio * 100
                );
            }
        }

        return null;
    }

    /**
     * @param array<int, SanctionEntryDto> $entries  เฉพาะตัวที่ใหม่หรือเปลี่ยน
     * @param array<int, string> $presentRefs        ทุก source_ref ที่ยังอยู่บนลิสต์
     * @return array{added: int, updated: int, removed: int}
     */
    private function commit(string $listCode, array $entries, array $presentRefs): array
    {
        $added = 0;
        $updated = 0;

        foreach ($entries as $dto) {
            $entry = SanctionEntry::where('list_code', $listCode)
                ->where('source_ref', $dto->sourceRef)
                ->first();

            $isNew = $entry === null;

            $attributes = [
                'reference_number' => $dto->referenceNumber,
                'notification_number' => $dto->notificationNumber,
                'section' => $dto->section,
                'name_th' => $dto->nameTh,
                'name_en' => $dto->nameEn,
                'aka' => $dto->aka,
                'date_of_birth' => $dto->dateOfBirth,
                'nationality' => $dto->nationality,
                'address_1' => $dto->address1,
                'address_2' => $dto->address2,
                'phone' => $dto->phone,
                'email' => $dto->email,
                'national_id' => $dto->nationalId,
                'company_registration_number' => $dto->companyRegistrationNumber,
                'group_name' => $dto->groupName,
                'status' => $dto->status,
                'listed_on' => $this->parseListedOn($dto->listedOn),
                'as_of_date' => $dto->asOfDate,
                'content_hash' => $dto->contentHash(),
                'source_row_hash' => $dto->rowHash,
                'last_seen_at' => now(),
                'delisted_at' => null,
            ];

            if ($isNew) {
                $entry = SanctionEntry::create($attributes + [
                    'list_code' => $listCode,
                    'source_ref' => $dto->sourceRef,
                    'first_seen_at' => now(),
                ]);
                $added++;

                $this->replaceNames($entry, $dto);
                $this->replaceIdentifiers($entry, $dto);

                continue;
            }

            // นับเป็น "เปลี่ยน" เฉพาะเมื่อเนื้อหาต่างจริง
            // แถวบนหน้า list อาจขยับด้วยเรื่องจัดรูปแบบโดยที่ข้อมูลคนไม่เปลี่ยน
            // ถ้านับมั่ว re-scan ในแผนที่ 2 จะวิ่งเต็มทุกคืนโดยไม่จำเป็น
            $contentChanged = $entry->content_hash !== $dto->contentHash();

            $entry->update($attributes);

            if ($contentChanged) {
                $updated++;
                $this->replaceNames($entry, $dto);
                $this->replaceIdentifiers($entry, $dto);
            }
        }

        // คนที่หายจากลิสต์ -> stamp delisted_at ไม่ DELETE
        // เพราะ sanction_screening_matches ชี้มาที่แถวนี้ ถ้าลบหลักฐานย้อนหลังจะพัง
        $removed = SanctionEntry::where('list_code', $listCode)
            ->whereNull('delisted_at')
            ->whereNotIn('source_ref', $presentRefs)
            ->update(['delisted_at' => now()]);

        return ['added' => $added, 'updated' => $updated, 'removed' => (int) $removed];
    }

    private function replaceNames(SanctionEntry $entry, SanctionEntryDto $dto): void
    {
        $entry->names()->delete();

        $rows = [];
        $seen = [];

        foreach ($dto->names as $index => $raw) {
            $normalized = NameNormalizer::normalize($raw);

            // ชื่อต้องมีตัวอักษรอย่างน้อยหนึ่งตัวถึงจะเอาไปแมตช์ได้
            //
            // ปปง. มีรายการที่ข้อมูลต้นทางว่างเปล่าจริง ๆ อยู่ (ชื่อเป็น "1." เฉย ๆ
            // ไม่มีเลขเอกสาร ไม่มีสัญชาติ) ถ้าเก็บเป็นชื่อที่ค้นได้ ชื่อนั้นจะเหลือ
            // token "1" แล้วลูกค้าที่ OCR ติดเลขมาจะได้ token_containment 85 คะแนน
            // = แถบแดงหน้าเคาน์เตอร์จากข้อมูลขยะ
            //
            // ยังเก็บตัว entry ไว้ เพราะมันอยู่บนบัญชีทางการจริง การลบทิ้ง
            // จะทำให้จำนวนรายชื่อที่เรารายงานไม่ตรงกับของ ปปง.
            if ($normalized === ''
                || preg_match('/\p{L}/u', $normalized) !== 1
                || isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;

            $rows[] = [
                'sanction_entry_id' => $entry->id,
                'name_raw' => mb_substr($raw, 0, 191),
                'name_normalized' => mb_substr($normalized, 0, 191),
                'name_soundex' => NameNormalizer::soundexOf($raw),
                'script' => NameNormalizer::detectScript($raw),
                'is_primary' => $index === 0,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            SanctionEntryName::insert($rows);
        }
    }

    private function replaceIdentifiers(SanctionEntry $entry, SanctionEntryDto $dto): void
    {
        $entry->identifiers()->delete();

        $rows = [];
        $seen = [];

        foreach ($dto->identifiers as $identifier) {
            $normalized = IdentifierNormalizer::normalize($identifier['raw']);

            if ($normalized === '') {
                continue;
            }

            $key = $identifier['type'] . '|' . $normalized;

            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $rows[] = [
                'sanction_entry_id' => $entry->id,
                'type' => $identifier['type'],
                'value_raw' => mb_substr($identifier['raw'], 0, 191),
                'value_normalized' => mb_substr($normalized, 0, 100),
                'issuing_country' => IdentifierNormalizer::issuingCountry($identifier['raw']),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($rows !== []) {
            SanctionEntryIdentifier::insert($rows);
        }
    }

    /** ต้นทางส่ง "22-02-2017" (d-m-Y) มา ไม่ใช่ ISO */
    private function parseListedOn(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $date = \DateTime::createFromFormat('d-m-Y', trim($value));

        return $date !== false ? $date->format('Y-m-d') : null;
    }
}
