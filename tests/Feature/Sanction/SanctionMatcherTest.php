<?php

namespace Tests\Feature\Sanction;

use App\Models\SanctionEntry;
use App\Models\SanctionEntryIdentifier;
use App\Models\SanctionEntryName;
use App\Services\Sanction\Dto\ScreeningInput;
use App\Services\Sanction\MatchScorer;
use App\Services\Sanction\NameNormalizer;
use App\Services\Sanction\SanctionMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctionMatcherTest extends TestCase
{
    use RefreshDatabase;

    private function makeEntry(
        string $nameEn,
        ?string $nationalId = null,
        ?string $passport = null,
        string $nationality = 'TH',
        ?string $dob = '18-12-1981',
        string $listCode = SanctionEntry::LIST_FREEZE_05_TH,
        ?string $passportCountry = null,
    ): SanctionEntry {
        $entry = SanctionEntry::create([
            'list_code' => $listCode,
            'source_ref' => (string) random_int(1000, 999999),
            'name_en' => $nameEn,
            'nationality' => $nationality,
            'date_of_birth' => $dob,
            'national_id' => $nationalId,
            'status' => 'Designated person',
            'content_hash' => hash('sha256', $nameEn),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        SanctionEntryName::create([
            'sanction_entry_id' => $entry->id,
            'name_raw' => $nameEn,
            'name_normalized' => NameNormalizer::normalize($nameEn),
            'name_soundex' => NameNormalizer::soundexOf($nameEn),
            'script' => NameNormalizer::detectScript($nameEn),
            'is_primary' => true,
        ]);

        if ($nationalId !== null) {
            SanctionEntryIdentifier::create([
                'sanction_entry_id' => $entry->id,
                'type' => 'national_id',
                'value_raw' => $nationalId,
                'value_normalized' => $nationalId,
            ]);
        }

        if ($passport !== null) {
            SanctionEntryIdentifier::create([
                'sanction_entry_id' => $entry->id,
                'type' => 'passport',
                'value_raw' => ($passportCountry !== null ? $passportCountry . ' ' : '') . $passport,
                'value_normalized' => $passport,
                'issuing_country' => $passportCountry,
            ]);
        }

        return $entry;
    }

    private function matcher(): SanctionMatcher
    {
        return app(SanctionMatcher::class);
    }

    public function test_exact_thai_national_id_scores_100(): void
    {
        $entry = $this->makeEntry('AMRAN MING', nationalId: '5960500028101');

        $candidates = $this->matcher()->match(new ScreeningInput(
            name: 'SOMEONE ELSE',
            idType: 'national_id',
            idNumber: '5960500028101',
        ));

        $this->assertCount(1, $candidates);
        $this->assertSame($entry->id, $candidates[0]->sanctionEntryId);
        $this->assertSame('exact_national_id', $candidates[0]->matchType);
        $this->assertSame(100.0, $candidates[0]->score);
    }

    public function test_passport_match_alone_scores_90_not_100(): void
    {
        $this->makeEntry(
            'IYAD KHALIL',
            passport: '654781',
            nationality: 'Jordan',
            dob: null,
            listCode: SanctionEntry::LIST_FREEZE_04_UN,
            passportCountry: 'Jordan',
        );

        $candidates = $this->matcher()->match(new ScreeningInput(
            name: 'UNRELATED NAME',
            idType: 'passport',
            idNumber: '654781',
            nationality: 'THA',
        ));

        $this->assertCount(1, $candidates);
        $this->assertSame('exact_passport', $candidates[0]->matchType);
        $this->assertSame(
            90.0,
            $candidates[0]->score,
            'พาสปอร์ตเลขสั้นห้ามบล็อกแข็งเดี่ยวๆ — ต้องมีสัญชาติหรือวันเกิดยืนยัน'
        );
    }

    public function test_passport_plus_matching_nationality_scores_100(): void
    {
        $this->makeEntry(
            'IYAD KHALIL',
            passport: '654781',
            nationality: 'Jordan',
            dob: null,
            listCode: SanctionEntry::LIST_FREEZE_04_UN,
            passportCountry: 'Jordan',
        );

        $candidates = $this->matcher()->match(new ScreeningInput(
            name: 'UNRELATED NAME',
            idType: 'passport',
            idNumber: '654781',
            nationality: 'JOR',
        ));

        $this->assertSame(100.0, $candidates[0]->score);
    }

    public function test_passport_plus_matching_dob_scores_100(): void
    {
        $this->makeEntry(
            'IYAD KHALIL',
            passport: '654781',
            nationality: 'Jordan',
            dob: '18-12-1981',
            listCode: SanctionEntry::LIST_FREEZE_04_UN,
            passportCountry: 'Jordan',
        );

        $candidates = $this->matcher()->match(new ScreeningInput(
            name: 'UNRELATED NAME',
            idType: 'passport',
            idNumber: '654781',
            nationality: 'THA',
            dob: '1981-12-18',
        ));

        $this->assertSame(100.0, $candidates[0]->score);
    }

    public function test_name_match_with_matching_nationality_and_dob_is_capped_at_99(): void
    {
        $this->makeEntry('AMRAN MING', nationality: 'TH', dob: '18-12-1981');

        $candidates = $this->matcher()->match(new ScreeningInput(
            name: 'AMRAN MING',
            nationality: 'THA',
            dob: '1981-12-18',
        ));

        $this->assertSame('name_exact', $candidates[0]->matchType);
        $this->assertSame(99.0, $candidates[0]->score);
    }

    public function test_swapped_name_order_still_matches(): void
    {
        $this->makeEntry('AMRAN MING');

        $candidates = $this->matcher()->match(new ScreeningInput(name: 'MING AMRAN'));

        $this->assertNotEmpty($candidates);
    }

    public function test_title_prefix_is_ignored(): void
    {
        $this->makeEntry('AMRAN MING');

        $candidates = $this->matcher()->match(new ScreeningInput(name: 'MR. AMRAN MING'));

        $this->assertSame('name_exact', $candidates[0]->matchType);
    }

    public function test_mismatching_nationality_drags_score_down(): void
    {
        $this->makeEntry('AMRAN MING', nationality: 'TH', dob: null);

        $candidates = $this->matcher()->match(new ScreeningInput(
            name: 'AMRAN MING',
            nationality: 'JPN',
        ));

        $this->assertLessThan(85.0, $candidates[0]->score);
        $this->assertGreaterThan(0.0, $candidates[0]->score);
    }

    public function test_mismatching_dob_drops_an_exact_name_match_out_of_red_but_not_out_of_sight(): void
    {
        $this->makeEntry('AMRAN MING', nationality: 'TH', dob: '18-12-1981');

        $candidates = $this->matcher()->match(new ScreeningInput(
            name: 'AMRAN MING',
            nationality: 'THA',
            dob: '1995-01-01',
        ));

        // เดิมเทสนี้ล็อกไว้ว่าต้องต่ำกว่า 70 คือเงียบสนิท
        // แต่วันเกิดมาจากมือพนักงาน พิมพ์ผิดหลักเดียวชื่อที่ตรงเป๊ะจะหายไปทั้งใบ
        // จึงตั้งพื้นไว้ที่เกณฑ์เตือน: ออกจากแถบแดงตามเจตนาเดิม แต่ยังมีคนเห็น
        $this->assertLessThan(MatchScorer::redThreshold(), $candidates[0]->score);
        $this->assertSame('orange', MatchScorer::severity($candidates[0]->score));
    }

    public function test_delisted_entries_are_not_matched(): void
    {
        $entry = $this->makeEntry('AMRAN MING', nationalId: '5960500028101');
        $entry->update(['delisted_at' => now()]);

        $candidates = $this->matcher()->match(new ScreeningInput(
            name: 'AMRAN MING',
            idType: 'national_id',
            idNumber: '5960500028101',
        ));

        $this->assertSame([], $candidates);
    }

    public function test_unrelated_customer_matches_nothing(): void
    {
        $this->makeEntry('AMRAN MING', nationalId: '5960500028101');

        $candidates = $this->matcher()->match(new ScreeningInput(
            name: 'SOMCHAI JAIDEE',
            idType: 'national_id',
            idNumber: '1100700123456',
        ));

        $this->assertSame([], $candidates);
    }

    public function test_only_the_best_candidate_per_entry_is_returned(): void
    {
        // entry เดียวแต่ match ได้ทั้งเลขบัตรและชื่อ -> ต้องเหลือแถวเดียว
        $this->makeEntry('AMRAN MING', nationalId: '5960500028101');

        $candidates = $this->matcher()->match(new ScreeningInput(
            name: 'AMRAN MING',
            idType: 'national_id',
            idNumber: '5960500028101',
        ));

        $this->assertCount(1, $candidates);
        $this->assertSame(100.0, $candidates[0]->score);
    }

    public function test_exact_name_match_is_not_crowded_out_by_common_token_noise(): void
    {
        // ชื่ออาหรับ/มุสลิมใน UN list ใช้ token ซ้ำกันเยอะมาก (MOHAMMED, ABDUL, AL)
        // ถ้าดึง candidate ด้วย query เดียวแล้ว limit ชั้น LIKE จะกินโควตาจนหมด
        // แล้วแถวที่ชื่อตรงเป๊ะอาจไม่ติดมาเลย = ปล่อยคนที่ควรถูกจับผ่านไปเงียบ ๆ
        for ($i = 1; $i <= 60; $i++) {
            $this->makeEntry("MOHAMMED DECOY{$i}", nationality: 'TH', dob: null);
        }

        // ตัวจริงถูกสร้างท้ายสุด -> id สูงสุด -> ถ้า limit ไม่มี ORDER BY จะตกหล่นง่ายที่สุด
        $target = $this->makeEntry('MOHAMMED ALFULANI', nationality: 'TH', dob: null);

        $candidates = $this->matcher()->match(new ScreeningInput(
            name: 'MOHAMMED ALFULANI',
            nationality: 'TH',
        ));

        $ids = array_map(
            static fn ($c): int => $c->sanctionEntryId,
            $candidates
        );

        $this->assertContains(
            $target->id,
            $ids,
            'ชื่อที่ตรงเป๊ะต้องไม่ถูก token ที่พบบ่อยเบียดตกจากชุด candidate'
        );
    }

    public function test_candidates_are_sorted_by_score_descending(): void
    {
        $this->makeEntry('AMRAN MING', nationality: 'TH', dob: null);
        $this->makeEntry('AMRAN MINGG', nationality: 'TH', dob: null);

        $candidates = $this->matcher()->match(new ScreeningInput(name: 'AMRAN MING', nationality: 'TH'));

        $this->assertGreaterThanOrEqual(2, count($candidates));
        $this->assertGreaterThanOrEqual($candidates[1]->score, $candidates[0]->score);
    }
}
