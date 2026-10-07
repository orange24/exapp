<?php

namespace Tests\Feature\CardReader;

use App\Models\CardReaderDevice;
use App\Models\CardReaderRead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class CardReaderApiTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private string $token = 'tok_live_abcdef0123456789';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function device(array $attributes = []): CardReaderDevice
    {
        return CardReaderDevice::create(array_merge([
            'name' => 'สีลม เคาน์เตอร์ 1',
            'counter_id' => $this->counter->id,
            'token_hash' => CardReaderDevice::hashToken($this->token),
        ], $attributes));
    }

    /** @return array<string, string> */
    private function card(array $overrides = []): array
    {
        return array_merge([
            'citizen_id' => '1234567890123',
            'name_th' => 'นายสมชาย ใจดี',
            'name_en' => 'MR. SOMCHAI JAIDEE',
            'first_name_en' => 'SOMCHAI',
            'last_name_en' => 'JAIDEE',
            'date_of_birth' => '1981-12-18',
            'address' => '1 ถนนสีลม',
        ], $overrides);
    }

    public function test_a_request_without_a_token_is_refused(): void
    {
        $this->device();

        $this->postJson(route('api.card-reader.read'), $this->card())->assertUnauthorized();
        $this->assertSame(0, CardReaderRead::count());
    }

    public function test_a_wrong_token_is_refused(): void
    {
        $this->device();

        $this->withToken('tok_live_wrong')
            ->postJson(route('api.card-reader.read'), $this->card())
            ->assertUnauthorized();

        $this->assertSame(0, CardReaderRead::count());
    }

    public function test_a_revoked_device_is_refused(): void
    {
        $this->device(['revoked_at' => now()->subMinute()]);

        // เครื่องที่หายไปต้องกลายเป็นก้อนพลาสติกทันทีที่กดเพิกถอน
        $this->withToken($this->token)
            ->postJson(route('api.card-reader.read'), $this->card())
            ->assertUnauthorized();

        $this->assertSame(0, CardReaderRead::count());
    }

    public function test_a_card_read_lands_in_the_inbox_of_the_bound_counter(): void
    {
        $device = $this->device();

        $this->withToken($this->token)
            ->postJson(route('api.card-reader.read'), $this->card())
            ->assertOk();

        $read = CardReaderRead::firstOrFail();

        $this->assertSame($this->counter->id, $read->counter_id);
        $this->assertSame($device->id, $read->device_id);
        $this->assertSame('1234567890123', $read->payload['citizen_id']);
        $this->assertNull($read->consumed_at);
    }

    public function test_card_data_is_not_readable_in_the_raw_column(): void
    {
        $this->device();

        $this->withToken($this->token)->postJson(route('api.card-reader.read'), $this->card())->assertOk();

        // ข้อมูลประชาชนไม่ควรนอนเป็น plaintext แม้จะอยู่แค่สองนาที
        $raw = (string) \DB::table('card_reader_reads')->value('payload');

        $this->assertStringNotContainsString('1234567890123', $raw);
        $this->assertStringNotContainsString('สมชาย', $raw);
    }

    public function test_a_read_expires_two_minutes_after_the_card_was_inserted(): void
    {
        $this->device();

        $this->withToken($this->token)->postJson(route('api.card-reader.read'), $this->card())->assertOk();

        $read = CardReaderRead::firstOrFail();

        $this->assertEqualsWithDelta(
            CardReaderRead::LIFETIME_SECONDS,
            $read->read_at->diffInSeconds($read->expires_at),
            2,
        );
    }

    public function test_a_second_card_replaces_the_first_instead_of_queueing_behind_it(): void
    {
        $this->device();

        $this->withToken($this->token)->postJson(route('api.card-reader.read'), $this->card())->assertOk();
        $this->withToken($this->token)
            ->postJson(route('api.card-reader.read'), $this->card(['citizen_id' => '9876543210987']))
            ->assertOk();

        // เสียบผิดคนแล้วเสียบใหม่ ต้องไม่ได้ข้อมูลของคนแรกไปใส่รายการของคนที่สอง
        $this->assertSame(1, CardReaderRead::count());
        $this->assertSame('9876543210987', CardReaderRead::firstOrFail()->payload['citizen_id']);
    }

    public function test_a_citizen_id_that_is_not_thirteen_digits_is_refused(): void
    {
        $this->device();

        $this->withToken($this->token)
            ->postJson(route('api.card-reader.read'), $this->card(['citizen_id' => '123']))
            ->assertStatus(422);

        $this->assertSame(0, CardReaderRead::count());
    }

    public function test_a_heartbeat_records_that_the_reader_is_alive(): void
    {
        $device = $this->device();

        $this->assertFalse($device->isOnline());

        $this->withToken($this->token)
            ->postJson(route('api.card-reader.heartbeat'), ['status' => 'ready', 'version' => '1.0.0'])
            ->assertOk();

        $device->refresh();

        $this->assertTrue($device->isOnline());
        $this->assertSame('ready', $device->health());
        $this->assertSame('1.0.0', $device->agent_version);
    }

    public function test_a_reader_that_stopped_reporting_counts_as_offline(): void
    {
        $device = $this->device([
            'last_seen_at' => now()->subSeconds(CardReaderDevice::OFFLINE_AFTER_SECONDS + 10),
            'last_status' => 'ready',
        ]);

        // เงียบหายไปต้องไม่แสดงเป็นเขียว ไม่งั้นพนักงานจะรอบัตรที่ไม่มีวันมา
        $this->assertSame('offline', $device->health());
    }

    public function test_a_reader_reporting_an_error_is_not_shown_as_ready(): void
    {
        $device = $this->device();

        $this->withToken($this->token)
            ->postJson(route('api.card-reader.heartbeat'), ['status' => 'no_reader', 'error' => 'ไม่พบเครื่องอ่าน'])
            ->assertOk();

        $this->assertSame('error', $device->refresh()->health());
    }
}
