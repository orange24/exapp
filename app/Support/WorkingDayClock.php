<?php

namespace App\Support;

use App\Models\Setting;
use Carbon\Carbon;

/**
 * วันทำการของร้าน ซึ่งไม่ใช่วันตามปฏิทิน
 *
 * ร้านปิดรอบตี 3 แปลว่าวันทำการที่ 11 กินเวลาตั้งแต่ตี 3 ของวันที่ 11
 * ไปจนถึงตี 3 ของวันที่ 12 รายการที่พนักงานทำตอนเที่ยงคืนครึ่งของวันที่ 12
 * ยังเป็นยอดของวันที่ 11 อยู่
 *
 * ก่อนจะมีคลาสนี้ สูตรเดียวกันถูกคัดลอกไว้ 15 จุด และทุกจุดถาม "วันนี้"
 * ด้วย now()->format('Y-m-d') ซึ่งเปลี่ยนค่าตอนเที่ยงคืน ไม่ใช่ตอนตัดรอบ
 * ช่วงเวลาที่ได้ระหว่างเที่ยงคืนถึงตี 3 จึงเริ่มในอนาคต และรายการที่กำลัง
 * เกิดขึ้นตรงนั้นหลุดออกจากทุกรายงาน
 *
 * ขอบของช่วงเป็น เปิดซ้าย-ปิดขวา: (dayStart, dayEnd] ตรงกับที่คิวรี่
 * ทั่วระบบใช้ ('>' กับ '<=') ห้ามเปลี่ยน ไม่งั้นตัวเลขรายงานย้อนหลังขยับ
 */
class WorkingDayClock
{
    public const SETTING_KEY = 'WORKING_CUT_OFF';

    public const DEFAULT_CUT_OFF = '03:00:00';

    private ?string $cutoff = null;

    /** เวลาตัดรอบในรูป HH:MM:SS */
    public function cutoff(): string
    {
        if ($this->cutoff !== null) {
            return $this->cutoff;
        }

        $raw = (string) Setting::get(self::SETTING_KEY, self::DEFAULT_CUT_OFF);

        // ค่าในฐานข้อมูลเคยถูกบันทึกทั้งแบบ 03:00 และ 03:00:00 — รับทั้งสองแบบ
        // ถ้าเจอค่าที่อ่านไม่ออกให้ถอยไปใช้ค่าปริยาย ดีกว่าทำให้ทุกหน้าล่ม
        try {
            return $this->cutoff = Carbon::createFromTimeString($raw)->format('H:i:s');
        } catch (\Throwable) {
            return $this->cutoff = self::DEFAULT_CUT_OFF;
        }
    }

    /** ลืมค่าที่จำไว้ — ใช้หลังผู้ใช้แก้ค่าตั้งค่าในคำขอเดียวกัน */
    public function forget(): void
    {
        $this->cutoff = null;
    }

    /** วันทำการที่เวลาขณะนี้ตกอยู่ */
    public function currentDate(): string
    {
        return $this->dateAt(Carbon::now());
    }

    /** วันทำการที่เวลาหนึ่งตกอยู่ */
    public function dateAt(string|Carbon $at): string
    {
        $at = $at instanceof Carbon ? $at->copy() : Carbon::parse($at);

        $cut = Carbon::parse($at->format('Y-m-d') . ' ' . $this->cutoff());

        // ก่อนถึงเวลาตัดรอบยังนับเป็นวันทำการของเมื่อวาน
        return $at->gt($cut)
            ? $at->format('Y-m-d')
            : $at->subDay()->format('Y-m-d');
    }

    /** ขอบซ้ายของวันทำการ — ไม่รวมตัวมันเอง */
    public function dayStart(string|Carbon $date): Carbon
    {
        return Carbon::parse(
            Carbon::parse($date)->format('Y-m-d') . ' ' . $this->cutoff()
        );
    }

    /** ขอบขวาของวันทำการ — รวมตัวมันเอง */
    public function dayEnd(string|Carbon $date): Carbon
    {
        return $this->dayStart($date)->addDay();
    }

    /**
     * ช่วงเวลาของวันทำการในรูปข้อความ พร้อมใส่ลงคิวรี่ได้ตรงๆ
     *
     * @return array{0: string, 1: string}
     */
    public function window(string|Carbon $date): array
    {
        return [
            $this->dayStart($date)->format('Y-m-d H:i:s'),
            $this->dayEnd($date)->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * ช่วงเวลาที่คลุมหลายวันทำการ — ตั้งแต่เปิดวันแรกจนปิดวันสุดท้าย
     *
     * @return array{0: string, 1: string}
     */
    public function range(string|Carbon $from, string|Carbon $to): array
    {
        return [
            $this->dayStart($from)->format("Y-m-d H:i:s"),
            $this->dayEnd($to)->format("Y-m-d H:i:s"),
        ];
    }

    /** ช่วงเวลาของวันทำการปัจจุบัน */    public function currentWindow(): array
    {
        return $this->window($this->currentDate());
    }
}
