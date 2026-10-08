<?php

namespace App\Services\CardReader;

use App\Models\CardReaderPassportRequest;

/**
 * คิวกุญแจเปิดชิปพาสปอร์ตของแต่ละเคาน์เตอร์
 *
 * ชิปพาสปอร์ตล็อกอยู่ ต่างจากบัตรประชาชนไทยที่เสียบแล้วอ่านได้เลย กุญแจสร้างจาก
 * เลขพาสปอร์ต + วันเกิด + วันหมดอายุ ตามข้อกำหนด ICAO 9303
 *
 * กุญแจอยู่ที่เบราว์เซอร์ (ได้จาก OCR) ซึ่งส่งตรงไปหา agent ไม่ได้ เพราะหน้า HTTPS
 * เรียก http://127.0.0.1 ไม่ได้ จึงฝากไว้ที่เซิร์ฟเวอร์ให้ agent มาหยิบเอง
 */
class PassportChipRequests
{
    /** @param array{document_no: string, date_of_birth: string, expiry_date: string} $mrz */
    public function open(int $counterId, array $mrz, ?int $userId = null): CardReaderPassportRequest
    {
        // คำขอเก่าของเคาน์เตอร์นี้ตกไปทันที — พนักงานถ่ายใบใหม่แปลว่าเลิกสนใจใบเก่าแล้ว
        // ถ้าปล่อยค้างไว้ agent อาจหยิบกุญแจของลูกค้าคนก่อนไปใช้กับเล่มที่เพิ่งแตะ
        CardReaderPassportRequest::where('counter_id', $counterId)
            ->whereIn('status', [CardReaderPassportRequest::STATUS_PENDING, CardReaderPassportRequest::STATUS_READING])
            ->delete();

        return CardReaderPassportRequest::create([
            'counter_id' => $counterId,
            'mrz' => [
                'document_no' => $mrz['document_no'],
                'date_of_birth' => $mrz['date_of_birth'],
                'expiry_date' => $mrz['expiry_date'],
            ],
            'status' => CardReaderPassportRequest::STATUS_PENDING,
            'requested_by' => $userId,
            'expires_at' => now()->addSeconds(CardReaderPassportRequest::LIFETIME_SECONDS),
        ]);
    }

    /** คำขอที่ agent ควรหยิบไปทำ */
    public function forAgent(int $counterId): ?CardReaderPassportRequest
    {
        return CardReaderPassportRequest::waitingForAgent($counterId)->first();
    }

    /** สถานะล่าสุดที่หน้าเคาน์เตอร์ควรแสดง */
    public function current(int $counterId): ?CardReaderPassportRequest
    {
        return CardReaderPassportRequest::liveFor($counterId)->first();
    }

    public function markReading(CardReaderPassportRequest $request, string $progress): void
    {
        if ($request->isFinished()) {
            return;
        }

        $request->update([
            'status' => CardReaderPassportRequest::STATUS_READING,
            'progress' => $progress,
        ]);
    }

    public function markDone(CardReaderPassportRequest $request): void
    {
        $request->update([
            'status' => CardReaderPassportRequest::STATUS_DONE,
            'progress' => null,
        ]);
    }

    public function markFailed(CardReaderPassportRequest $request, string $error): void
    {
        $request->update([
            'status' => CardReaderPassportRequest::STATUS_FAILED,
            'progress' => null,
            'error' => $error,
        ]);
    }

    /** ลบกุญแจที่หมดอายุ — ปล่อยไว้ไม่ได้เพราะมันเปิดชิปของคนจริงได้ */
    public function prune(): int
    {
        return CardReaderPassportRequest::where('expires_at', '<=', now())->delete();
    }
}
