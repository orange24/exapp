<?php

namespace Tests\Unit\Sanction;

use App\Services\Sanction\AmloHtmlParser;
use PHPUnit\Framework\TestCase;

class AmloHtmlParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../../fixtures/amlo/' . $name . '.html');
    }

    public function test_parses_list_page_into_rows_with_source_ref(): void
    {
        $rows = AmloHtmlParser::parseList($this->fixture('list_thailand'), 'thailandlist');

        $this->assertGreaterThan(300, count($rows));

        $first = $rows[0];
        $this->assertArrayHasKey('source_ref', $first);
        $this->assertArrayHasKey('row_hash', $first);
        $this->assertMatchesRegularExpression('/^\d+$/', $first['source_ref']);
    }

    public function test_list_rows_have_unique_source_refs(): void
    {
        $rows = AmloHtmlParser::parseList($this->fixture('list_un'), 'unlist');
        $refs = array_column($rows, 'source_ref');

        $this->assertSame(count($refs), count(array_unique($refs)));
    }

    public function test_row_hash_changes_when_row_content_changes(): void
    {
        $html = '<table><tr><th>No.</th></tr>'
            . '<tr><td>1</td><td>ก</td><td>A</td><td>XXXX1XXXX</td><td></td><td></td>'
            . '<td>Designated person</td>'
            . '<td><a href="https://aps.amlo.go.th/aps/public/thailandlist/detail/99">view</a></td></tr>'
            . '</table>';

        $rows = AmloHtmlParser::parseList($html, 'thailandlist');
        $this->assertCount(1, $rows);

        $changed = str_replace('XXXX1XXXX', 'XXXX2XXXX', $html);
        $rowsChanged = AmloHtmlParser::parseList($changed, 'thailandlist');

        $this->assertNotSame($rows[0]['row_hash'], $rowsChanged[0]['row_hash']);
        $this->assertSame($rows[0]['source_ref'], $rowsChanged[0]['source_ref']);
    }

    public function test_parses_thailand_detail_with_full_national_id(): void
    {
        $dto = AmloHtmlParser::parseDetail($this->fixture('detail_th_individual'), '17178');

        $this->assertSame('17178', $dto->sourceRef);
        $this->assertSame('AMRAN MING', $dto->nameEn);
        $this->assertStringContainsString('อำรัน มิง', $dto->nameTh);
        $this->assertSame('5960500028101', $dto->nationalId);
        $this->assertSame('18-12-1981', $dto->dateOfBirth);
        $this->assertSame('TH', $dto->nationality);
        $this->assertSame('001/2556', $dto->notificationNumber);
        $this->assertSame('Designated person', $dto->status);
        $this->assertNotNull($dto->asOfDate);
    }

    public function test_thailand_detail_produces_national_id_identifier(): void
    {
        $dto = AmloHtmlParser::parseDetail($this->fixture('detail_th_individual'), '17178');

        $nationalIds = array_values(array_filter(
            $dto->identifiers,
            static fn (array $i): bool => $i['type'] === 'national_id'
        ));

        $this->assertCount(1, $nationalIds);
        $this->assertSame('5960500028101', $nationalIds[0]['raw']);
    }

    public function test_thailand_detail_names_include_thai_and_english(): void
    {
        $dto = AmloHtmlParser::parseDetail($this->fixture('detail_th_individual'), '17178');

        $this->assertContains('AMRAN MING', $dto->names);

        $hasThai = false;
        foreach ($dto->names as $n) {
            if (preg_match('/\p{Thai}/u', $n) === 1) {
                $hasThai = true;
                break;
            }
        }
        $this->assertTrue($hasThai, 'ต้องมีชื่อไทยอยู่ในรายการชื่อ');
    }

    public function test_parses_un_detail_with_reference_number_and_multiple_passports(): void
    {
        $dto = AmloHtmlParser::parseDetail($this->fixture('detail_un_individual'), '17313');

        $this->assertSame('QDi.400', $dto->referenceNumber);
        $this->assertSame('Jordan', $dto->nationality);
        $this->assertSame('ISIL & Al-Qaida', $dto->groupName);

        $passports = array_values(array_filter(
            $dto->identifiers,
            static fn (array $i): bool => $i['type'] === 'passport'
        ));

        $this->assertCount(2, $passports);
    }

    public function test_un_display_name_has_no_leftover_sequence_numbers(): void
    {
        // name_en คือค่าที่แถบเตือนหน้าเคาน์เตอร์ คิวตรวจสอบ และ noti เอาไปแสดง
        // ถ้าเลข 2. 3. 4. ค้างอยู่ คนอนุมัติจะอ่านเทียบกับพาสปอร์ตในมือได้ยาก
        $dto = AmloHtmlParser::parseDetail($this->fixture('detail_un_individual'), '17313');

        $this->assertSame('IYAD NAZMI SALIH KHALIL', $dto->nameEn);
        $this->assertDoesNotMatchRegularExpression('/\d+\./', (string) $dto->nameEn);
    }

    public function test_un_detail_splits_numbered_name_parts(): void
    {
        $dto = AmloHtmlParser::parseDetail($this->fixture('detail_un_individual'), '17313');

        $this->assertContains('IYAD', $dto->names);
        $this->assertContains('NAZMI', $dto->names);
        $this->assertContains('SALIH', $dto->names);
        $this->assertContains('KHALIL', $dto->names);
    }

    public function test_na_values_become_null_not_the_string_na(): void
    {
        $dto = AmloHtmlParser::parseDetail($this->fixture('detail_sparse'), '1');

        $this->assertNotSame('na', $dto->nationalId);
        $this->assertNotSame('na', $dto->dateOfBirth);
        $this->assertNotSame('na', $dto->aka);
    }

    public function test_sparse_detail_still_yields_at_least_one_name(): void
    {
        $dto = AmloHtmlParser::parseDetail($this->fixture('detail_sparse'), '1');

        $this->assertNotEmpty($dto->names);
    }

    public function test_parse_detail_throws_when_required_label_missing(): void
    {
        $this->expectException(\RuntimeException::class);

        AmloHtmlParser::parseDetail('<html><body>no table here</body></html>', '1');
    }
}
