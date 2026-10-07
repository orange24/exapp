<?php

namespace App\Services\CardReader;

use App\Models\CardReaderDevice;
use App\Models\CardReaderRead;

/**
 * กล่องรับบัตรของเคาน์เตอร์หนึ่ง ๆ
 *
 * หน้าซื้อกับหน้าขายใช้ตัวเดียวกัน ถ้าแยกกันเขียนจะมีวันที่กฎอายุข้อมูล
 * ของสองหน้าไม่ตรงกัน แล้วหน้าหนึ่งจะเก็บข้อมูลประชาชนไว้นานกว่าที่ตั้งใจ
 */
class CounterInbox
{
    /** บัตรที่รออยู่ ยังไม่มีใครรับ และยังไม่หมดอายุ */
    public function pending(int $counterId): ?CardReaderRead
    {
        return CardReaderRead::waitingFor($counterId)->first();
    }

    /**
     * รับบัตรไปใช้ แล้วปิดไม่ให้ใครรับซ้ำ
     *
     * @return array<string, mixed>|null
     */
    public function consume(int $counterId): ?array
    {
        $read = $this->pending($counterId);

        if ($read === null) {
            return null;
        }

        $payload = $read->payload;

        // ปิดทันทีที่หยิบ ไม่ใช่ตอนพนักงานกดบันทึก — ถ้าเปิดค้างไว้ หน้าต่าง
        // อีกบานที่เปิดเคาน์เตอร์เดียวกันจะหยิบใบเดิมไปซ้ำ
        $read->update(['consumed_at' => now()]);

        return $payload;
    }

    /** ready | error | offline | none — ใช้เลือกสีไฟสถานะหน้าเคาน์เตอร์ */
    public function health(int $counterId): string
    {
        $device = CardReaderDevice::active()->where('counter_id', $counterId)->first();

        return $device?->health() ?? 'none';
    }

    /** ลบบัตรที่หมดอายุแล้ว — ไม่มีใครมารับใน 2 นาทีถือว่าไม่มีใครต้องการ */
    public function prune(): int
    {
        return CardReaderRead::where('expires_at', '<=', now())->delete();
    }
}
