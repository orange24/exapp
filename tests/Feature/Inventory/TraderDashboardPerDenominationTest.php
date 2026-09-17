<?php

namespace Tests\Feature\Inventory;

use App\Models\CounterStock;
use App\Models\CurrencyDenomination;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * Trader dashboard ต้องแยกแถวตาม denomination ไม่ใช่รวมต่อสกุลเงิน
 *
 * แต่ละ denomination ซื้อขายกันคนละเรท การเฉลี่ยรวมทั้งสกุลให้ตัวเลขที่ไม่ตรงกับ
 * ช่วงไหนเลย และ trader ใช้ตัดสินใจตั้งเรทไม่ได้
 */
class TraderDashboardPerDenominationTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private User $trader;
    private CurrencyDenomination $small;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        // USD ช่วงย่อยอีกช่วง ต้นทุนต่างจาก 100-50 ชัดเจน
        $this->small = CurrencyDenomination::create([
            'currency_code' => 'USD',
            'denom_label' => '2-1',
            'display_name' => 'USD 2-1',
            'seq' => 2,
        ]);

        $this->trader = User::create([
            'name' => 'Trader',
            'email' => 'trader@test.local',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'trader')->value('id'),
            'branch_id' => $this->branch->id,
            'managed_branch_ids' => [$this->branch->id],
            'is_active' => true,
        ]);

        // USD 100-50 : 1,000 @ 33.30
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 1000,
            'hold_amount' => 0,
            'avg_cost' => 33.30,
            'total_cost_value' => 33300,
        ]);

        // USD 2-1 : 100 @ 31.00
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->small->id,
            'quantity' => 100,
            'hold_amount' => 0,
            'avg_cost' => 31.00,
            'total_cost_value' => 3100,
        ]);
    }

    private function rows(): array
    {
        return Livewire::actingAs($this->trader)
            ->test(\App\Livewire\Trader\InventoryDashboard::class)
            ->get('combinedData');
    }

    public function test_each_denomination_gets_its_own_row_with_its_own_cost(): void
    {
        $rows = collect($this->rows())->keyBy('denom_label');

        $this->assertCount(2, $rows, 'USD ต้องแยกเป็น 2 แถว ไม่ใช่รวมเป็นแถวเดียว');

        $this->assertEquals(33.30, round($rows['100-50']['avg_cost'], 4));
        $this->assertEquals(1000.0, round($rows['100-50']['balance'], 2));

        $this->assertEquals(31.00, round($rows['2-1']['avg_cost'], 4));
        $this->assertEquals(100.0, round($rows['2-1']['balance'], 2));
    }

    /**
     * ยอดเดิมจะเฉลี่ยรวมเป็น (33,300 + 3,100) / 1,100 = 33.0909 ซึ่งไม่ตรงกับ
     * ต้นทุนของช่วงไหนเลย — ต้องไม่มีแถวไหนแสดงค่านั้น
     */
    public function test_no_row_shows_the_old_blended_average(): void
    {
        foreach ($this->rows() as $row) {
            $this->assertNotEquals(33.0909, round($row['avg_cost'], 4));
        }
    }

    public function test_cost_is_weighted_by_quantity_across_counters(): void
    {
        // เคาน์เตอร์ที่สองถือ USD 100-50 อีก 3,000 ที่ต้นทุนถูกกว่า
        CounterStock::create([
            'counter_id' => $this->counter2->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 3000,
            'hold_amount' => 0,
            'avg_cost' => 33.10,
            'total_cost_value' => 99300,
        ]);

        $row = collect($this->rows())->firstWhere('denom_label', '100-50');

        // (33,300 + 99,300) / 4,000 = 33.15 — ถ่วงน้ำหนักด้วยจำนวน
        // ไม่ใช่ (33.30 + 33.10) / 2 = 33.20
        $this->assertEquals(33.15, round($row['avg_cost'], 4));
        $this->assertEquals(4000.0, round($row['balance'], 2));
        $this->assertEquals(132600.0, round($row['thb_value'], 2));
    }

    public function test_thb_cash_rows_never_appear_as_inventory(): void
    {
        // เงินบาทอยู่ใน counter_stock ด้วย (denomination_id = null) แต่ไม่ใช่สินค้า
        // คงคลังที่ trader ต้องบริหารเรท
        app(\App\Services\ThbCashService::class)
            ->topUp($this->counter->id, 500000, $this->adminUser->id);

        $codes = collect($this->rows())->pluck('currency_code')->all();

        $this->assertNotContains('THB', $codes);
        $this->assertCount(2, $codes);
    }

    public function test_empty_denominations_are_hidden(): void
    {
        CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->small->id)
            ->update(['quantity' => 0, 'total_cost_value' => 0]);

        $labels = collect($this->rows())->pluck('denom_label')->all();

        $this->assertSame(['100-50'], $labels);
    }

    public function test_rows_are_ordered_by_currency_then_denomination(): void
    {
        StockMovement::query()->delete();

        $labels = collect($this->rows())->pluck('denom_label')->all();

        // denomination seq: 100-50 = 1, 2-1 = 2
        $this->assertSame(['100-50', '2-1'], $labels);
    }
}
