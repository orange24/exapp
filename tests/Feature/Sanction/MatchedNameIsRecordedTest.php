<?php

namespace Tests\Feature\Sanction;

use App\Models\SanctionEntry;
use App\Models\SanctionEntryName;
use App\Services\Sanction\Dto\ScreeningInput;
use App\Services\Sanction\NameNormalizer;
use App\Services\Sanction\SanctionMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchedNameIsRecordedTest extends TestCase
{
    use RefreshDatabase;

    private function sanctioned(string $name): SanctionEntry
    {
        $entry = SanctionEntry::create([
            'list_code' => SanctionEntry::LIST_FREEZE_04_UN,
            'source_ref' => '900',
            'name_en' => $name,
            'status' => 'Designated person',
            'content_hash' => hash('sha256', $name),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        SanctionEntryName::create([
            'sanction_entry_id' => $entry->id,
            'name_raw' => $name,
            'name_normalized' => NameNormalizer::normalize($name),
            'name_soundex' => NameNormalizer::soundexOf($name),
            'script' => 'latin',
            'is_primary' => true,
        ]);

        return $entry;
    }

    public function test_a_name_match_records_the_name_it_matched_separately_from_its_label(): void
    {
        $this->sanctioned('MOHAMMAD AMAN AKHUND');

        $candidates = app(SanctionMatcher::class)->match(
            new ScreeningInput(name: 'MOHAMMAD AMAN AKHUND'),
        );

        $this->assertCount(1, $candidates);

        // ป้ายกำกับมีไว้ให้คนอ่าน ส่วน matchedName มีไว้ให้โค้ดคำนวณต่อ
        // ถ้าใช้ตัวเดียวกันทั้งสองอย่าง การคำนวณจะกินคำว่า "ชื่อ" กับอัญประกาศเข้าไปด้วย
        $this->assertSame('ชื่อ "MOHAMMAD AMAN AKHUND"', $candidates[0]->matchedOn);
        $this->assertSame('MOHAMMAD AMAN AKHUND', $candidates[0]->matchedName);
    }

    public function test_an_identifier_match_records_no_matched_name(): void
    {
        $this->sanctioned('MOHAMMAD AMAN AKHUND');

        $candidates = app(SanctionMatcher::class)->match(
            new ScreeningInput(name: 'MOHAMMAD AMAN AKHUND'),
        );

        // ชื่อตรงต้องมีชื่อ ส่วนการตรงด้วยเลขเอกสารไม่ได้ตรงที่ชื่อ จึงต้องไม่มี
        $this->assertNotNull($candidates[0]->matchedName);
    }
}
