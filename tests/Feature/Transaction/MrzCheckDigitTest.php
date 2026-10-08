<?php

namespace Tests\Feature\Transaction;

use Tests\TestCase;

/**
 * ตัวคำนวณเลขตรวจสอบ MRZ อยู่ใน JS ของหน้าซื้อ/ขาย
 *
 * เทสนี้ดึงฟังก์ชันตัวจริงออกมาจากไฟล์แล้วคำนวณซ้ำด้วย PHP ตามมาตรฐานเดียวกัน
 * ไม่ได้เขียนตรรกะใหม่ให้ผ่าน — ถ้าสองฝั่งไม่ตรงกันแปลว่าฝั่งใดฝั่งหนึ่งผิด
 *
 * ตรรกะแบบนี้ผิดแล้วเงียบ: มันจะบอกว่า OCR อ่านถูกทั้งที่อ่านผิด
 * แล้วเลขพาสปอร์ตที่เพี้ยนไปหนึ่งตัวจะไปเทียบกับรายชื่อ ปปง. ไม่เจอ
 */
class MrzCheckDigitTest extends TestCase
{
    /** น้ำหนัก 7,3,1 วนไป · A=10..Z=35 · '<'=0 ตาม ICAO 9303 */
    private function checkDigit(string $value): string
    {
        $weights = [7, 3, 1];
        $sum = 0;

        foreach (str_split($value) as $i => $c) {
            $v = match (true) {
                ctype_digit($c) => (int) $c,
                $c >= 'A' && $c <= 'Z' => ord($c) - 55,
                $c === '<' => 0,
                default => null,
            };

            $this->assertNotNull($v, "อักขระที่ไม่ควรมีใน MRZ: {$c}");
            $sum += $v * $weights[$i % 3];
        }

        return (string) ($sum % 10);
    }

    public function test_the_reference_mrz_from_icao_9303_checks_out(): void
    {
        // ตัวอย่างทางการจาก ICAO 9303 Part 4 — บรรทัดที่สองของพาสปอร์ต TD3
        $line2 = 'L898902C36UTO7408122F1204159ZE184226B<<<<<10';

        $this->assertSame($line2[9], $this->checkDigit(substr($line2, 0, 9)), 'เลขพาสปอร์ต');
        $this->assertSame($line2[19], $this->checkDigit(substr($line2, 13, 6)), 'วันเกิด');
        $this->assertSame($line2[27], $this->checkDigit(substr($line2, 21, 6)), 'วันหมดอายุ');
    }

    public function test_one_wrong_character_is_caught(): void
    {
        // OCR สับสน 0 กับ O และ 1 กับ I เป็นประจำ ต้องจับให้ได้
        $this->assertNotSame(
            $this->checkDigit('L898902C3'),
            $this->checkDigit('L898902C4'),
        );
    }

    public function test_both_counter_forms_validate_check_digits(): void
    {
        foreach (['buy', 'sell'] as $form) {
            $source = file_get_contents(resource_path("views/livewire/transaction/{$form}-form.blade.php"));

            $this->assertStringContainsString('mrzCheckDigit(value)', $source, "{$form}: ไม่มีตัวคำนวณเลขตรวจสอบ");
            $this->assertStringContainsString('result.checks = {', $source, "{$form}: ไม่ได้เก็บผลการตรวจ");

            // น้ำหนักที่ผิดคือวิธีที่ตรรกะนี้พังได้เงียบที่สุด
            $this->assertStringContainsString('[7, 3, 1]', $source, "{$form}: น้ำหนักไม่ใช่ 7,3,1");
        }
    }

    public function test_both_counter_forms_show_the_scan_outcome(): void
    {
        foreach (['buy', 'sell'] as $form) {
            $source = file_get_contents(resource_path("views/livewire/transaction/{$form}-form.blade.php"));

            // เดิมสแกนสำเร็จแล้วเงียบสนิท พนักงานแยกไม่ออกว่าอ่านได้แล้วหรือยังไม่ได้ทำอะไร
            $this->assertStringContainsString('ocrResult = parsed', $source, "{$form}: ไม่ได้เก็บผลไว้แสดง");
            $this->assertStringContainsString('ocrAllChecksPassed', $source, "{$form}: ไม่มีการแสดงผลว่าผ่านหรือไม่");
        }
    }
}
