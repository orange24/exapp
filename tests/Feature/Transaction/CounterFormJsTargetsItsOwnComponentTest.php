<?php

namespace Tests\Feature\Transaction;

use Tests\TestCase;

/**
 * JS บนหน้าซื้อ/ขายต้องเรียกเมธอดบนคอมโพเนนต์ของตัวเอง
 *
 * เดิมใช้ document.querySelector('[wire:id]') ซึ่งคืนตัวแรกในหน้า
 * พอกระดิ่งแจ้งเตือนถูกเพิ่มเข้า header (2026-10-02) มันถูกวาดก่อน
 * @yield('content') จึงกลายเป็นตัวแรก แล้ว OCR พาสปอร์ตก็ยิง
 * receiveOcrData ไปที่กระดิ่งแล้วได้ 500 ทุกครั้ง — ทั้ง UAT และ production
 *
 * เทสนี้อ่านไฟล์ตรง ๆ เพราะพฤติกรรมอยู่ใน JS ที่ PHPUnit รันไม่ได้
 * แต่รูปแบบที่ผิดจับได้จากตัวโค้ดเอง
 */
class CounterFormJsTargetsItsOwnComponentTest extends TestCase
{
    /** @return array<int, string> */
    private function counterForms(): array
    {
        return [
            resource_path('views/livewire/transaction/buy-form.blade.php'),
            resource_path('views/livewire/transaction/sell-form.blade.php'),
        ];
    }

    public function test_no_counter_form_grabs_the_first_livewire_component_on_the_page(): void
    {
        foreach ($this->counterForms() as $path) {
            $source = file_get_contents($path);

            $this->assertStringNotContainsString(
                "document.querySelector('[wire",
                $source,
                basename($path) . ' เรียกคอมโพเนนต์ตัวแรกในหน้า ซึ่งคือกระดิ่งแจ้งเตือน ไม่ใช่ฟอร์มนี้',
            );
        }
    }

    public function test_every_counter_form_resolves_its_component_from_its_own_root(): void
    {
        foreach ($this->counterForms() as $path) {
            $this->assertStringContainsString(
                'this.$el.closest',
                file_get_contents($path),
                basename($path) . ' ต้องหาคอมโพเนนต์จาก root ของตัวเอง',
            );
        }
    }

    public function test_the_notification_bell_really_is_rendered_before_the_page_content(): void
    {
        $layout = file_get_contents(resource_path('views/layouts/app.blade.php'));

        $bell = strpos($layout, 'livewire:notification-bell');
        $content = strpos($layout, "@yield('content')");

        $this->assertNotFalse($bell);
        $this->assertNotFalse($content);

        // ถ้าวันหนึ่งกระดิ่งย้ายไปอยู่หลัง content เทสสองตัวบนจะยังคุ้มครองอยู่
        // แต่คำอธิบายข้างบนจะเลิกเป็นจริง — ให้เทสตัวนี้บอกเราเอง
        $this->assertLessThan($content, $bell, 'กระดิ่งไม่ได้อยู่ก่อน content แล้ว — ทบทวนคำอธิบายในเทสนี้');
    }

    public function test_no_counter_form_polls_itself(): void
    {
        foreach ($this->counterForms() as $path) {
            $source = file_get_contents($path);

            // ฟอร์มมีหน้าต่างครอปที่ Cropper.js ฉีด DOM ของตัวเองเข้าไป
            // ทุกครั้งที่ฟอร์ม re-render Livewire จะลบโหนดนั้นทิ้งเพราะ
            // เซิร์ฟเวอร์ไม่รู้จัก — วัดจากของจริงแล้วกรอบครอปอยู่ได้ 1.5 วินาที
            // พอดีกับจังหวะ poll แล้วหายไป พนักงานเห็นรูปแต่ลากกรอบไม่ได้
            $this->assertStringNotContainsString(
                'wire:poll',
                $source,
                basename($path) . ' ห้าม poll ตัวเอง — ให้ card-reader.inbox เฝ้าแล้วส่ง event มาแทน',
            );
        }
    }

    public function test_the_inbox_component_is_the_one_that_polls(): void
    {
        $this->assertStringContainsString(
            'wire:poll',
            file_get_contents(resource_path('views/livewire/card-reader/inbox.blade.php')),
        );

        foreach ($this->counterForms() as $path) {
            $this->assertStringContainsString(
                'livewire:card-reader.inbox',
                file_get_contents($path),
                basename($path) . ' ต้องวางคอมโพเนนต์ที่เฝ้ากล่องรับบัตรไว้',
            );
        }
    }
}
