<?php

namespace Tests\Feature\Sanction;

use App\Models\SanctionEntry;
use App\Models\SanctionEntryName;
use App\Models\SanctionNameToken;
use App\Services\Sanction\NameNormalizer;
use App\Services\Sanction\NameRarityIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NameRarityIndexTest extends TestCase
{
    use RefreshDatabase;

    private function seedNames(array $names): void
    {
        $entry = SanctionEntry::create([
            'list_code' => SanctionEntry::LIST_FREEZE_04_UN,
            'source_ref' => '1',
            'name_en' => 'seed',
            'status' => 'Designated person',
            'content_hash' => hash('sha256', 'seed'),
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ]);

        foreach ($names as $i => $n) {
            SanctionEntryName::create([
                'sanction_entry_id' => $entry->id,
                'name_raw' => $n,
                'name_normalized' => NameNormalizer::normalize($n),
                'name_soundex' => NameNormalizer::soundexOf($n),
                'script' => 'latin',
                'is_primary' => $i === 0,
            ]);
        }
    }

    public function test_rebuild_counts_how_many_names_each_token_appears_in(): void
    {
        $this->seedNames(['MOHAMED AHMED', 'MOHAMED ALI', 'RARE PERSON']);

        $unique = app(NameRarityIndex::class)->rebuild();

        $this->assertSame(5, $unique);   // MOHAMED, AHMED, ALI, RARE, PERSON
        $this->assertSame(2, SanctionNameToken::where('token', 'MOHAMED')->value('document_frequency'));
        $this->assertSame(1, SanctionNameToken::where('token', 'RARE')->value('document_frequency'));
    }

    public function test_a_common_token_carries_less_information_than_a_rare_one(): void
    {
        $names = [];
        for ($i = 1; $i <= 250; $i++) {
            $names[] = $i <= 200 ? "MOHAMED PERSON{$i}" : "UNIQUEWORD{$i} PERSON{$i}";
        }
        $this->seedNames($names);

        $index = app(NameRarityIndex::class);
        $index->rebuild();

        $this->assertLessThan(
            $index->informationOf('UNIQUEWORD201'),
            $index->informationOf('MOHAMED'),
            'คำที่ปรากฏ 200 ชื่อ ต้องมีค่าข้อมูลน้อยกว่าคำที่ปรากฏชื่อเดียว'
        );
    }

    /**
     * คุณสมบัติด้านความปลอดภัยที่มองไม่เห็น — การไม่มีสถิติต้องไม่ทำให้เตือนน้อยลง
     *
     * เกิดได้จริงตอนเพิ่งติดตั้ง ยังไม่ได้ sync หรือดัชนียังไม่ถูกสร้าง
     * ถ้าปล่อยให้ลดคะแนนตอนนั้น ระบบจะเงียบสนิทโดยดูเหมือนทำงานปกติ
     */
    public function test_no_discount_is_applied_when_the_index_was_never_built(): void
    {
        $this->seedNames(['MOHAMED AHMED', 'MOHAMED ALI']);

        $this->assertSame(1.0, app(NameRarityIndex::class)->factorFor('MOHAMED'));
    }

    public function test_no_discount_is_applied_when_the_corpus_is_too_small_to_mean_anything(): void
    {
        $this->seedNames(['MOHAMED AHMED', 'MOHAMED ALI', 'MOHAMED HASSAN']);

        $index = app(NameRarityIndex::class);
        $index->rebuild();

        // MOHAMED อยู่ในทุกชื่อ แต่คลังมีแค่ 3 ชื่อ — สถิติยังเชื่อไม่ได้
        $this->assertSame(1.0, $index->factorFor('MOHAMED'));
    }

    public function test_discount_applies_once_the_corpus_is_large_enough(): void
    {
        $names = [];
        for ($i = 1; $i <= 250; $i++) {
            $names[] = "MOHAMED PERSON{$i}";
        }
        $this->seedNames($names);

        $index = app(NameRarityIndex::class);
        $index->rebuild();

        $this->assertLessThan(1.0, $index->factorFor('MOHAMED'));
    }

    public function test_index_reports_itself_stale_when_names_exist_but_no_tokens_do(): void
    {
        $this->seedNames(['MOHAMED ALI', 'AHMED HASSAN']);

        // deploy ใหม่มีรายชื่ออยู่แล้วแต่ยังไม่เคยสร้างดัชนี — sync ต้องรู้ว่าต้องสร้าง
        // ไม่ใช่รอให้รายชื่อเปลี่ยนก่อน ไม่งั้นฟีเจอร์หลับไปจนกว่า ปปง. จะแก้ประกาศ
        $this->assertTrue(app(NameRarityIndex::class)->isStale());
    }

    public function test_index_is_not_stale_once_built(): void
    {
        $this->seedNames(['MOHAMED ALI', 'AHMED HASSAN']);
        app(NameRarityIndex::class)->rebuild();

        $this->assertFalse(app(NameRarityIndex::class)->isStale());
    }

    public function test_index_is_not_stale_when_there_are_no_names_to_index(): void
    {
        // ไม่มีรายชื่อเลย ดัชนีว่างเป็นเรื่องถูกต้อง อย่าให้ sync วน rebuild เปล่า ๆ
        $this->assertFalse(app(NameRarityIndex::class)->isStale());
    }
}
