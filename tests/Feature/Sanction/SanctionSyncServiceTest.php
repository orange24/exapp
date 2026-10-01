<?php

namespace Tests\Feature\Sanction;

use App\Models\SanctionEntry;
use App\Models\SanctionEntryIdentifier;
use App\Models\SanctionEntryName;
use App\Models\SanctionSyncRun;
use App\Services\Sanction\SanctionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSanctionSource;
use Tests\TestCase;

class SanctionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private function sourceWith(array $entries, array $failedRefs = []): FakeSanctionSource
    {
        return new FakeSanctionSource(
            listCode: SanctionEntry::LIST_FREEZE_05_TH,
            entries: $entries,
            listRows: FakeSanctionSource::listRowsFor($entries),
            failedRefs: $failedRefs,
        );
    }

    private function service(): SanctionSyncService
    {
        return app(SanctionSyncService::class);
    }

    public function test_first_sync_writes_entries_names_and_identifiers(): void
    {
        $entries = [
            FakeSanctionSource::entry('1', 'AMRAN MING', '5960500028101'),
            FakeSanctionSource::entry('2', 'ROWI HAYIDING'),
        ];

        $run = $this->service()->sync($this->sourceWith($entries));

        $this->assertSame(SanctionSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(2, $run->entries_added);
        $this->assertSame(2, SanctionEntry::count());
        $this->assertSame(1, SanctionEntryIdentifier::where('type', 'national_id')->count());
        $this->assertSame(
            '5960500028101',
            SanctionEntryIdentifier::where('type', 'national_id')->first()->value_normalized
        );
        $this->assertGreaterThanOrEqual(2, SanctionEntryName::count());
    }

    public function test_names_get_normalized_and_soundex_filled_for_latin(): void
    {
        $this->service()->sync($this->sourceWith([
            FakeSanctionSource::entry('1', 'MING AMRAN'),
        ]));

        $name = SanctionEntryName::first();

        $this->assertSame('AMRAN MING', $name->name_normalized);
        $this->assertSame('latin', $name->script);
        $this->assertNotSame('', $name->name_soundex);
    }

    public function test_second_sync_with_same_data_changes_nothing(): void
    {
        $entries = [FakeSanctionSource::entry('1', 'AMRAN MING', '5960500028101')];

        $this->service()->sync($this->sourceWith($entries));
        $run = $this->service()->sync($this->sourceWith($entries));

        $this->assertSame(SanctionSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(0, $run->entries_added);
        $this->assertSame(0, $run->entries_updated);
        $this->assertSame(0, $run->entries_removed);
        $this->assertSame(1, SanctionEntry::count());
    }

    public function test_new_entry_is_added_on_second_sync(): void
    {
        $this->service()->sync($this->sourceWith([
            FakeSanctionSource::entry('1', 'AMRAN MING'),
        ]));

        $run = $this->service()->sync($this->sourceWith([
            FakeSanctionSource::entry('1', 'AMRAN MING'),
            FakeSanctionSource::entry('2', 'NEW PERSON'),
        ]));

        $this->assertSame(1, $run->entries_added);
        $this->assertSame(2, SanctionEntry::count());
        $this->assertNotNull(SanctionEntry::where('source_ref', '2')->first());
    }

    public function test_missing_entry_is_delisted_not_deleted(): void
    {
        // ต้องใช้ลิสต์ 10 ชื่อ ไม่ใช่ 2 ชื่อ — หายไป 1 จาก 10 เหลือ 90% ยังผ่านเกณฑ์ 80%
        // ถ้าใช้ 2 ชื่อแล้วหายไป 1 จะเหลือ 50% ซึ่ง sanity check ต้องยกเลิกทั้งรอบ
        // (พฤติกรรมนั้นมีเทสของตัวเองอยู่แล้วด้านล่าง)
        $full = [];
        for ($i = 1; $i <= 10; $i++) {
            $full[] = FakeSanctionSource::entry((string) $i, "PERSON {$i}");
        }

        $this->service()->sync($this->sourceWith($full));

        $run = $this->service()->sync($this->sourceWith(array_slice($full, 0, 9)));

        $this->assertSame(SanctionSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(1, $run->entries_removed);
        $this->assertSame(10, SanctionEntry::count(), 'ห้ามลบแถว — หลักฐานย้อนหลังจะพัง');

        $gone = SanctionEntry::where('source_ref', '10')->first();
        $this->assertNotNull($gone->delisted_at);

        $this->assertSame(9, SanctionEntry::active()->count());
    }

    public function test_relisted_entry_clears_delisted_at(): void
    {
        // 10 ชื่อด้วยเหตุผลเดียวกับเทสข้างบน: 10 -> 9 -> 10 ผ่านเกณฑ์ 80% ทุกรอบ
        $full = [];
        for ($i = 1; $i <= 10; $i++) {
            $full[] = FakeSanctionSource::entry((string) $i, "PERSON {$i}");
        }

        $this->service()->sync($this->sourceWith($full));
        $this->service()->sync($this->sourceWith(array_slice($full, 0, 9)));

        // ต้องถูกถอดชื่อจริงก่อน ไม่งั้นเทสรอบที่ 3 จะผ่านแบบไม่ได้พิสูจน์อะไร
        $this->assertNotNull(SanctionEntry::where('source_ref', '10')->first()->delisted_at);

        $run = $this->service()->sync($this->sourceWith($full));

        $this->assertSame(SanctionSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertNull(SanctionEntry::where('source_ref', '10')->first()->delisted_at);
        $this->assertSame(10, SanctionEntry::active()->count());
    }

    public function test_aborts_when_row_count_drops_below_eighty_percent(): void
    {
        $full = [];
        for ($i = 1; $i <= 100; $i++) {
            $full[] = FakeSanctionSource::entry((string) $i, "PERSON {$i}");
        }

        $this->service()->sync($this->sourceWith($full));
        $this->assertSame(100, SanctionEntry::count());

        // เหลือ 50 จาก 100 = 50% ต่ำกว่าเกณฑ์ 80%
        $run = $this->service()->sync($this->sourceWith(array_slice($full, 0, 50)));

        $this->assertSame(SanctionSyncRun::STATUS_ABORTED_SANITY_CHECK, $run->status);
        $this->assertSame(100, SanctionEntry::count(), 'ห้ามเขียนอะไรเลยเมื่อ sanity check ไม่ผ่าน');
        $this->assertSame(0, SanctionEntry::whereNotNull('delisted_at')->count());
    }

    public function test_aborts_when_list_is_empty(): void
    {
        $this->service()->sync($this->sourceWith([
            FakeSanctionSource::entry('1', 'AMRAN MING'),
        ]));

        $run = $this->service()->sync($this->sourceWith([]));

        $this->assertSame(SanctionSyncRun::STATUS_ABORTED_SANITY_CHECK, $run->status);
        $this->assertSame(1, SanctionEntry::count());
    }

    public function test_force_overrides_sanity_check_and_records_who_forced(): void
    {
        $full = [];
        for ($i = 1; $i <= 100; $i++) {
            $full[] = FakeSanctionSource::entry((string) $i, "PERSON {$i}");
        }

        $this->service()->sync($this->sourceWith($full));

        $run = $this->service()->sync(
            $this->sourceWith(array_slice($full, 0, 50)),
            force: true,
            forcedBy: null,
        );

        $this->assertSame(SanctionSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(50, $run->entries_removed);
        $this->assertSame(50, SanctionEntry::active()->count());
    }

    public function test_aborts_when_too_many_detail_pages_failed(): void
    {
        $entries = [];
        for ($i = 1; $i <= 10; $i++) {
            $entries[] = FakeSanctionSource::entry((string) $i, "PERSON {$i}");
        }

        // 3 จาก 10 = 30% เกินเกณฑ์ 10%
        $run = $this->service()->sync($this->sourceWith($entries, failedRefs: ['8', '9', '10']));

        $this->assertSame(SanctionSyncRun::STATUS_ABORTED_SANITY_CHECK, $run->status);
        $this->assertSame(0, SanctionEntry::count());
    }

    public function test_tolerates_small_number_of_failed_detail_pages(): void
    {
        $entries = [];
        for ($i = 1; $i <= 100; $i++) {
            $entries[] = FakeSanctionSource::entry((string) $i, "PERSON {$i}");
        }

        // 5 จาก 100 = 5% ต่ำกว่าเกณฑ์ 10%
        $run = $this->service()->sync($this->sourceWith($entries, failedRefs: ['96', '97', '98', '99', '100']));

        $this->assertSame(SanctionSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertSame(100, SanctionEntry::count());
    }

    public function test_records_source_adapter_and_as_of_on_the_run(): void
    {
        $run = $this->service()->sync($this->sourceWith([
            FakeSanctionSource::entry('1', 'AMRAN MING'),
        ]));

        $this->assertSame('fake', $run->source_adapter);
        $this->assertSame('2026-10-01', $run->source_as_of->format('Y-m-d'));
        $this->assertNotNull($run->finished_at);
    }
}
