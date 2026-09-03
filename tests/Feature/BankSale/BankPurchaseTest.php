<?php

namespace Tests\Feature\BankSale;

use App\Livewire\Rate\BankSaleManager;
use App\Models\BankSale;
use App\Models\CounterRate;
use App\Models\CounterStock;
use App\Models\Customer;
use App\Models\CurrencyDenomination;
use App\Models\JournalLine;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\BankSaleService;
use Database\Seeders\AccountSeeder;
use Database\Seeders\TransactionAccountMappingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class BankPurchaseTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private User $trader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        // GL mappings resolve accounts by code — without these the journal is
        // silently skipped and every journal assertion would pass vacuously.
        $this->seed(AccountSeeder::class);
        $this->seed(TransactionAccountMappingSeeder::class);

        $this->trader = User::create([
            'name' => 'Trader',
            'email' => 'trader@test.local',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'trader')->value('id'),
            'branch_id' => $this->branch->id,
            'managed_branch_ids' => [$this->branch->id],
            'is_active' => true,
        ]);
    }

    private function service(): BankSaleService
    {
        return app(BankSaleService::class);
    }

    private function purchaseData(array $overrides = []): array
    {
        return array_merge([
            'direction' => BankSale::DIRECTION_BUY,
            'bank_name' => 'กสิกรไทย',
            'destination_counter_id' => $this->counter->id,
            'settlement_method' => 'bank_transfer',
        ], $overrides);
    }

    /** แถวธนบัตรหนึ่งแถว — ธนาคารส่งมาครั้งเดียวได้หลายแถว */
    private function item(float $amount = 1000, float $rate = 32, ?int $denominationId = null): array
    {
        return [
            'currency_code' => 'USD',
            'denomination_id' => $denominationId ?? $this->denomination->id,
            'amount' => $amount,
            'bank_rate' => $rate,
        ];
    }

    public function test_completing_a_purchase_adds_stock_and_reweights_avg_cost(): void
    {
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 1000,
            'hold_amount' => 0,
            'avg_cost' => 30,
            'total_cost_value' => 30000,
        ]);

        $purchase = $this->service()->create(
            $this->purchaseData(),
            [$this->item(1000, 32)],
            $this->trader->id
        );

        $this->assertSame(BankSale::STATUS_ORDERED, $purchase->status);
        $this->assertSame('buy', $purchase->direction);
        $this->assertStringStartsWith('BP', $purchase->sale_no);

        // Reserving is a sell-side concept — a purchase must not touch hold_amount
        $stock = CounterStock::where('counter_id', $this->counter->id)->first();
        $this->assertEquals(0, $stock->hold_amount);
        $this->assertEquals(1000, $stock->quantity, 'stock arrives on complete, not on create');

        $this->service()->complete($purchase->fresh(), $this->trader->id);

        $stock->refresh();
        $this->assertEquals(2000, $stock->quantity);
        // (1000*30 + 1000*32) / 2000 = 31
        $this->assertEquals(31, round((float) $stock->avg_cost, 6));
        $this->assertEquals(62000, round((float) $stock->total_cost_value, 2));
        $this->assertEquals(0, $stock->hold_amount);

        $movement = StockMovement::where('reference_type', 'bank_purchase')->first();
        $this->assertNotNull($movement);
        $this->assertSame('buy', $movement->movement_type);
        $this->assertEquals(1000, $movement->amount);

        $purchase->refresh();
        $this->assertSame(BankSale::STATUS_COMPLETED, $purchase->status);
        $this->assertNotNull($purchase->journal_entry_id, 'GL journal must be created');

        $lines = JournalLine::where('journal_entry_id', $purchase->journal_entry_id)->get();
        $this->assertEquals($lines->sum('debit'), $lines->sum('credit'), 'journal must balance');
        $this->assertEquals(32000, $lines->sum('debit'));
        $this->assertEquals(0, $purchase->profit_loss, 'a purchase has no P&L — it is cost');
    }

    public function test_receiving_into_a_counter_with_no_stock_row_creates_one_at_the_bank_rate(): void
    {
        $this->assertEquals(0, CounterStock::count());

        $purchase = $this->service()->create(
            $this->purchaseData(['destination_counter_id' => $this->counter2->id]),
            [$this->item(500, 35)],
            $this->trader->id
        );
        $this->service()->complete($purchase->fresh(), $this->trader->id);

        $stock = CounterStock::where('counter_id', $this->counter2->id)->first();
        $this->assertNotNull($stock);
        $this->assertEquals(500, $stock->quantity);
        $this->assertEquals(35, round((float) $stock->avg_cost, 6));
    }

    public function test_cancelling_a_purchase_never_drives_hold_amount_negative(): void
    {
        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 100,
            'hold_amount' => 0,
            'avg_cost' => 30,
            'total_cost_value' => 3000,
        ]);

        $purchase = $this->service()->create(
            $this->purchaseData(),
            [$this->item(1000, 32)],
            $this->trader->id
        );

        $this->service()->cancel($purchase->fresh(), $this->trader->id);

        $stock = CounterStock::where('counter_id', $this->counter->id)->first();
        $this->assertEquals(0, $stock->hold_amount, 'purchase never held stock, so nothing to release');
        $this->assertEquals(100, $stock->quantity);
        $this->assertSame(BankSale::STATUS_CANCELLED, $purchase->fresh()->status);
    }

    public function test_a_purchase_has_no_transit_or_delivered_step(): void
    {
        $purchase = $this->service()->create(
            $this->purchaseData(),
            [$this->item(1000, 32)],
            $this->trader->id
        );

        $this->expectException(\RuntimeException::class);
        $this->service()->markInTransit($purchase->fresh());
    }

    public function test_completing_is_blocked_while_the_settlement_method_is_pending(): void
    {
        $purchase = $this->service()->create(
            $this->purchaseData(['settlement_method' => BankSale::SETTLEMENT_PENDING]),
            [$this->item(1000, 32)],
            $this->trader->id
        );

        try {
            $this->service()->complete($purchase->fresh(), $this->trader->id);
            $this->fail('completing with settlement_method=pending must throw');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('ระบุภายหลัง', $e->getMessage());
        }

        $purchase->refresh();
        $this->assertSame(BankSale::STATUS_ORDERED, $purchase->status);
        $this->assertNull($purchase->journal_entry_id);
        $this->assertEquals(0, CounterStock::count(), 'no stock may move on a blocked completion');
    }

    public function test_each_screen_only_sees_its_own_direction(): void
    {
        $purchase = $this->service()->create(
            $this->purchaseData(),
            [$this->item(1000, 32)],
            $this->trader->id
        );

        Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'sell'])
            ->assertDontSee($purchase->sale_no);

        Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->assertSee($purchase->sale_no);

        // A purchase id sent to the sell screen must not find a record at all
        Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'sell'])
            ->call('completeSale', $purchase->id);

        $this->assertSame(BankSale::STATUS_ORDERED, $purchase->fresh()->status);
    }

    public function test_the_settlement_method_can_be_filled_in_after_creation(): void
    {
        $purchase = $this->service()->create(
            $this->purchaseData(['settlement_method' => BankSale::SETTLEMENT_PENDING]),
            [$this->item(1000, 32)],
            $this->trader->id
        );

        Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->call('updateSettlementMethod', $purchase->id, 'cash');

        $this->assertSame('cash', $purchase->fresh()->settlement_method);

        $this->service()->complete($purchase->fresh(), $this->trader->id);
        $this->assertSame(BankSale::STATUS_COMPLETED, $purchase->fresh()->status);
    }

    public function test_stock_info_averages_rates_and_sums_stock_across_managed_branches(): void
    {
        // Two counters, different rates and different stock levels
        CounterRate::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'rate_buy' => 32.00,
            'rate_sell' => 34.00,
        ]);
        CounterRate::create([
            'counter_id' => $this->counter2->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'rate_buy' => 33.00,
            'rate_sell' => 35.00,
        ]);

        CounterStock::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 1000, 'hold_amount' => 200, 'avg_cost' => 30, 'total_cost_value' => 30000,
        ]);
        CounterStock::create([
            'counter_id' => $this->counter2->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'quantity' => 500, 'hold_amount' => 0, 'avg_cost' => 31, 'total_cost_value' => 15500,
        ]);

        Cache::flush();

        $info = Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->instance()
            ->stockInfo;

        $this->assertCount(1, $info);
        $this->assertSame('USD', $info[0]['currency_code']);
        $this->assertEquals(32.5, round($info[0]['buy_rate'], 4), 'rates are averaged across branches');
        $this->assertEquals(34.5, round($info[0]['sell_rate'], 4));
        // (1000-200) + (500-0) = 1300
        $this->assertEquals(1300, round($info[0]['available'], 2), 'available stock is summed across branches');
    }

    public function test_a_purchase_routes_to_the_central_counter_without_choosing_a_destination(): void
    {
        $component = Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->set('showForm', true)
            ->set('custName', 'กสิกรไทย')
            ->set('rowDenominationId', (string) $this->denomination->id)
            ->set('rowAmount', '1000')
            ->set('rowRate', '32')
            ->call('addRow')
            ->call('save');

        $component->assertHasNoErrors();

        $purchase = BankSale::direction('buy')->firstOrFail();

        // กองกลาง = เคาน์เตอร์แรกของสาขาที่ trader สังกัด
        $this->assertSame($this->counter->id, $purchase->destination_counter_id);
        $this->assertCount(1, $purchase->items);
        $this->assertEquals(1000, $purchase->items->first()->amount);
        $this->assertEquals(32000, $purchase->total_thb);
    }

    public function test_one_purchase_can_hold_several_denominations_at_different_rates(): void
    {
        $usd20 = CurrencyDenomination::create([
            'currency_code' => 'USD',
            'denom_label' => '20-10',
            'display_name' => 'USD 20-10',
            'seq' => 2,
        ]);

        $purchase = $this->service()->create(
            $this->purchaseData(),
            [
                $this->item(1000, 32),
                $this->item(500, 31, $usd20->id),
            ],
            $this->trader->id
        );

        $this->assertCount(2, $purchase->items);
        // 1000×32 + 500×31 = 47,500
        $this->assertEquals(47500, $purchase->total_thb);
        $this->assertEquals(47500, $purchase->total_cost);
        $this->assertEquals(0, $purchase->profit_loss);

        $this->service()->complete($purchase->fresh(), $this->trader->id);

        // avg cost ต้องคิดแยกต่อ denom ไม่ใช่เฉลี่ยรวมทั้งใบ
        $stock100 = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $this->denomination->id)->firstOrFail();
        $stock20 = CounterStock::where('counter_id', $this->counter->id)
            ->where('denomination_id', $usd20->id)->firstOrFail();

        $this->assertEquals(1000, $stock100->quantity);
        $this->assertEquals(32, round((float) $stock100->avg_cost, 6));
        $this->assertEquals(500, $stock20->quantity);
        $this->assertEquals(31, round((float) $stock20->avg_cost, 6));

        $this->assertEquals(2, StockMovement::where('reference_type', 'bank_purchase')->count());

        $purchase->refresh();
        $lines = JournalLine::where('journal_entry_id', $purchase->journal_entry_id)->get();
        $this->assertEquals($lines->sum('debit'), $lines->sum('credit'), 'journal must balance');
        $this->assertEquals(47500, $lines->sum('debit'), 'GL ต้องเป็นยอดรวมทั้งใบ');
    }

    public function test_the_same_denomination_added_twice_merges_into_one_row(): void
    {
        $component = Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->set('showForm', true)
            ->set('custName', 'กสิกรไทย')
            ->set('rowDenominationId', (string) $this->denomination->id)
            ->set('rowAmount', '1000')
            ->set('rowRate', '32')
            ->call('addRow')
            ->set('rowDenominationId', (string) $this->denomination->id)
            ->set('rowAmount', '500')
            ->set('rowRate', '32')
            ->call('addRow');

        $rows = $component->get('rows');
        $this->assertCount(1, $rows, 'ธนบัตรเดิมเรทเดิมต้องรวมเข้าแถวเดียว');
        $this->assertEquals(1500, $rows[0]['amount']);
        $this->assertEquals(48000, $rows[0]['total_thb']);
    }

    public function test_saving_a_purchase_with_no_rows_shows_an_error_instead_of_saving(): void
    {
        $component = Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->set('showForm', true)
            ->set('custName', 'กสิกรไทย')
            ->call('save');

        $component->assertHasErrors('rows');
        $component->assertSee('กรุณาเพิ่มรายการธนบัตรอย่างน้อย 1 แถว');
        $this->assertEquals(0, BankSale::count());
    }

    public function test_the_counterparty_field_autocompletes_from_the_customer_registry(): void
    {
        Customer::create([
            'id_type' => 'passport',
            'id_number' => 'AA1234567',
            'name_en' => 'JOHN SMITH',
            'first_name' => 'JOHN',
            'last_name' => 'SMITH',
            'nationality' => 'GBR',
            'passport_expiry' => '2030-01-31',
            'kyc_status' => 'approved',
        ]);

        $component = Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->set('showForm', true)
            ->call('searchCustomers', 'JOHN');

        $suggestions = $component->get('customerSuggestions');
        $this->assertCount(1, $suggestions);
        $this->assertSame('AA1234567', $suggestions[0]['id_number']);

        $component->call('selectCustomer', $suggestions[0]['id']);

        $this->assertSame('JOHN SMITH', $component->get('custName'));
        $this->assertSame('AA1234567', $component->get('custPassportNo'));
        $this->assertSame('GBR', $component->get('custNationality'));
        $this->assertSame('2030-01-31', $component->get('custExpiry'));
    }

    public function test_a_purchase_records_the_counterparty_and_registers_a_new_customer(): void
    {
        $this->assertEquals(0, Customer::count());

        Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->set('showForm', true)
            ->set('custName', 'JANE DOE')
            ->set('custPassportNo', 'BB7654321')
            ->set('custNationality', 'USA')
            ->set('custExpiry', '2031-06-30')
            ->set('rowDenominationId', (string) $this->denomination->id)
            ->set('rowAmount', '1000')
            ->set('rowRate', '32')
            ->call('addRow')
            ->call('save')
            ->assertHasNoErrors();

        $purchase = BankSale::direction('buy')->firstOrFail();
        $this->assertSame('JANE DOE', $purchase->bank_name);
        $this->assertSame('BB7654321', $purchase->customer_passport_no);
        $this->assertSame('USA', $purchase->customer_nationality);

        // กรอกพาสปอร์ตแล้วต้องเข้าทะเบียนลูกค้า เพื่อให้ครั้งหน้า autocomplete เจอ
        $customer = Customer::where('id_number', 'BB7654321')->first();
        $this->assertNotNull($customer);
        $this->assertSame($customer->id, $purchase->customer_id);
        $this->assertSame('JANE DOE', $customer->name_en);
    }

    public function test_a_purchase_without_a_passport_still_saves_the_counterparty_name(): void
    {
        Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->set('showForm', true)
            ->set('custName', 'กสิกรไทย')
            ->set('rowDenominationId', (string) $this->denomination->id)
            ->set('rowAmount', '1000')
            ->set('rowRate', '32')
            ->call('addRow')
            ->call('save')
            ->assertHasNoErrors();

        $purchase = BankSale::direction('buy')->firstOrFail();
        $this->assertSame('กสิกรไทย', $purchase->bank_name);
        $this->assertNull($purchase->customer_id);
        $this->assertEquals(0, Customer::count(), 'ไม่มีพาสปอร์ตก็ไม่ต้องสร้างลูกค้าเปล่าๆ');
    }

    public function test_saving_a_purchase_without_a_counterparty_name_shows_an_error(): void
    {
        $component = Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->set('showForm', true)
            ->set('rowDenominationId', (string) $this->denomination->id)
            ->set('rowAmount', '1000')
            ->set('rowRate', '32')
            ->call('addRow')
            ->call('save');

        $component->assertHasErrors('custName');
        $this->assertEquals(0, BankSale::count());
    }

    public function test_selecting_a_denomination_prefills_the_average_buy_rate(): void
    {
        CounterRate::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'rate_buy' => 32.00,
            'rate_sell' => 34.00,
        ]);
        CounterRate::create([
            'counter_id' => $this->counter2->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'rate_buy' => 33.00,
            'rate_sell' => 35.00,
        ]);

        Cache::flush();

        $component = Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->set('showForm', true)
            ->set('rowDenominationId', (string) $this->denomination->id);

        $this->assertEquals(32.5, (float) $component->get('rowRate'), 'เรทตั้งต้น = ค่าเฉลี่ยทุกสาขาที่ดูแล');
    }

    public function test_stock_info_is_empty_when_the_trader_manages_no_branches(): void
    {
        $this->trader->update(['managed_branch_ids' => []]);
        Cache::flush();

        $info = Livewire::actingAs($this->trader->fresh())
            ->test(BankSaleManager::class, ['direction' => 'buy'])
            ->instance()
            ->stockInfo;

        $this->assertSame([], $info);
    }

    /**
     * เคยเป็นบั๊ก: กด "สร้างรายการ" แล้วเงียบสนิท เพราะ error ของ sourceAmounts
     * ถูกวางไว้ในบล็อกตารางแหล่งสต็อก ซึ่งไม่ถูก render ตอนไม่มีสต็อก
     */
    public function test_selling_with_no_stock_shows_a_visible_error_not_silence(): void
    {
        $this->assertEquals(0, CounterStock::count(), 'ตั้งใจให้ไม่มีสต็อกเลย');

        $component = Livewire::actingAs($this->trader)
            ->test(BankSaleManager::class, ['direction' => 'sell'])
            ->set('showForm', true)
            ->set('bankName', 'ไทยพาณิชย์')
            ->set('currencyCode', 'USD')
            ->set('denominationId', (string) $this->denomination->id)
            ->set('totalAmount', '8000')
            ->set('bankRate', '32.65')
            ->call('save');

        $component->assertHasErrors('sourceAmounts');
        // ข้อความต้องโผล่บนหน้าจอจริง ไม่ใช่แค่มีอยู่ใน error bag
        $component->assertSee('กรุณาระบุจำนวนจากเคาน์เตอร์อย่างน้อย 1 แห่ง');
        $component->assertSee('ไม่มีสต็อกให้ขาย');

        $this->assertEquals(0, BankSale::count(), 'ต้องไม่สร้างรายการ');
    }

    public function test_bank_purchases_page_is_trader_only(): void
    {
        $this->actingAs($this->trader)->get('/admin/bank-purchases')->assertStatus(200);
        $this->actingAsAdmin()->get('/admin/bank-purchases')->assertStatus(403);
        $this->actingAsStaff()->get('/admin/bank-purchases')->assertStatus(403);
    }
}
