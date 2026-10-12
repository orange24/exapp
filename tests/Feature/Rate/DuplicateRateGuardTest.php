<?php

namespace Tests\Feature\Rate;

use App\Models\CounterRate;
use App\Models\SuperrichRateBatch;
use App\Services\SuperrichRateService;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * กันราคาเบิ้ล
 *
 * เกิดขึ้นจริงบน production 2026-10-12: การกระจายชุดราคา #357 ถูกกดซ้อนกัน
 * สองครั้ง ด่านตรวจอ่านสถานะจาก object ในหน่วยความจำ "นอก" transaction แล้ว
 * ค่อยเปลี่ยนสถานะเป็น distributed ตอน "ท้าย" ของ transaction ทั้งสองครั้ง
 * จึงผ่านด่านไปพร้อมกัน ต่างคนต่างมองไม่เห็นแถวที่อีกฝ่ายยังไม่ commit
 * ผลคือราคาทุกตัวของ 3 เคาน์เตอร์มีสองแถว และจอแสดงราคาโชว์ซ้อนกัน
 */
class DuplicateRateGuardTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function batch(array $overrides = []): SuperrichRateBatch
    {
        return SuperrichRateBatch::create(array_merge([
            'status' => SuperrichRateBatch::STATUS_APPROVED,
            'target_counter_ids' => [$this->counter->id],
            'rates_data' => [[
                'denomination_id' => $this->denomination->id,
                'currency_code' => 'USD',
                'final_buy' => 33.25,
                'final_sell' => 33.75,
            ]],
            'created_by' => $this->adminUser->id,
        ], $overrides));
    }

    private function rateRows(): int
    {
        return CounterRate::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)
            ->whereDate('rate_date', Carbon::today())
            ->count();
    }

    public function test_distributing_a_batch_writes_one_row_per_denomination(): void
    {
        app(SuperrichRateService::class)->distributeBatch($this->batch(), $this->adminUser->id);

        $this->assertSame(1, $this->rateRows());
    }

    public function test_a_batch_already_distributed_by_someone_else_is_refused(): void
    {
        $batch = $this->batch();

        // อีกคนกดก่อนและทำเสร็จไปแล้ว — object ที่เราถืออยู่ยังจำว่า approved
        // โค้ดเดิมเชื่อค่าในหน่วยความจำแล้วเขียนทับซ้ำ ของใหม่ต้องอ่านใหม่จาก
        // ฐานข้อมูลพร้อมล็อกแถวไว้ แล้วปฏิเสธ
        DB::table('superrich_rate_batches')->where('id', $batch->id)->update([
            'status' => SuperrichRateBatch::STATUS_DISTRIBUTED,
        ]);

        $this->expectException(\RuntimeException::class);

        try {
            app(SuperrichRateService::class)->distributeBatch($batch, $this->adminUser->id);
        } finally {
            $this->assertSame(0, $this->rateRows(), 'ต้องไม่มีราคาถูกเขียนเลย');
        }
    }

    public function test_a_pending_batch_still_cannot_be_distributed(): void
    {
        $this->expectException(\RuntimeException::class);

        app(SuperrichRateService::class)->distributeBatch(
            $this->batch(['status' => SuperrichRateBatch::STATUS_PENDING]),
            $this->adminUser->id,
        );
    }

    public function test_distributing_the_same_batch_twice_in_a_row_is_refused(): void
    {
        $batch = $this->batch();

        app(SuperrichRateService::class)->distributeBatch($batch, $this->adminUser->id);

        try {
            app(SuperrichRateService::class)->distributeBatch($batch->fresh(), $this->adminUser->id);
            $this->fail('ครั้งที่สองต้องถูกปฏิเสธ');
        } catch (\RuntimeException) {
            // คาดไว้แล้ว
        }

        $this->assertSame(1, $this->rateRows());
    }

    public function test_the_database_itself_refuses_a_second_row_for_the_same_day(): void
    {
        // ด่านสุดท้าย ถ้ามีทางเขียนอื่นที่ยังไม่รู้จักหลุดมาได้ ฐานข้อมูลต้องไม่ยอม
        // ไม่ใช่เก็บไว้เงียบๆ แล้วไปโผล่ที่จอแสดงราคาหน้าร้าน
        $attrs = [
            'counter_id' => $this->counter->id,
            'denomination_id' => $this->denomination->id,
            'currency_code' => 'USD',
            'rate_date' => Carbon::today(),
        ];

        CounterRate::create($attrs + ['rate_buy' => 33.25, 'rate_sell' => 33.75]);

        $this->expectException(UniqueConstraintViolationException::class);

        CounterRate::create($attrs + ['rate_buy' => 99, 'rate_sell' => 99]);
    }

    public function test_the_same_denomination_on_a_different_day_is_still_allowed(): void
    {
        $attrs = [
            'counter_id' => $this->counter->id,
            'denomination_id' => $this->denomination->id,
            'currency_code' => 'USD',
            'rate_buy' => 33.25,
            'rate_sell' => 33.75,
        ];

        CounterRate::create($attrs + ['rate_date' => Carbon::today()]);
        CounterRate::create($attrs + ['rate_date' => Carbon::today()->subDay()]);

        $this->assertSame(2, CounterRate::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->count());
    }

    public function test_the_rate_board_shows_each_denomination_once(): void
    {
        app(SuperrichRateService::class)->distributeBatch($this->batch(), $this->adminUser->id);

        $response = $this->get('/rate/' . $this->counter->counter_code);
        $response->assertOk();

        $rates = $response->viewData('rates');
        $ids = $rates->pluck('denomination_id');

        $this->assertSame($ids->unique()->count(), $ids->count(), 'จอแสดงราคาต้องไม่มีบรรทัดซ้ำ');
    }
    public function test_saving_a_rate_twice_updates_instead_of_exploding(): void
    {
        // กดบันทึกรัวๆ ที่หน้าตั้งราคาไม่ควรเจอหน้าจอ error เพราะกุญแจฐานข้อมูล
        // ตัวช่วยกลางต้องกลืนการชนแล้วทำเป็นแก้ไขแถวเดิมแทน
        $keys = [
            'counter_id' => $this->counter->id,
            'denomination_id' => $this->denomination->id,
            'currency_code' => 'USD',
            'rate_date' => Carbon::today(),
        ];

        CounterRate::putRate($keys, ['rate_buy' => 33.25, 'rate_sell' => 33.75]);
        CounterRate::putRate($keys, ['rate_buy' => 34.00, 'rate_sell' => 34.50]);

        $this->assertSame(1, $this->rateRows());
        $this->assertEquals(34.00, (float) CounterRate::first()->rate_buy);
    }

    public function test_the_helper_still_writes_the_row_when_another_process_got_there_first(): void
    {
        $keys = [
            'counter_id' => $this->counter->id,
            'denomination_id' => $this->denomination->id,
            'currency_code' => 'USD',
            'rate_date' => Carbon::today(),
        ];

        // อีกโปรเซสแทรกแถวเข้ามาหลังจากเราเช็คว่ายังไม่มี — จำลองด้วยการใส่ตรงๆ
        DB::table('counter_rates')->insert($keys + [
            'rate_date' => Carbon::today()->toDateTimeString(),
            'rate_buy' => 1, 'rate_sell' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        CounterRate::putRate($keys, ['rate_buy' => 33.25, 'rate_sell' => 33.75]);

        $this->assertSame(1, $this->rateRows());
        $this->assertEquals(33.25, (float) CounterRate::first()->rate_buy, 'ค่าที่ตั้งใจเขียนต้องไม่หาย');
    }
}