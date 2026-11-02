<?php

namespace Tests\Feature\Transaction;

use App\Models\CounterRate;
use App\Models\CounterStock;
use App\Models\Customer;
use App\Models\TransactionMaster;
use App\Services\CustomerDailyLimit;
use App\Support\TypedDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ช่องวันหมดอายุพิมพ์อิสระ — พิมพ์แบบ วัน/เดือน/ปี แล้วบิลต้องไม่พัง (เคยได้ 500 บน prod)
 */
class TypedCustomerDateTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        CustomerDailyLimit::save(CustomerDailyLimit::BUYING, false, 800000);
        CustomerDailyLimit::save(CustomerDailyLimit::SELLING, false, 800000);

        CounterRate::create([
            'counter_id' => $this->counter->id,
            'currency_code' => 'USD',
            'denomination_id' => $this->denomination->id,
            'rate_date' => now()->toDateString(),
            'rate_buy' => 33.00,
            'rate_sell' => 38.00,
        ]);
    }

    public function test_typed_dates_are_read_the_thai_way(): void
    {
        $this->assertSame('2070-06-25', TypedDate::toIso('25/06/2070'));
        $this->assertSame('2027-06-25', TypedDate::toIso('25/06/2570'), 'ปี พ.ศ.');
        $this->assertSame('2030-01-05', TypedDate::toIso('5.1.2030'));
        $this->assertSame('2030-01-05', TypedDate::toIso('2030-01-05'));
        $this->assertNull(TypedDate::toIso('31/02/2030'));
        $this->assertNull(TypedDate::toIso('ตลอดชีพ'));
        $this->assertNull(TypedDate::toIso(''));
    }

    public function test_a_buy_with_a_day_first_expiry_saves(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('custName', 'JOHN DOE')
            ->set('ocrIdType', 'passport')
            ->set('ocrPassportNo', 'X1234567')
            ->set('ocrExpiry', '25/06/2070')
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 100)
            ->call('addRow')
            ->call('saveTransaction')
            ->assertHasNoErrors();

        $this->assertSame(1, TransactionMaster::where('trns_type', 'BUYING')->count());
        $this->assertSame('2070-06-25', Customer::where('id_number', 'X1234567')->sole()->passport_expiry->format('Y-m-d'));
    }

    public function test_a_buy_with_placeholder_dates_saves(): void
    {
        // ค่าที่เจอจริงบน prod: ขีดแทน "ไม่มี" และศูนย์ทั้งช่อง
        Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('custName', 'สมชาย ใจดี')
            ->set('ocrIdType', 'national_id')
            ->set('ocrPassportNo', '1100000000001')
            ->set('ocrDob', '-')
            ->set('ocrExpiry', '00-000-0000')
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 100)
            ->call('addRow')
            ->call('saveTransaction')
            ->assertHasNoErrors();

        $customer = Customer::where('id_number', '1100000000001')->sole();
        $this->assertNull($customer->date_of_birth);
        $this->assertNull($customer->passport_expiry);
    }

    public function test_a_sell_with_an_unreadable_expiry_still_saves(): void
    {
        CounterStock::create([
            'counter_id' => $this->counter->id, 'currency_code' => 'USD', 'denomination_id' => $this->denomination->id,
            'quantity' => 1000, 'hold_amount' => 0, 'avg_cost' => 33, 'total_cost_value' => 33000,
        ]);

        Livewire::actingAs($this->staffUser)
            ->test('transaction.sell-form')
            ->set('counterId', (string) $this->counter->id)
            ->set('custName', 'JOHN DOE')
            ->set('ocrIdType', 'passport')
            ->set('ocrPassportNo', 'X7654321')
            ->set('ocrExpiry', 'ตลอดชีพ')
            ->set('selectedCurrency', (string) $this->denomination->id)
            ->set('addAmount', 3800)
            ->call('addRow')
            ->call('saveTransaction')
            ->assertHasNoErrors();

        $this->assertSame(1, TransactionMaster::where('trns_type', 'SELLING')->count());
        $this->assertNull(Customer::where('id_number', 'X7654321')->sole()->passport_expiry);
    }
}
