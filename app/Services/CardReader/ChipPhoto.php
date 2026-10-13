<?php

namespace App\Services\CardReader;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

/**
 * แปลงรูปจากชิปพาสปอร์ตให้เบราว์เซอร์แสดงได้
 *
 * DG2 เก็บรูปเป็น JPEG หรือ JPEG 2000 แล้วแต่ประเทศ — พาสปอร์ตไทยใช้ JPEG 2000
 * ซึ่งไม่มีเบราว์เซอร์ไหนแสดงได้ ถ้าส่งไปตรง ๆ จะได้กรอบว่างที่ดูเหมือนระบบพัง
 *
 * แปลงที่เซิร์ฟเวอร์ไม่ใช่ที่ agent เพราะ agent เป็นไฟล์เดียวจบ การไปเรียก
 * โปรแกรมข้างนอกทำให้ต้องติดตั้งอะไรเพิ่มที่สาขา 20 แห่ง
 */
class ChipPhoto
{
    /** รูปจากชิปเล็กมาก (ราว 10 KB) อะไรที่ใหญ่กว่านี้มากคือผิดปกติ */
    private const MAX_INPUT_BYTES = 2 * 1024 * 1024;

    /**
     * @return array{data: string, mime: string}|null  null เมื่อแปลงไม่ได้
     */
    public function toBrowserReadable(string $base64, string $mime): ?array
    {
        $raw = base64_decode($base64, true);

        if ($raw === false || $raw === '' || strlen($raw) > self::MAX_INPUT_BYTES) {
            return null;
        }

        /*
         * ดูไบต์แรกเอา ไม่เชื่อ mime ที่ชิปแจ้งมา
         *
         * gmrtd เดารูปแบบจากไบต์แรกเหมือนกัน แต่ถ้ามันเดาไม่ออกจะคืนค่าว่าง
         * มาให้โดยไม่บอกว่าเดาไม่ออก การกรองด้วย mime อย่างเดียวจึงทิ้งรูปที่
         * แปลงได้อยู่แล้วไปฟรี ๆ ไบต์แรกเป็นสิ่งที่โกหกไม่ได้
         */
        $format = $this->sniff($raw);

        if ($format === 'jpeg' || $format === 'png') {
            return ['data' => $base64, 'mime' => 'image/' . $format];
        }

        if ($format !== 'jp2') {
            return null;
        }

        return $this->convert($raw);
    }

    /** @return 'jpeg'|'png'|'jp2'|null */
    private function sniff(string $raw): ?string
    {
        // JPEG ปกติ: FF D8 FF
        if (str_starts_with($raw, "\xFF\xD8\xFF")) {
            return 'jpeg';
        }

        if (str_starts_with($raw, "\x89PNG\r\n\x1A\n")) {
            return 'png';
        }

        // JPEG 2000 มาได้สองแบบ — มีกล่องหุ้ม กับ codestream ดิบ
        if (str_starts_with($raw, "\x00\x00\x00\x0CjP  \r\n")
            || str_starts_with($raw, "\xFF\x4F\xFF\x51")) {
            return 'jp2';
        }

        return null;
    }

    /** @return array{data: string, mime: string}|null */
    private function convert(string $raw): ?array
    {
        $dir = sys_get_temp_dir();
        $in = tempnam($dir, 'chip') . '.jp2';
        $out = $in . '.png';

        try {
            file_put_contents($in, $raw);

            $process = new Process(['opj_decompress', '-i', $in, '-o', $out]);
            $process->setTimeout(10);
            $process->run();

            if (! $process->isSuccessful() || ! is_file($out)) {
                Log::warning('แปลงรูปจากชิปไม่สำเร็จ', ['stderr' => $process->getErrorOutput()]);

                return null;
            }

            $png = imagecreatefrompng($out);

            if ($png === false) {
                return null;
            }

            ob_start();
            imagejpeg($png, null, 85);
            $jpeg = (string) ob_get_clean();
            imagedestroy($png);

            return ['data' => base64_encode($jpeg), 'mime' => 'image/jpeg'];
        } catch (\Throwable $e) {
            Log::warning('แปลงรูปจากชิปล้มเหลว: ' . $e->getMessage());

            return null;
        } finally {
            // ไฟล์ชั่วคราวมีรูปหน้าของประชาชน ลบทันทีไม่ว่าจะสำเร็จหรือไม่
            foreach ([$in, $out] as $path) {
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }
}
