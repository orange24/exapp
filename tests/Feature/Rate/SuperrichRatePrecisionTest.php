<?php

namespace Tests\Feature\Rate;

use App\Models\CounterRate;
use App\Models\CurrencyDenomination;
use App\Models\SuperrichAdjustment;
use App\Models\SuperrichRate;
use App\Models\SuperrichRateBatch;
use App\Services\SuperrichRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * เรตของสกุลที่ค่าต่ำมาก (VND, IDR) ต้องเก็บ 6 ทศนิยมให้ตรงกับคอลัมน์
 * counter_rates.rate_buy DECIMAL(12,6) และหน้าตั้งราคาปกติที่ใช้ step="0.000001"
 *
 * ถ้าปัดเหลือ 4 ตำแหน่ง VND จะคลาดจากราคาจริงเกิน 1.5% ซึ่งไม่ใช่แค่เรื่อง
 * การแสดงผล — ค่านี้ถูกเขียนลง counter_rates แล้วเอาไปคิดเงินหน้าเคาน์เตอร์
 */
class SuperrichRatePrecisionTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function vndDenomination(): CurrencyDenomination
    {
        return CurrencyDenomination::firstOrCreate(
            ['currency_code' => 'VND', 'denom_label' => '500000-10000'],
            ['display_name' => 'VND 500000-10000', 'seq' => 90, 'is_active' => true]
        );
    }

    /** ตัวเลขชุดนี้มาจากหน้าจอจริงที่ผู้ใช้รายงาน */
    private function seedVndRate(): CurrencyDenomination
    {
        $denom = $this->vndDenomination();

        SuperrichRate::create([
            'currency_code' => 'VND',
            'superrich_denom' => '500000-10000',
            'denomination_id' => $denom->id,
            'rate_buy' => 0.00127,
            'rate_sell' => 0.00132,
            'fetched_at' => now(),
        ]);

        SuperrichAdjustment::create([
            'denomination_id' => $denom->id,
            'adj_rate_buy' => -0.00005,
            'adj_rate_sell' => 0.00003,
        ]);

        return $denom;
    }

    public function test_batch_keeps_six_decimals_for_low_value_currencies(): void
    {
        $denom = $this->seedVndRate();

        $batch = app(SuperrichRateService::class)
            ->createBatch($this->adminUser->id, [$this->counter->id]);

        $row = collect($batch->rates_data)
            ->firstWhere('denomination_id', $denom->id);

        $this->assertSame(0.00122, $row['final_buy'], '0.00127 + (-0.00005) ต้องได้ 0.00122 ไม่ใช่ 0.0012');
        $this->assertSame(0.00135, $row['final_sell'], '0.00132 + 0.00003 ต้องได้ 0.00135 ไม่ใช่ 0.0014');
    }

    public function test_distributed_counter_rate_keeps_six_decimals(): void
    {
        $denom = $this->seedVndRate();
        $service = app(SuperrichRateService::class);

        $batch = $service->createBatch($this->adminUser->id, [$this->counter->id]);
        $service->approveBatch($batch, $this->adminUser->id);
        $service->distributeBatch($batch->fresh(), $this->adminUser->id);

        $rate = CounterRate::where('counter_id', $this->counter->id)
            ->where('denomination_id', $denom->id)
            ->firstOrFail();

        // นี่คือค่าที่หน้าเคาน์เตอร์เอาไปคิดเงินจริง
        $this->assertEquals(0.00122, (float) $rate->rate_buy);
        $this->assertEquals(0.00135, (float) $rate->rate_sell);
    }

    public function test_float_artifacts_are_still_cleaned_up(): void
    {
        $this->seedVndRate();

        $batch = app(SuperrichRateService::class)
            ->createBatch($this->adminUser->id, [$this->counter->id]);

        $row = collect($batch->rates_data)->first();

        // 0.00127 + (-0.00005) ให้ 0.00122000000000000016 แบบดิบ ๆ
        // ยังต้องปัดอยู่ แค่ปัดที่ 6 ตำแหน่งแทน 4
        $this->assertSame(0.00122, $row['final_buy']);
        $this->assertStringNotContainsString('0000000', (string) $row['final_buy']);
    }

    public function test_ordinary_currencies_are_unaffected(): void
    {
        $denom = CurrencyDenomination::firstOrCreate(
            ['currency_code' => 'USD', 'denom_label' => '100-50'],
            ['display_name' => 'USD 100-50', 'seq' => 91, 'is_active' => true]
        );

        SuperrichRate::create([
            'currency_code' => 'USD', 'superrich_denom' => '100-50',
            'denomination_id' => $denom->id,
            'rate_buy' => 33.47, 'rate_sell' => 33.53, 'fetched_at' => now(),
        ]);
        SuperrichAdjustment::create([
            'denomination_id' => $denom->id,
            'adj_rate_buy' => -0.05, 'adj_rate_sell' => 0.03,
        ]);

        $batch = app(SuperrichRateService::class)
            ->createBatch($this->adminUser->id, [$this->counter->id]);

        $row = collect($batch->rates_data)->firstWhere('denomination_id', $denom->id);

        $this->assertSame(33.42, $row['final_buy']);
        $this->assertSame(33.56, $row['final_sell']);
    }
}
