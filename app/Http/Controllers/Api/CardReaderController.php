<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CardReaderDevice;
use App\Models\CardReaderPassportRequest;
use App\Models\CardReaderRead;
use App\Services\CardReader\ChipPhoto;
use App\Services\CardReader\PassportChipRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardReaderController extends Controller
{
    private function device(Request $request): CardReaderDevice
    {
        return $request->attributes->get('card_reader_device');
    }

    /** agent บอกว่ายังอยู่ ทุก 30 วินาที — ใช้ขับไฟสถานะหน้าเคาน์เตอร์ */
    public function heartbeat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:ready,no_reader,error',
            'error' => 'nullable|string|max:255',
            'version' => 'nullable|string|max:40',
        ]);

        $device = $this->device($request);

        $device->update([
            'last_seen_at' => now(),
            'last_status' => $data['status'],
            'last_error' => $data['error'] ?? null,
            'agent_version' => $data['version'] ?? null,
        ]);

        /*
         * สัญญาณชีพพากุญแจเปิดชิปกลับไปด้วย
         *
         * ไม่เปิดช่องทางใหม่โดยตั้งใจ — ทิศเดียวที่ใช้ได้คือ agent ยิงออกมาหาเรา
         * การรวมไว้ในคำขอเดียวแปลว่ามีที่เดียวที่ต้องดูแลเรื่อง retry, timeout
         * และการเพิกถอนเครื่อง
         */
        $pending = app(PassportChipRequests::class)->forAgent($device->counter_id);
        $key = $pending?->key();

        if ($pending !== null && $key === null) {
            // กุญแจอ่านไม่ออกก็ส่งให้ agent ไม่ได้ ปิดคำขอทิ้งแทนที่จะให้
            // พนักงานรอการแตะที่ไม่มีวันสำเร็จ
            app(PassportChipRequests::class)->markFailed($pending, 'กุญแจเสียหาย กรุณาถ่ายรูปพาสปอร์ตใหม่');
        }

        return response()->json([
            'ok' => true,
            'passport_request' => $key === null ? null : [
                'id' => $pending->id,
                'document_no' => $key['document_no'],
                'date_of_birth' => $key['date_of_birth'],
                'expiry_date' => $key['expiry_date'],
            ],
        ]);
    }

    /** agent รายงานว่าอ่านชิปไปถึงขั้นไหนแล้ว — การอ่านใช้เวลาสองสามวินาที */
    public function passportProgress(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_id' => 'required|integer',
            'progress' => 'required|string|max:120',
        ]);

        $chip = app(PassportChipRequests::class);
        $pending = $this->ownedRequest($request, (int) $data['request_id']);

        if ($pending === null) {
            return response()->json(['ok' => false], 404);
        }

        $chip->markReading($pending, $data['progress']);

        return response()->json(['ok' => true]);
    }

    public function passport(Request $request): JsonResponse
    {
        $data = $request->validate([
            'request_id' => 'required|integer',
            'ok' => 'required|boolean',
            'error' => 'nullable|string|max:255',
            'document_no' => 'nullable|string|max:20',
            'surname' => 'nullable|string|max:120',
            'given_names' => 'nullable|string|max:120',
            'nationality' => 'nullable|string|max:3',
            'issuing_state' => 'nullable|string|max:3',
            'national_id' => 'nullable|string|max:20',
            'date_of_birth' => 'nullable|date_format:Y-m-d',
            'expiry_date' => 'nullable|date_format:Y-m-d',
            'sex' => 'nullable|string|max:1',
            'authenticity' => 'required|in:verified,unverifiable,failed',
            'chip_authentication' => 'nullable|boolean',
            'photo_base64' => 'nullable|string',
            'photo_mime' => 'nullable|string|max:40',
        ]);

        $device = $this->device($request);
        $chip = app(PassportChipRequests::class);
        $pending = $this->ownedRequest($request, (int) $data['request_id']);

        if ($pending === null) {
            return response()->json(['ok' => false], 404);
        }

        if (! $data['ok']) {
            $chip->markFailed($pending, $data['error'] ?: 'อ่านชิปไม่สำเร็จ');

            return response()->json(['ok' => true]);
        }

        $payload = collect($data)->except([
            'request_id', 'ok', 'error', 'photo_base64', 'photo_mime',
        ])->all();

        // แปลงรูปที่เบราว์เซอร์แสดงไม่ได้ ก่อนเก็บ ไม่ใช่ตอนแสดง — จะได้แปลงครั้งเดียว
        if (! empty($data['photo_base64'])) {
            $photo = app(ChipPhoto::class)->toBrowserReadable(
                $data['photo_base64'],
                $data['photo_mime'] ?? 'image/jp2',
            );

            if ($photo !== null) {
                $payload['photo_base64'] = $photo['data'];
                $payload['photo_mime'] = $photo['mime'];
            }
        }

        CardReaderRead::where('counter_id', $device->counter_id)
            ->whereNull('consumed_at')
            ->delete();

        CardReaderRead::create([
            'counter_id' => $device->counter_id,
            'device_id' => $device->id,
            'kind' => CardReaderRead::KIND_PASSPORT,
            'payload' => $payload,
            'read_at' => now(),
            'expires_at' => now()->addSeconds(CardReaderRead::LIFETIME_SECONDS),
        ]);

        $chip->markDone($pending);

        return response()->json(['ok' => true]);
    }

    /**
     * คำขอต้องเป็นของเคาน์เตอร์ที่เครื่องนี้ผูกอยู่เท่านั้น
     *
     * ถ้าไม่ตรวจ เครื่องของสาขาหนึ่งจะยิงผลไปปิดคำขอของอีกสาขาได้
     */
    private function ownedRequest(Request $request, int $id): ?CardReaderPassportRequest
    {
        return CardReaderPassportRequest::where('id', $id)
            ->where('counter_id', $this->device($request)->counter_id)
            ->first();
    }

    public function read(Request $request): JsonResponse
    {
        $data = $request->validate([
            'citizen_id' => 'required|digits:13',
            'name_th' => 'nullable|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'first_name_en' => 'nullable|string|max:120',
            'last_name_en' => 'nullable|string|max:120',
            'date_of_birth' => 'nullable|date_format:Y-m-d',
            'address' => 'nullable|string|max:500',
            'issue_date' => 'nullable|date_format:Y-m-d',
            'expire_date' => 'nullable|date_format:Y-m-d',
            'photo_jpeg_base64' => 'nullable|string',
        ]);

        $device = $this->device($request);

        // บัตรใบใหม่ทับใบเก่าเสมอ — ถ้าปล่อยให้ค้างอยู่หลายใบ พนักงานที่เสียบ
        // บัตรผิดคนแล้วเสียบใหม่จะได้ข้อมูลของคนแรกไปใส่ในรายการของคนที่สอง
        CardReaderRead::where('counter_id', $device->counter_id)
            ->whereNull('consumed_at')
            ->delete();

        CardReaderRead::create([
            'counter_id' => $device->counter_id,
            'device_id' => $device->id,
            'payload' => $data,
            'read_at' => now(),
            'expires_at' => now()->addSeconds(CardReaderRead::LIFETIME_SECONDS),
        ]);

        $device->update([
            'last_seen_at' => now(),
            'last_status' => CardReaderDevice::STATUS_READY,
            'last_error' => null,
        ]);

        return response()->json(['ok' => true]);
    }
}
