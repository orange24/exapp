<?php

namespace Tests\Feature\CardReader;

use App\Models\CardReaderDevice;
use App\Models\CardReaderPassportRequest;
use App\Models\CardReaderRead;
use App\Models\Counter;
use App\Services\CardReader\PassportChipRequests;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class PassportChipApiTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private string $token = 'crd_passport_test_token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        CardReaderDevice::create([
            'name' => 'สีลม เคาน์เตอร์ 1',
            'counter_id' => $this->counter->id,
            'token_hash' => CardReaderDevice::hashToken($this->token),
        ]);
    }

    private function openRequest(?int $counterId = null): CardReaderPassportRequest
    {
        return app(PassportChipRequests::class)->open($counterId ?? $this->counter->id, [
            'document_no' => 'AC2784283',
            'date_of_birth' => '830625',
            'expiry_date' => '311031',
        ]);
    }

    /** @return array<string, mixed> */
    private function chipResult(array $overrides = []): array
    {
        return array_merge([
            'ok' => true,
            'document_no' => 'AC2784283',
            'surname' => 'KITTIKUM',
            'given_names' => 'WATCHARA',
            'nationality' => 'THA',
            'issuing_state' => 'THA',
            'national_id' => '3500900234628',
            'date_of_birth' => '1983-06-25',
            'expiry_date' => '2031-10-31',
            'sex' => 'M',
            'authenticity' => 'verified',
            'chip_authentication' => true,
        ], $overrides);
    }

    public function test_the_heartbeat_hands_the_agent_the_key_waiting_at_its_counter(): void
    {
        $request = $this->openRequest();

        $response = $this->withToken($this->token)
            ->postJson(route('api.card-reader.heartbeat'), ['status' => 'ready'])
            ->assertOk();

        // กุญแจเดินทางกลับมากับสัญญาณชีพ ไม่ใช่ช่องทางใหม่ — ทิศเดียวที่ใช้ได้
        // คือ agent ยิงออกมาหาเรา
        $response->assertJsonPath('passport_request.id', $request->id);
        $response->assertJsonPath('passport_request.document_no', 'AC2784283');
        $response->assertJsonPath('passport_request.date_of_birth', '830625');
    }

    public function test_the_heartbeat_hands_over_nothing_when_no_one_asked(): void
    {
        $this->withToken($this->token)
            ->postJson(route('api.card-reader.heartbeat'), ['status' => 'ready'])
            ->assertOk()
            ->assertJsonPath('passport_request', null);
    }

    public function test_a_key_belonging_to_another_counter_is_never_handed_over(): void
    {
        $this->openRequest($this->counter2->id);

        // กุญแจเปิดชิปของลูกค้าที่สาขาอื่นต้องไม่หลุดมาที่เครื่องนี้
        $this->withToken($this->token)
            ->postJson(route('api.card-reader.heartbeat'), ['status' => 'ready'])
            ->assertOk()
            ->assertJsonPath('passport_request', null);
    }

    public function test_an_expired_key_is_not_handed_over(): void
    {
        $request = $this->openRequest();
        $request->update(['expires_at' => now()->subSecond()]);

        $this->withToken($this->token)
            ->postJson(route('api.card-reader.heartbeat'), ['status' => 'ready'])
            ->assertOk()
            ->assertJsonPath('passport_request', null);
    }

    public function test_a_chip_read_lands_in_the_inbox_and_closes_the_request(): void
    {
        $request = $this->openRequest();

        $this->withToken($this->token)
            ->postJson(route('api.card-reader.passport'), $this->chipResult(['request_id' => $request->id]))
            ->assertOk();

        $read = CardReaderRead::firstOrFail();

        $this->assertSame(CardReaderRead::KIND_PASSPORT, $read->kind);
        $this->assertSame('KITTIKUM', $read->payload['surname']);
        $this->assertSame('WATCHARA', $read->payload['given_names']);

        // พาสปอร์ตไทยใส่เลขบัตรประชาชนไว้ในชิป ซึ่งเป็นตัวที่บล็อกแข็งที่ 100 คะแนน
        $this->assertSame('3500900234628', $read->payload['national_id']);
        $this->assertSame('verified', $read->payload['authenticity']);

        $this->assertSame(CardReaderPassportRequest::STATUS_DONE, $request->refresh()->status);
    }

    public function test_a_failed_read_records_why_without_filling_the_form(): void
    {
        $request = $this->openRequest();

        $this->withToken($this->token)
            ->postJson(route('api.card-reader.passport'), [
                'request_id' => $request->id,
                'ok' => false,
                'error' => 'เปิดชิปไม่ได้ — ข้อมูลจากหน้าพาสปอร์ตไม่ตรง',
                'authenticity' => 'failed',
            ])
            ->assertOk();

        $this->assertSame(CardReaderPassportRequest::STATUS_FAILED, $request->refresh()->status);
        $this->assertSame(0, CardReaderRead::count());
    }

    public function test_a_device_cannot_close_a_request_belonging_to_another_counter(): void
    {
        $other = $this->openRequest($this->counter2->id);

        $this->withToken($this->token)
            ->postJson(route('api.card-reader.passport'), $this->chipResult(['request_id' => $other->id]))
            ->assertNotFound();

        $this->assertSame(CardReaderPassportRequest::STATUS_PENDING, $other->refresh()->status);
        $this->assertSame(0, CardReaderRead::count());
    }

    public function test_progress_is_recorded_so_the_counter_can_show_it(): void
    {
        $request = $this->openRequest();

        $this->withToken($this->token)
            ->postJson(route('api.card-reader.passport-progress'), [
                'request_id' => $request->id,
                'progress' => 'กำลังอ่านรูปถ่าย',
            ])
            ->assertOk();

        $request->refresh();

        $this->assertSame(CardReaderPassportRequest::STATUS_READING, $request->status);
        $this->assertSame('กำลังอ่านรูปถ่าย', $request->progress);
    }

    public function test_an_unknown_authenticity_value_is_refused(): void
    {
        $request = $this->openRequest();

        // สามสถานะเท่านั้น — การเพิ่มสถานะใหม่โดยไม่ตั้งใจจะทำให้หน้าจอตีความผิด
        $this->withToken($this->token)
            ->postJson(route('api.card-reader.passport'), $this->chipResult([
                'request_id' => $request->id,
                'authenticity' => 'probably_fine',
            ]))
            ->assertStatus(422);
    }

    public function test_asking_again_cancels_the_key_still_waiting(): void
    {
        $first = $this->openRequest();
        $second = $this->openRequest();

        // พนักงานถ่ายใบใหม่แปลว่าเลิกสนใจใบเก่า ถ้าปล่อยค้างไว้ agent อาจหยิบ
        // กุญแจของลูกค้าคนก่อนไปใช้กับเล่มที่เพิ่งแตะ
        $this->assertNull(CardReaderPassportRequest::find($first->id));
        $this->assertSame(1, CardReaderPassportRequest::count());
        $this->assertSame($second->id, CardReaderPassportRequest::firstOrFail()->id);
    }

    public function test_the_mrz_key_is_not_readable_in_the_raw_column(): void
    {
        $this->openRequest();

        // MRZ คือกุญแจที่เปิดชิปของเล่มนั้นได้จริง เก็บเป็น plaintext ไม่ได้
        $raw = (string) \DB::table('card_reader_passport_requests')->value('mrz');

        $this->assertStringNotContainsString('AC2784283', $raw);
        $this->assertStringNotContainsString('830625', $raw);
    }
}
