<?php

namespace Tests\Feature\CardReader;

use App\Services\CardReader\ChipPhoto;
use Tests\TestCase;

/**
 * DG2 ของพาสปอร์ตไทยเก็บรูปเป็น JPEG 2000 ซึ่งไม่มีเบราว์เซอร์ไหนแสดงได้
 * ถ้าส่งไปตรง ๆ หน้าเคาน์เตอร์จะได้กรอบว่างที่ดูเหมือนระบบพัง
 */
class ChipPhotoTest extends TestCase
{
    /**
     * สร้างไฟล์ JPEG 2000 จริงด้วยเครื่องมือชุดเดียวกับที่ใช้แปลง
     *
     * $ext เลือกได้สองแบบเพราะชิปส่งมาได้ทั้งสองแบบ — jp2 คือไฟล์แบบมีกล่องหุ้ม
     * ส่วน j2k คือ codestream ดิบ ๆ ไม่มีกล่อง
     */
    private function makeJp2(string $ext = 'jp2'): string
    {
        foreach (['opj_compress', 'opj_decompress'] as $tool) {
            if (trim((string) shell_exec("command -v {$tool}")) === '') {
                $this->markTestSkipped("ไม่มี {$tool} ในเครื่องนี้");
            }
        }

        $png = tempnam(sys_get_temp_dir(), 'chip') . '.png';
        $jp2 = $png . '.' . $ext;

        $img = imagecreatetruecolor(60, 80);
        imagefill($img, 0, 0, imagecolorallocate($img, 120, 130, 140));
        imagepng($img, $png);
        imagedestroy($img);

        shell_exec(sprintf('opj_compress -i %s -o %s 2>/dev/null', escapeshellarg($png), escapeshellarg($jp2)));

        $this->assertFileExists($jp2, 'สร้างไฟล์ JPEG 2000 ทดสอบไม่สำเร็จ');

        $raw = (string) file_get_contents($jp2);

        @unlink($png);
        @unlink($jp2);

        return base64_encode($raw);
    }

    /** รูป jpeg จริง ๆ ขนาดจิ๋ว ใช้แทนรูปจากชิปที่ไม่ต้องแปลง */
    private function makeJpeg(): string
    {
        $img = imagecreatetruecolor(8, 8);
        ob_start();
        imagejpeg($img);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        return base64_encode($jpeg);
    }

    public function test_a_jpeg_from_the_chip_is_passed_through_untouched(): void
    {
        $jpeg = $this->makeJpeg();

        $out = app(ChipPhoto::class)->toBrowserReadable($jpeg, 'image/jpeg');

        $this->assertSame($jpeg, $out['data'], 'ของที่เบราว์เซอร์แสดงได้อยู่แล้วไม่ต้องแตะ');
        $this->assertSame('image/jpeg', $out['mime']);
    }

    public function test_bytes_that_only_claim_to_be_a_jpeg_are_refused(): void
    {
        // ส่งต่อไปตรง ๆ จะได้กรอบรูปแตกที่หน้าเคาน์เตอร์ ซึ่งดูเหมือนระบบพัง
        // ไม่แสดงอะไรเลยพร้อมข้อความอธิบายดีกว่า
        $this->assertNull(
            app(ChipPhoto::class)->toBrowserReadable(base64_encode('ไม่ใช่รูปจริง'), 'image/jpeg')
        );
    }

    public function test_a_jpeg2000_photo_becomes_something_a_browser_can_show(): void
    {
        if (trim((string) shell_exec('command -v opj_decompress')) === '') {
            $this->markTestSkipped('ไม่มี opj_decompress ในเครื่องนี้');
        }

        $out = app(ChipPhoto::class)->toBrowserReadable($this->makeJp2(), 'image/jp2');

        $this->assertNotNull($out, 'แปลงไม่สำเร็จ');
        $this->assertSame('image/jpeg', $out['mime']);

        $info = getimagesizefromstring(base64_decode($out['data']));

        $this->assertNotFalse($info);
        $this->assertSame('image/jpeg', $info['mime']);
    }

    public function test_rubbish_does_not_crash_the_counter(): void
    {
        // ชิปที่อ่านมาไม่ครบหรือรูปแบบแปลก ๆ ต้องไม่ทำให้หน้าเคาน์เตอร์พัง
        $this->assertNull(app(ChipPhoto::class)->toBrowserReadable(base64_encode('ไม่ใช่รูป'), 'image/jp2'));
        $this->assertNull(app(ChipPhoto::class)->toBrowserReadable('', 'image/jp2'));
    }

    public function test_an_unknown_format_is_refused_rather_than_guessed(): void
    {
        $this->assertNull(app(ChipPhoto::class)->toBrowserReadable(base64_encode('x'), 'image/tiff'));
    }

    public function test_an_oversized_payload_is_refused(): void
    {
        // รูปจากชิปราว 10 KB อะไรที่ใหญ่กว่านี้มากคือผิดปกติ ไม่ควรเอาไปแปลง
        $huge = base64_encode(str_repeat('x', 3 * 1024 * 1024));

        $this->assertNull(app(ChipPhoto::class)->toBrowserReadable($huge, 'image/jp2'));
    }
    public function test_a_raw_codestream_from_the_chip_is_converted_too(): void
    {
        if (trim((string) shell_exec('command -v opj_decompress')) === '') {
            $this->markTestSkipped('ไม่มี opj_decompress ในเครื่องนี้');
        }

        /*
         * พาสปอร์ตหลายเล่มเก็บรูปเป็น codestream ดิบ (ขึ้นต้น FF4F FF51) ไม่ใช่
         * ไฟล์ .jp2 ที่มีกล่องหุ้ม แต่ gmrtd รายงาน mime เป็น image/jp2 ทั้งคู่
         * opj_decompress เตือนเรื่องนามสกุลแต่ยังถอดรหัสให้ เทสนี้ล็อกไว้ว่า
         * ทั้งสองแบบต้องได้รูปออกมา ไม่ใช่แค่แบบที่มีกล่องหุ้ม
         */
        $out = app(ChipPhoto::class)->toBrowserReadable($this->makeJp2('j2k'), 'image/jp2');

        $this->assertNotNull($out, 'codestream ดิบต้องแปลงได้');
        $this->assertSame('image/jpeg', $out['mime']);

        $info = getimagesizefromstring(base64_decode($out['data']));

        $this->assertNotFalse($info);
        $this->assertSame('image/jpeg', $info['mime']);
    }

    public function test_the_real_format_wins_over_what_the_chip_claims(): void
    {
        if (trim((string) shell_exec('command -v opj_decompress')) === '') {
            $this->markTestSkipped('ไม่มี opj_decompress ในเครื่องนี้');
        }

        // บางเล่มรายงาน mime มาว่าง หรือมาเป็นชื่อที่เราไม่รู้จัก การเชื่อ mime
        // อย่างเดียวทำให้รูปที่แปลงได้อยู่แล้วถูกทิ้ง — ดูไบต์แรกเอาดีกว่า
        $out = app(ChipPhoto::class)->toBrowserReadable($this->makeJp2(), '');

        $this->assertNotNull($out);
        $this->assertSame('image/jpeg', $out['mime']);
    }

    public function test_a_real_jpeg_is_recognised_even_when_the_mime_is_wrong(): void
    {
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($img);
        $jpeg = (string) ob_get_clean();
        imagedestroy($img);

        $out = app(ChipPhoto::class)->toBrowserReadable(base64_encode($jpeg), 'image/jp2');

        $this->assertNotNull($out);
        $this->assertSame('image/jpeg', $out['mime']);
        $this->assertSame(base64_encode($jpeg), $out['data'], 'jpeg ไม่ต้องแปลงซ้ำ');
    }
}