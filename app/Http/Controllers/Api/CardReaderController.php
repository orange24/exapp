<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CardReaderDevice;
use App\Models\CardReaderRead;
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

        $this->device($request)->update([
            'last_seen_at' => now(),
            'last_status' => $data['status'],
            'last_error' => $data['error'] ?? null,
            'agent_version' => $data['version'] ?? null,
        ]);

        return response()->json(['ok' => true]);
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
