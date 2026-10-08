<?php

namespace App\Livewire\CardReader;

use App\Services\CardReader\CounterInbox;
use Livewire\Component;

/**
 * เฝ้ากล่องรับบัตรของเคาน์เตอร์นี้ แล้วบอกฟอร์มเมื่อมีบัตรเข้ามา
 *
 * ต้องเป็นคอมโพเนนต์แยก ห้ามเอา wire:poll ไปแปะบนฟอร์มซื้อ/ขาย
 *
 * Livewire เทียบ HTML จากเซิร์ฟเวอร์กับ DOM จริงทุกครั้งที่ re-render แล้วลบ
 * โหนดที่เซิร์ฟเวอร์ไม่รู้จักทิ้ง ฟอร์มมีหน้าต่างครอปรูปพาสปอร์ตที่ Cropper.js
 * ฉีด DOM ของตัวเองเข้าไป การ poll บนฟอร์มจึงลบกรอบครอปทิ้งทุก 1.5 วินาที
 * พนักงานเห็นรูปแต่ลากกรอบไม่ได้ เหมือนระบบข้ามขั้นตอนครอปไปเฉย ๆ
 *
 * คอมโพเนนต์นี้เล็กและไม่มีอะไรที่ JS ไปฉีด DOM ทับ จึง re-render ได้ปลอดภัย
 */
class Inbox extends Component
{
    public int $counterId = 0;

    /** ready | error | offline | none */
    public string $health = 'none';

    public function mount(): void
    {
        $this->counterId = (int) session('working_counter_id', 0);
    }

    public function poll(): void
    {
        if ($this->counterId === 0) {
            return;
        }

        $inbox = app(CounterInbox::class);
        $this->health = $inbox->health($this->counterId);

        $card = $inbox->consume($this->counterId);

        if ($card !== null) {
            // ส่งให้ฟอร์มที่เปิดอยู่เติมข้อมูลเอง คอมโพเนนต์นี้ไม่รู้จักฟอร์ม
            $this->dispatch('card-read', card: $card);
        }
    }

    public function render()
    {
        return view('livewire.card-reader.inbox');
    }
}
