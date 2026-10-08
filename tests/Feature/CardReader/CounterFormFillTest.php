<?php

namespace Tests\Feature\CardReader;

use App\Models\CardReaderDevice;
use App\Models\CardReaderRead;
use App\Services\CardReader\CounterInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class CounterFormFillTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        // ฟอร์มรู้ว่าตัวเองอยู่เคาน์เตอร์ไหนจาก session — ไม่มีค่านี้ pollCardReader
        // จะออกตั้งแต่บรรทัดแรกแล้วเทสจะเขียวทั้งที่ไม่ได้ทดสอบอะไรเลย
        session(['working_counter_id' => $this->counter->id]);

        // ฟอร์มซื้อ/ขายเด้งผู้ใช้ออกถ้าวันทำการยังไม่เปิด พอ redirect กลางคัน
        // Livewire จะได้ response ที่ไม่ใช่ snapshot แล้วเทสล้มด้วย error
        // ที่ไม่เกี่ยวกับสิ่งที่กำลังทดสอบเลย
        \App\Models\WorkingDay::insert([
            'counter_id' => $this->counter->id,
            'work_date' => now()->format('Y-m-d'),
            'opening_thb_cash' => 0,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function device(array $attributes = []): CardReaderDevice
    {
        return CardReaderDevice::create(array_merge([
            'name' => 'สีลม เคาน์เตอร์ 1',
            'counter_id' => $this->counter->id,
            'token_hash' => CardReaderDevice::hashToken('tok_' . uniqid()),
            'last_seen_at' => now(),
            'last_status' => CardReaderDevice::STATUS_READY,
        ], $attributes));
    }

    private function insertCard(array $overrides = []): CardReaderRead
    {
        $device = CardReaderDevice::first() ?? $this->device();

        return CardReaderRead::create([
            'counter_id' => $this->counter->id,
            'device_id' => $device->id,
            'payload' => array_merge([
                'citizen_id' => '5960500028101',
                'name_th' => 'นายสมชาย ใจดี',
                'first_name_en' => 'SOMCHAI',
                'last_name_en' => 'JAIDEE',
                'date_of_birth' => '1981-12-18',
            ], $overrides),
            'read_at' => now(),
            'expires_at' => now()->addSeconds(CardReaderRead::LIFETIME_SECONDS),
        ]);
    }

    /** @return array<string, string> */
    private function cardPayload(array $overrides = []): array
    {
        return array_merge([
            'citizen_id' => '5960500028101',
            'name_th' => 'นายสมชาย ใจดี',
            'first_name_en' => 'SOMCHAI',
            'last_name_en' => 'JAIDEE',
            'date_of_birth' => '1981-12-18',
        ], $overrides);
    }

    public function test_inserting_a_card_fills_the_buy_form(): void
    {
        $this->device();
        $this->insertCard();

        Livewire::actingAs($this->staffUser)
            ->test('card-reader.inbox')
            ->call('poll')
            ->assertDispatched('card-read');

        Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->dispatch('card-read', card: $this->cardPayload())
            ->assertSet('ocrPassportNo', '5960500028101')
            ->assertSet('ocrIdType', 'national_id')
            ->assertSet('ocrDob', '1981-12-18')
            ->assertSet('ocrNationality', 'THA')
            ->assertSet('custName', 'นายสมชาย ใจดี');
    }

    public function test_inserting_a_card_fills_the_sell_form(): void
    {
        $this->device();
        $this->insertCard();

        Livewire::actingAs($this->staffUser)
            ->test('transaction.sell-form')
            ->dispatch('card-read', card: $this->cardPayload())
            ->assertSet('ocrPassportNo', '5960500028101')
            ->assertSet('ocrIdType', 'national_id');
    }

    public function test_a_card_is_handed_to_one_page_only(): void
    {
        $this->device();
        $this->insertCard();

        $inbox = app(CounterInbox::class);

        $this->assertNotNull($inbox->consume($this->counter->id));

        // หน้าต่างบานที่สองที่เปิดเคาน์เตอร์เดียวกันต้องไม่ได้ใบเดิมไปซ้ำ
        $this->assertNull($inbox->consume($this->counter->id));
    }

    public function test_an_expired_card_is_never_handed_over(): void
    {
        $this->device();
        $read = $this->insertCard();
        $read->update(['expires_at' => now()->subSecond()]);

        // เสียบบัตรทิ้งไว้แล้วเดินออกไป ข้อมูลต้องหายไปเองโดยไม่มีใครได้ใช้
        $this->assertNull(app(CounterInbox::class)->consume($this->counter->id));
    }

    public function test_a_card_read_at_another_counter_does_not_leak_across(): void
    {
        $other = \App\Models\Counter::create([
            'branch_id' => $this->branch->id,
            'counter_name' => 'เคาน์เตอร์ 2',
            'counter_code' => 'TEST-C2',
            'is_active' => true,
        ]);

        $device = $this->device(['counter_id' => $other->id]);

        CardReaderRead::create([
            'counter_id' => $other->id,
            'device_id' => $device->id,
            'payload' => ['citizen_id' => '1111111111119'],
            'read_at' => now(),
            'expires_at' => now()->addMinute(),
        ]);

        $this->assertNull(app(CounterInbox::class)->consume($this->counter->id));
        $this->assertNotNull(app(CounterInbox::class)->consume($other->id));
    }

    public function test_the_form_reports_the_reader_as_offline_when_it_stops_reporting(): void
    {
        $this->device([
            'last_seen_at' => now()->subSeconds(CardReaderDevice::OFFLINE_AFTER_SECONDS + 10),
        ]);

        Livewire::actingAs($this->staffUser)
            ->test('card-reader.inbox')
            ->call('poll')
            ->assertSet('health', 'offline');
    }

    public function test_a_counter_with_no_reader_installed_shows_no_light_at_all(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test('card-reader.inbox')
            ->call('poll')
            ->assertSet('health', 'none');
    }

    public function test_a_revoked_reader_stops_driving_the_light(): void
    {
        $this->device(['revoked_at' => now()]);

        $this->assertSame('none', app(CounterInbox::class)->health($this->counter->id));
    }

    public function test_pruning_removes_only_what_has_expired(): void
    {
        $this->device();
        $fresh = $this->insertCard();
        $stale = $this->insertCard(['citizen_id' => '2222222222228']);
        $stale->update(['expires_at' => now()->subMinute()]);

        $this->assertSame(1, app(CounterInbox::class)->prune());
        $this->assertSame([$fresh->id], CardReaderRead::pluck('id')->all());
    }

    public function test_the_same_card_arriving_twice_does_not_screen_the_customer_twice(): void
    {
        $this->device();
        $this->insertCard();

        $page = Livewire::actingAs($this->staffUser)
            ->test('transaction.buy-form')
            ->dispatch('card-read', card: $this->cardPayload());

        $after = \App\Models\SanctionScreening::count();

        $page->dispatch('card-read', card: $this->cardPayload());

        // การสแกนแต่ละครั้งสร้างรายการตรวจรายชื่อหนึ่งแถว ถ้ายิงซ้ำได้
        // ประวัติของลูกค้าคนเดียวจะกลายเป็นสิบแถวในนาทีเดียว
        $this->assertSame($after, \App\Models\SanctionScreening::count());
    }

    public function test_a_consumed_card_is_deleted_not_merely_flagged(): void
    {
        $this->device();
        $this->insertCard();

        app(CounterInbox::class)->consume($this->counter->id);

        // ข้อมูลประชาชนต้องไม่นอนค้างรอใครมา prune — ไม่มีอะไรเรียก prune ให้
        $this->assertSame(0, CardReaderRead::count());
    }

    public function test_a_card_that_cannot_be_decrypted_does_not_take_down_the_counter(): void
    {
        $this->device();
        $read = $this->insertCard();

        // เกิดได้เมื่อ APP_KEY เปลี่ยน หรือมีสภาพแวดล้อมสองชุดใช้ฐานข้อมูลเดียวกัน
        // แต่คนละกุญแจ — พนักงานที่แค่เปิดหน้าซื้อไว้ต้องไม่เจอ 500
        \DB::table('card_reader_reads')->where('id', $read->id)->update(['payload' => 'ถอดรหัสไม่ออก']);

        $this->assertNull(app(CounterInbox::class)->consume($this->counter->id));

        // และต้องลบทิ้ง ไม่ให้ค้างแล้วพังซ้ำทุก 1.5 วินาที
        $this->assertSame(0, CardReaderRead::count());
    }
}
