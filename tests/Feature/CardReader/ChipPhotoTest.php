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
    /** สร้างไฟล์ JPEG 2000 จริงด้วยเครื่องมือชุดเดียวกับที่ใช้แปลง */
    private function makeJp2(): string
    {
        foreach (['opj_compress', 'opj_decompress'] as $tool) {
            if (trim((string) shell_exec("command -v {$tool}")) === '') {
                $this->markTestSkipped("ไม่มี {$tool} ในเครื่องนี้");
            }
        }

        $png = tempnam(sys_get_temp_dir(), 'chip') . '.png';
        $jp2 = $png . '.jp2';

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

    public function test_a_jpeg_from_the_chip_is_passed_through_untouched(): void
    {
        $jpeg = base64_encode('ไม่ใช่รูปจริง แต่ไม่ต้องแปลง');

        $out = app(ChipPhoto::class)->toBrowserReadable($jpeg, 'image/jpeg');

        $this->assertSame($jpeg, $out['data']);
        $this->assertSame('image/jpeg', $out['mime']);
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
}
