<?php

namespace Tests\Feature\Transaction;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Counter;
use App\Models\CounterRate;
use App\Models\CounterStock;
use App\Models\Customer;
use App\Models\WorkingDay;
use App\Services\CustomerDailyLimit;
use Database\Seeders\DailyLimitMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * วงเงินต่อคนต่อวันของธุรกรรมหน้าเคาน์เตอร์ — รับซื้อ/จ่ายขายแยกกัน เปิดปิดได้
 */
class CustomerDailyLimitTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Customer $customer;
    private CustomerDailyLimit $limit;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->limit = app(CustomerDailyLimit::class);

        $this->customer = Customer::create([
            'type' => 'individual', 'id_type' => 'passport', 'id_number' => 'AA111',
            'name_en' => 'John Smith', 'nationality' => 'USA',
        ]);

        CustomerDailyLimit::save(CustomerDailyLimit::BUYING, true, 800000);
        CustomerDailyLimit::save(CustomerDailyLimit::SELLING, true, 800000);
    }

    /** บิลที่บันทึกไปแล้ววันนี้ — $thb คือยอดบาท */
    private function bill(string $type, ?int $customerId, float $thb, array $master = [], ?Counter $counter = null): void
    {
        $counter ??= $this->counter;
        $id = DB::table('transactions_master')->insertGetId(array_merge([
            'trns_no' => 'T' . (++$this->seq),
            'trns_type' => $type,
            'counter_id' => $counter->id,
            'customer_id' => $customerId,
            'flag_cancel' => 'N',
            'trns_datetime' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $master));

        // BUYING: total = บาท / SELLING: amount = บาท
        DB::table('transactions_detail')->insert([
            'transaction_id' => $id,
            'currency_code' => 'USD',
            'unit_price' => 33,
            'amount' => $type === 'BUYING' ? $thb / 33 : $thb,
            'total' => $type === 'BUYING' ? $thb : $thb / 33,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_the_default_is_800000_and_on(): void
    {
        \App\Models\Setting::query()->delete();

        $this->assertSame(['enabled' => true, 'amount' => 800000.0], CustomerDailyLimit::config(CustomerDailyLimit::BUYING));
        $this->assertSame(['enabled' => true, 'amount' => 800000.0], CustomerDailyLimit::config(CustomerDailyLimit::SELLING));
    }

    public function test_within_the_limit_passes(): void
    {
        $this->bill('BUYING', $this->customer->id, 500000);

        $this->assertNull($this->limit->violation('BUYING', $this->customer->id, '', $this->counter->id, 300000));
    }

    public function test_going_over_the_limit_is_refused(): void
    {
        $this->bill('BUYING', $this->customer->id, 500000);

        $message = $this->limit->violation('BUYING', $this->customer->id, '', $this->counter->id, 300001);

        $this->assertStringContainsString('เกินวงเงินรับซื้อ', $message);
        $this->assertStringContainsString('500,000.00', $message);
        $this->assertStringContainsString('300,000.00', $message, 'บอกยอดที่ยังทำได้');
    }

    public function test_buying_and_selling_have_separate_limits(): void
    {
        CustomerDailyLimit::save(CustomerDailyLimit::SELLING, true, 100000);
        $this->bill('BUYING', $this->customer->id, 700000);

        $this->assertNull($this->limit->violation('SELLING', $this->customer->id, '', $this->counter->id, 100000));
        $this->assertNotNull($this->limit->violation('SELLING', $this->customer->id, '', $this->counter->id, 100001));
    }

    public function test_a_limit_that_is_off_never_refuses(): void
    {
        CustomerDailyLimit::save(CustomerDailyLimit::BUYING, false, 800000);

        $this->assertNull($this->limit->violation('BUYING', $this->customer->id, '', $this->counter->id, 5000000));
        $this->assertNull($this->limit->violation('BUYING', null, '', $this->counter->id, 1000), 'ปิดวงเงินแล้วไม่ต้องผูกลูกค้า');
    }

    public function test_a_bill_without_a_customer_is_refused_while_the_limit_is_on(): void
    {
        $this->assertStringContainsString('ต้องเลือกหรือสแกนข้อมูลลูกค้า', $this->limit->violation('BUYING', null, '', $this->counter->id, 1000));
    }

    public function test_the_same_passport_on_another_customer_row_counts_together(): void
    {
        // สแกนพาสปอร์ตเดิมแล้วได้แถวลูกค้าใหม่ — ต้องไม่ใช่ช่องหลบวงเงิน
        $duplicate = Customer::create(['type' => 'individual', 'id_type' => 'passport', 'id_number' => 'AA111', 'name_en' => 'J. Smith']);
        $this->bill('BUYING', $duplicate->id, 700000);

        $this->assertNotNull($this->limit->violation('BUYING', $this->customer->id, '', $this->counter->id, 200000));
        $this->assertNotNull($this->limit->violation('BUYING', null, 'AA111', $this->counter->id, 200000), 'ลูกค้าใหม่จากการสแกน ยังไม่มี customer_id');
    }

    public function test_cancelled_bills_yesterday_and_other_companies_do_not_count(): void
    {
        $company = Company::create(['company_code' => 'A', 'company_name' => 'A', 'is_active' => true]);
        $this->branch->update(['company_id' => $company->id]);
        $otherCompany = Company::create(['company_code' => 'B', 'company_name' => 'B', 'is_active' => true]);
        $otherBranch = Branch::create(['company_id' => $otherCompany->id, 'branch_code' => 'B1', 'branch_name' => 'B1', 'is_active' => true]);
        $otherCounter = Counter::create(['counter_code' => 'B1-C1', 'counter_name' => 'B1', 'branch_id' => $otherBranch->id, 'is_active' => true]);
        $sameCompanyBranch = Branch::create(['company_id' => $company->id, 'branch_code' => 'A2', 'branch_name' => 'A2', 'is_active' => true]);
        $sameCompanyCounter = Counter::create(['counter_code' => 'A2-C1', 'counter_name' => 'A2', 'branch_id' => $sameCompanyBranch->id, 'is_active' => true]);

        $this->bill('BUYING', $this->customer->id, 700000, ['flag_cancel' => 'Y']);
        $this->bill('BUYING', $this->customer->id, 700000, ['trns_datetime' => now()->subDay()]);
        $this->bill('BUYING', $this->customer->id, 700000, [], $otherCounter);
        $this->assertNull($this->limit->violation('BUYING', $this->customer->id, '', $this->counter->id, 800000));

        // สาขาอื่นของบริษัทเดียวกันนับรวม
        $this->bill('BUYING', $this->customer->id, 1, [], $sameCompanyCounter);
        $this->assertNotNull($this->limit->violation('BUYING', $this->customer->id, '', $this->counter->id, 800000));
    }

    public function test_the_buy_screen_refuses_to_save_over_the_limit(): void
    {
        CounterRate::create([
            'counter_id' => $this->counter->id, 'currency_code' => 'USD', 'denomination_id' => $this->denomination->id,
            'rate_date' => now()->toDateString(), 'rate_buy' => 33.00, 'rate_sell' => 38.00,
        ]);
        WorkingDay::insert([
            'counter_id' => $this->counter->id, 'work_date' => $this->workingDate(), 'opening_thb_cash' => 0,
            'status' => 'open', 'opened_by' => $this->adminUser->id, 'opened_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        CustomerDailyLimit::save(CustomerDailyLimit::BUYING, true, 3000);

        $component = Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('custName', 'Walk in')
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 100)          // 100 USD × 33 = 3,300 บาท
            ->call('addRow')
            ->call('saveTransaction');

        $this->assertStringContainsString('ต้องเลือกหรือสแกนข้อมูลลูกค้า', $component->get('limitError'));

        $component->set('customerId', $this->customer->id)->call('saveTransaction');

        $this->assertStringContainsString('เกินวงเงินรับซื้อ', $component->get('limitError'));
        $component->assertSee('เกินวงเงินรับซื้อ');
        $this->assertDatabaseCount('transactions_master', 0);
    }

    public function test_admin_can_change_the_limits(): void
    {
        $this->actingAsAdmin()->get(route('admin.daily-limit.edit'))->assertOk()->assertSee('800000');

        $this->actingAsAdmin()->put(route('admin.daily-limit.update'), [
            'buy_enabled' => '1', 'buy_amount' => '500000',
            'sell_enabled' => '0', 'sell_amount' => '900000',
        ])->assertRedirect(route('admin.daily-limit.edit'));

        $this->assertSame(['enabled' => true, 'amount' => 500000.0], CustomerDailyLimit::config(CustomerDailyLimit::BUYING));
        $this->assertSame(['enabled' => false, 'amount' => 900000.0], CustomerDailyLimit::config(CustomerDailyLimit::SELLING));
    }

    public function test_staff_cannot_open_the_setting(): void
    {
        $this->actingAsStaff()->get(route('admin.daily-limit.edit'))->assertForbidden();
    }

    public function test_the_menu_sits_under_settings(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(DailyLimitMenuSeeder::class);

        $menu = \App\Models\Menu::where('key', 'admin.daily-limit')->firstOrFail();
        $this->assertSame('settings', \App\Models\Menu::find($menu->parent_id)?->key);
        $this->assertContains('admin', $menu->roles()->pluck('name')->all());
        $this->assertNotContains('staff', $menu->roles()->pluck('name')->all());
        $this->assertNotContains('branch_manager', $menu->roles()->pluck('name')->all(), 'เข้าหน้าไม่ได้ ห้ามเห็นเมนู');
    }
}
