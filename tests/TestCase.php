<?php

namespace Tests;

use App\Support\WorkingDayClock;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * วันทำการที่ "ตอนนี้" ตกอยู่
     *
     * เทสที่สร้างรายการด้วย now() แล้วเปิดวันทำการด้วย now()->format('Y-m-d')
     * จะล้มเองทุกคืนระหว่างเที่ยงคืนถึงเวลาตัดรอบ เพราะรายการตกไปอยู่ในวัน
     * ทำการของเมื่อวาน แต่วันที่ที่เปิดไว้เป็นของวันนี้ — นาฬิกาจริงไม่ควร
     * เป็นตัวตัดสินว่าเทสเขียวหรือแดง
     */
    protected function workingDate(): string
    {
        return app(WorkingDayClock::class)->currentDate();
    }

    /**
     * เวลาหนึ่งภายในวันทำการ นับจากวันทำการปัจจุบัน
     *
     * ใช้แทน now()->setTime(...) ในการสร้างข้อมูลทดสอบ — now() ผูกกับวันตาม
     * ปฏิทิน พอรันตอนตีหนึ่งมันจะชี้ไปวันทำการถัดไปซึ่งไม่ใช่วันที่เทส
     * เพิ่งเปิดไว้ $dayOffset ติดลบคือวันทำการก่อนหน้า
     */
    protected function workingMoment(int $hour, int $minute = 0, int $dayOffset = 0): Carbon
    {
        return Carbon::parse($this->workingDate())
            ->addDays($dayOffset)
            ->setTime($hour, $minute);
    }

    /**
     * เวลาที่อยู่ในวันทำการปัจจุบันแน่นอน และเป็นอดีตแน่นอน
     *
     * ใช้วางรายการที่ต้องถูกนับรวมในยอด "ตอนนี้" การเลือกชั่วโมงตายตัวอย่าง
     * 09:00 ใช้ไม่ได้ เพราะถ้าชุดเทสรันตอนเจ็ดโมงเช้า เวลานั้นยังไม่มาถึง
     * ส่วน now()->subMinute() เฉยๆ ก็ตกไปอยู่วันทำการก่อนหน้าได้ ถ้ารัน
     * คาบเส้นเวลาตัดรอบพอดี
     */
    protected function momentInsideCurrentWorkingDay(): Carbon
    {
        $clock = app(WorkingDayClock::class);
        $floor = $clock->dayStart($clock->currentDate())->addSecond();
        $recent = Carbon::now()->subMinute();

        return $recent->gt($floor) ? $recent : $floor;
    }
}
