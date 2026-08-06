<?php

namespace Tests\Feature\Transaction;

use App\Models\Customer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ยืนยันว่า autocomplete ชื่อลูกค้าบนหน้าซื้อทำงานจริง
 *
 * ที่หน้างานแล้วไม่มี dropdown ขึ้นเพราะตาราง customers ยังว่าง ไม่ใช่เพราะโค้ดพัง
 */
class CustomerAutocompleteTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function buyForm()
    {
        return Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form');
    }

    public function test_no_suggestions_when_the_customers_table_is_empty(): void
    {
        $this->assertEquals(0, Customer::count());

        $this->buyForm()
            ->call('searchCustomers', 'abc')
            ->assertSet('showSuggestions', false)
            ->assertSet('customerSuggestions', []);
    }

    public function test_searching_by_name_returns_the_customer(): void
    {
        Customer::create([
            'name_en' => 'ABC Traveller',
            'id_number' => 'AA1234567',
            'nationality' => 'USA',
        ]);

        $component = $this->buyForm()->call('searchCustomers', 'abc');

        $component->assertSet('showSuggestions', true);
        $suggestions = $component->get('customerSuggestions');
        $this->assertCount(1, $suggestions);
        $this->assertSame('ABC Traveller', $suggestions[0]['name']);
    }

    public function test_searching_by_passport_number_returns_the_customer(): void
    {
        Customer::create([
            'name_en' => 'ABC Traveller',
            'id_number' => 'AA1234567',
            'nationality' => 'USA',
        ]);

        $component = $this->buyForm()->call('searchCustomers', 'AA123');

        $component->assertSet('showSuggestions', true);
        $this->assertSame('AA1234567', $component->get('customerSuggestions')[0]['id_number']);
    }

    public function test_a_single_character_is_too_short_to_search(): void
    {
        Customer::create(['name_en' => 'ABC Traveller', 'id_number' => 'AA1234567']);

        $this->buyForm()
            ->call('searchCustomers', 'a')
            ->assertSet('showSuggestions', false);
    }
}
