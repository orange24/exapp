<?php

namespace App\Services\CardReader;

use App\Models\CardReaderDevice;
use App\Models\CardReaderRead;
use Illuminate\Support\Facades\Log;

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

        try {
            $payload = $read->payload;
        } catch (\Throwable $e) {
            /*
             * ถอดรหัสไม่ออก — ลบทิ้งแล้วทำเหมือนไม่มีบัตรเข้ามา
             *
             * เกิดได้เมื่อ APP_KEY เปลี่ยน หรือมีสภาพแวดล้อมสองชุดใช้ฐานข้อมูล
             * เดียวกันแต่คนละกุญแจ ถ้าปล่อยให้ exception หลุดออกไป หน้าซื้อ/ขาย
             * ทั้งหน้าจะพังด้วย 500 ทั้งที่พนักงานแค่เปิดหน้าไว้เฉย ๆ
             */
            Log::warning('ข้อมูลบัตรในกล่องรับถอดรหัสไม่ออก ลบทิ้ง', ['read_id' => $read->id]);

            $read->delete();

            return null;
        }

        $payload['kind'] = $read->kind;

        // ลบทิ้งทันทีที่ส่งมอบ ไม่ใช่แค่ทำเครื่องหมายว่าใช้แล้ว
        //
        // เดิมทำเครื่องหมายไว้เฉย ๆ แถวที่ใช้แล้วจึงนอนค้างอยู่จนกว่าจะมีคน
        // สั่ง prune ซึ่งไม่มีอะไรเรียกให้เลย — ข้อมูลประชาชนกองสะสมไปเรื่อย
        // ทั้งที่สเปกบอกว่าต้องไม่เกินสองนาที
        //
        // ลบแล้วยังกันการหยิบซ้ำได้เหมือนเดิม เพราะไม่มีอะไรให้หยิบแล้ว
        $read->delete();

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
