<?php

namespace Tests\Feature\Rate;

use App\Models\CurrencyDenomination;
use App\Models\SuperrichAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * PHP พลิกไปใช้ scientific notation ตอนแปลง float เป็น string เมื่อค่าต่ำกว่า 1e-5
 *   (string) -0.0001  -> "-0.0001"
 *   (string) -0.00001 -> "-1.0E-5"
 *
 * ค่าปรับของสกุลที่ค่าต่ำมาก (IDR, VND) อยู่ใต้เส้นนั้นพอดี พนักงานจึงเห็น
 * "-1.0E-5" ในช่องกรอก ซึ่งอ่านไม่รู้เรื่องว่าคือ -0.00001
 */
class RateInputValueTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function idrDenomination(): CurrencyDenomination
    {
        return CurrencyDenomination::firstOrCreate(
            ['currency_code' => 'IDR', 'denom_label' => '100000-1000'],
            ['display_name' => 'IDR 100000-1000', 'seq' => 92, 'is_active' => true]
        );
    }

    public function test_tiny_adjustment_is_not_shown_in_scientific_notation(): void
    {
        $denom = $this->idrDenomination();

        SuperrichAdjustment::create([
            'denomination_id' => $denom->id,
            'adj_rate_buy' => -0.00001,
            'adj_rate_sell' => 0.00002,
        ]);

        $adjustments = Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Rate\SuperrichRateManager::class)
            ->get('adjustments');

        $this->assertSame('-0.00001', $adjustments[$denom->id]['adj_rate_buy']);
        $this->assertSame('0.00002', $adjustments[$denom->id]['adj_rate_sell']);
    }

    public function test_ordinary_adjustments_keep_their_plain_shape(): void
    {
        $denom = CurrencyDenomination::firstOrCreate(
            ['currency_code' => 'EUR', 'denom_label' => '50-5'],
            ['display_name' => 'EUR 50-5', 'seq' => 93, 'is_active' => true]
        );

        SuperrichAdjustment::create([
            'denomination_id' => $denom->id,
            'adj_rate_buy' => -0.05,
            'adj_rate_sell' => 0,
        ]);

        $adjustments = Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Rate\SuperrichRateManager::class)
            ->get('adjustments');

        // ห้ามเติมศูนย์ท้ายรก ๆ และห้ามเป็น "-0.050000"
        $this->assertSame('-0.05', $adjustments[$denom->id]['adj_rate_buy']);
        $this->assertSame('0', $adjustments[$denom->id]['adj_rate_sell']);
    }

    public function test_denomination_without_an_adjustment_defaults_to_zero(): void
    {
        $denom = CurrencyDenomination::firstOrCreate(
            ['currency_code' => 'GBP', 'denom_label' => '50'],
            ['display_name' => 'GBP 50', 'seq' => 94, 'is_active' => true]
        );

        $adjustments = Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Rate\SuperrichRateManager::class)
            ->get('adjustments');

        $this->assertSame('0', $adjustments[$denom->id]['adj_rate_buy']);
    }

    public function test_a_tiny_adjustment_survives_a_save_round_trip(): void
    {
        $denom = $this->idrDenomination();

        SuperrichAdjustment::create([
            'denomination_id' => $denom->id,
            'adj_rate_buy' => -0.00001,
            'adj_rate_sell' => 0,
        ]);

        // พนักงานเปิดหน้า แก้สกุลอื่น แล้วกดบันทึก — ค่าของ IDR ต้องไม่เพี้ยน
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Rate\SuperrichRateManager::class)
            ->call('saveAdjustments');

        $this->assertEquals(
            -0.00001,
            (float) SuperrichAdjustment::where('denomination_id', $denom->id)->value('adj_rate_buy')
        );
    }
}
