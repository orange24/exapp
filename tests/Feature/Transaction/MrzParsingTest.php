<?php

namespace Tests\Feature\Transaction;

use Tests\TestCase;

/**
 * parser MRZ อยู่ใน JS — เทสนี้รันด้วย node โดยดึงเมธอดตัวจริงออกมาจากไฟล์
 *
 * เคสทั้งหมดมาจากพาสปอร์ตจริงที่ถ่ายที่เคาน์เตอร์ ไม่ใช่ข้อมูลสมมติ
 * OCR เคยอ่านชื่อได้เป็น "LCLLLLLLLLLLLLLLLLLLL WATCHARA" และอ่านวันเกิดไม่ออกเลย
 */
class MrzParsingTest extends TestCase
{
    private const LINE1 = 'P<THAKITTIKUM<<WATCHARA<<<<<<<<<<<<<<<<<<<<<';
    private const LINE2 = 'AC27842836THA8306258M31103153500900234628<68';

    /** @return array<string, mixed> */
    private function parse(string $line1, string $line2): array
    {
        if (exec('command -v node') === '') {
            $this->markTestSkipped('ไม่มี node ในเครื่องนี้');
        }

        $script = <<<'JS'
        const fs = require('fs');
        const src = fs.readFileSync(process.argv[2], 'utf8');
        const grab = re => src.match(re)[0].replace(/,\s*$/, '');
        const o = eval('({' + [
          grab(/mrzCheckDigit\(value\) \{[\s\S]*?\n        \},/),
          grab(/mrzFieldOk\(value, expected\) \{[\s\S]*?\n        \},/),
          grab(/mrzDigitsOnly\(s\) \{[\s\S]*?\n        \},/),
          grab(/trimMrzNoise\(value\) \{[\s\S]*?\n        \},/),
          grab(/parseMRZ\(text\) \{[\s\S]*?\n        \},/),
          grab(/parseMrzNames\(lines, line2, nationality\) \{[\s\S]*?\n        \},/),
        ].join(',\n') + '})');
        process.stdout.write(JSON.stringify(o.parseMRZ(process.argv[3] + '\n' + process.argv[4])));
        JS;

        $file = tempnam(sys_get_temp_dir(), 'mrz') . '.js';
        file_put_contents($file, $script);

        $cmd = sprintf(
            'node %s %s %s %s',
            escapeshellarg($file),
            escapeshellarg(resource_path('views/livewire/transaction/buy-form.blade.php')),
            escapeshellarg($line1),
            escapeshellarg($line2),
        );

        $out = shell_exec($cmd);
        unlink($file);

        return json_decode((string) $out, true) ?? [];
    }

    public function test_a_clean_mrz_reads_every_field(): void
    {
        $r = $this->parse(self::LINE1, self::LINE2);

        $this->assertSame('AC2784283', $r['passportNo']);
        $this->assertSame('KITTIKUM', $r['lastName']);
        $this->assertSame('WATCHARA', $r['firstName']);
        $this->assertSame('1983-06-25', $r['dob']);
        $this->assertSame('2031-10-31', $r['expiry']);
        $this->assertSame('THA', $r['nationality']);

        foreach (['passportNo', 'dob', 'expiry'] as $field) {
            $this->assertTrue($r['checks'][$field], "เลขตรวจสอบของ {$field} ควรผ่าน");
        }
    }

    /**
     * OCR อ่าน '<' เป็นตัวอักษรที่หน้าตาใกล้เคียงเป็นประจำ
     * ของจริงที่เจอคือ C, L และ K ปนกันในใบเดียว
     */
    public function test_the_name_survives_the_filler_being_misread_as_a_letter(): void
    {
        foreach (['C', 'L', 'K'] as $misread) {
            $r = $this->parse(str_replace('<', $misread, self::LINE1), self::LINE2);

            $this->assertSame('KITTIKUM', $r['lastName'], "'<' อ่านเป็น '{$misread}'");

            // 'C' อยู่ใน WATCHARA ด้วย — การแทนทุกตัวจะได้ WAT HARA
            $this->assertSame('WATCHARA', $r['firstName'], "'<' อ่านเป็น '{$misread}'");
        }
    }

    /** ช่องวันเกิดเป็นตัวเลขล้วนตามมาตรฐาน ดัดตัวอักษรที่คล้ายกลับได้อย่างปลอดภัย */
    public function test_a_digit_misread_as_a_letter_is_recovered(): void
    {
        foreach (['83O6258' => 'O แทน 0', 'B306258' => 'B แทน 8'] as $broken => $label) {
            $r = $this->parse(self::LINE1, str_replace('8306258', $broken, self::LINE2));

            $this->assertSame('1983-06-25', $r['dob'], $label);
            $this->assertTrue($r['checks']['dob'], $label . ' — เลขตรวจสอบต้องยืนยันว่าดัดถูก');
        }
    }

    public function test_an_unrecoverable_name_is_left_empty_rather_than_filled_with_rubbish(): void
    {
        // ชื่อขยะที่ถูกเติมลงฟอร์มจะถูกบันทึกเป็นชื่อลูกค้าแล้วเอาไปเทียบรายชื่อ ปปง.
        // โดยไม่มีใครทันสังเกต — ปล่อยว่างให้พนักงานพิมพ์เองปลอดภัยกว่า
        $r = $this->parse('P<THAXXXXXXXXXX<<YYYYYYYYYY<<<<<<<<<<<<<<<<', self::LINE2);

        $this->assertSame('', $r['lastName']);
        $this->assertSame('', $r['firstName']);
    }

    public function test_the_name_survives_the_filler_being_misread_as_several_different_letters(): void
    {
        // ของจริงที่เคาน์เตอร์: OCR อ่าน '<' เป็น C, S และ L ปนกันในใบเดียว
        // ได้ชื่อออกมาเป็น "CCSCLC KITTIKUMCSWATCHARA" เพราะตัวคั่นระหว่าง
        // นามสกุลกับชื่อถูกอ่านเป็น "CS" ซึ่งไม่ใช่ตัวเดียวซ้ำกัน
        $pool = ['C', 'S', 'L'];
        $i = 0;
        $mixed = '';

        foreach (str_split(self::LINE1) as $ch) {
            $mixed .= $ch === '<' ? $pool[$i++ % 3] : $ch;
        }

        $r = $this->parse($mixed, self::LINE2);

        $this->assertSame('KITTIKUM', $r['lastName']);
        $this->assertSame('WATCHARA', $r['firstName']);
    }

    public function test_the_name_survives_the_first_filler_after_the_document_type(): void
    {
        // ตัวนี้เคยเป็นสาเหตุที่นามสกุลกับชื่อสลับที่ เพราะตัวหาจุดเริ่มชื่อ
        // ไปเจอ '<' ตัวแรกในหางของบรรทัดแทนที่จะเป็นตัวหลัง P
        $r = $this->parse(str_replace('P<THA', 'PCTHA', self::LINE1), self::LINE2);

        $this->assertSame('KITTIKUM', $r['lastName']);
        $this->assertSame('WATCHARA', $r['firstName']);
    }

    public function test_a_long_noisy_tail_does_not_bleed_into_the_given_name(): void
    {
        // หางที่ OCR อ่านเป็นตัวอักษรคนละตัวไปเรื่อย ๆ ไม่มีตัวไหนซ้ำพอจะเป็นรูปแบบ
        $noisy = preg_replace('/<+$/', 'CSKLCSKLCSKLCSKLCSKL', self::LINE1);

        $r = $this->parse($noisy, self::LINE2);

        $this->assertSame('KITTIKUM', $r['lastName']);
        $this->assertSame('WATCHARA', $r['firstName']);
    }
}
