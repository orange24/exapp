<?php

namespace App\Console\Commands;

use App\Services\CardReader\CounterInbox;
use Illuminate\Console\Command;

/**
 * ลบข้อมูลบัตรที่หมดอายุแล้ว
 *
 * ปกติหน้าเคาน์เตอร์หยิบไปใช้แล้วปิดเองภายในไม่กี่วินาที แถวที่ค้างคือแถวที่
 * ไม่มีใครมารับ — เสียบบัตรตอนไม่มีใครเปิดหน้าซื้อ/ขาย หรือเสียบแล้วเดินออกไป
 *
 * ปล่อยไว้ไม่ได้เพราะในนั้นคือชื่อ เลข 13 หลัก ที่อยู่ และรูปของประชาชน
 */
class CardReaderPrune extends Command
{
    protected $signature = 'card-reader:prune';

    protected $description = 'ลบข้อมูลบัตรประชาชนที่หมดอายุแล้วออกจากกล่องรับ';

    public function handle(CounterInbox $inbox): int
    {
        $deleted = $inbox->prune();

        $this->info("ลบข้อมูลบัตรที่หมดอายุ {$deleted} รายการ");

        return self::SUCCESS;
    }
}
