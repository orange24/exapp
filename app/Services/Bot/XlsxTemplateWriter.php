<?php

namespace App\Services\Bot;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * เขียนค่าลงเซลล์ของไฟล์ .xlsx ต้นแบบ โดยแตะเฉพาะเซลล์ที่สั่ง
 *
 * ไม่ใช้ PhpSpreadsheet เปิดแล้วบันทึกใหม่ เพราะมันสร้างไฟล์ขึ้นใหม่ทั้งไฟล์
 * จาก model ของตัวเอง — dropdown ที่เป็นสูตร (IF(...,CA,CB)) ชื่อที่ผูกกับ
 * ชีต (localSheetId) ชีตที่ซ่อนแบบ veryHidden และ comment อาจเพี้ยนหรือหาย
 * template ของแบงค์ชาติห้ามแก้สูตรและโครงสร้าง จึงแก้ XML ของเซลล์ตรง ๆ
 * ทุกอย่างที่ไม่ได้เขียนทับคงเดิมทุกไบต์
 *
 * เซลล์ที่เขียนคงสไตล์เดิม (attribute s) ไว้ จึงได้รูปแบบวันที่/ตัวเลข/เส้นขอบ
 * ตามที่ template กำหนด
 */
class XlsxTemplateWriter
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const NS_PKG_REL = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** @var array<string, string> ชื่อชีต → path ของ XML ในไฟล์ zip */
    private array $sheetPaths = [];

    /** @var array<string, DOMDocument> ชีตที่ถูกแก้ */
    private array $dirty = [];

    private ZipArchive $zip;

    public function __construct(private string $templatePath)
    {
        if (! is_file($templatePath)) {
            throw new RuntimeException("ไม่พบไฟล์ template: {$templatePath}");
        }
    }

    /**
     * @param  array<string, array<string, string|int|float|null>>  $cells  ชื่อชีต => [A1 => ค่า]
     */
    public function save(array $cells, string $outputPath): void
    {
        if (! copy($this->templatePath, $outputPath)) {
            throw new RuntimeException("คัดลอก template ไม่สำเร็จ: {$outputPath}");
        }

        $this->zip = new ZipArchive();
        if ($this->zip->open($outputPath) !== true) {
            throw new RuntimeException("เปิดไฟล์ xlsx ไม่ได้: {$outputPath}");
        }

        $this->dirty = [];
        $this->sheetPaths = $this->readSheetPaths();

        foreach ($cells as $sheetName => $values) {
            foreach ($values as $ref => $value) {
                $this->writeCell($sheetName, $ref, $value);
            }
        }

        foreach ($this->dirty as $path => $dom) {
            $this->zip->addFromString($path, $dom->saveXML());
        }

        $this->forceRecalculation();

        $this->zip->close();
    }

    /**
     * @return array<string, string>
     */
    private function readSheetPaths(): array
    {
        $workbook = $this->load('xl/workbook.xml');
        $rels = $this->load('xl/_rels/workbook.xml.rels');

        $targets = [];
        foreach ($rels->getElementsByTagNameNS(self::NS_PKG_REL, 'Relationship') as $rel) {
            $targets[$rel->getAttribute('Id')] = 'xl/' . ltrim($rel->getAttribute('Target'), '/');
        }

        $paths = [];
        foreach ($workbook->getElementsByTagNameNS(self::NS, 'sheet') as $sheet) {
            $paths[$sheet->getAttribute('name')] = $targets[$sheet->getAttributeNS(self::NS_REL, 'id')];
        }

        return $paths;
    }

    private function writeCell(string $sheetName, string $ref, string|int|float|null $value): void
    {
        $path = $this->sheetPaths[$sheetName] ?? throw new RuntimeException("template ไม่มีชีต {$sheetName}");
        $dom = $this->dirty[$path] ??= $this->load($path);

        $cell = $this->findOrCreateCell($dom, $ref);

        // ล้างค่าเดิม (รวมสูตรที่ template ใส่ไว้เป็นค่าตั้งต้นของช่องกรอก)
        // แต่คงสไตล์ไว้
        while ($cell->firstChild) {
            $cell->removeChild($cell->firstChild);
        }
        $cell->removeAttribute('t');

        if ($value === null || $value === '') {
            return;
        }

        if (is_int($value) || is_float($value)) {
            $cell->appendChild($dom->createElementNS(self::NS, 'v', $this->number($value)));

            return;
        }

        // inline string — ไม่ต้องไปแก้ sharedStrings.xml ซึ่งใช้ร่วมกันทั้งไฟล์
        $cell->setAttribute('t', 'inlineStr');
        $is = $dom->createElementNS(self::NS, 'is');
        $t = $dom->createElementNS(self::NS, 't');
        $t->appendChild($dom->createTextNode($value));
        if ($value !== trim($value)) {
            $t->setAttribute('xml:space', 'preserve');
        }
        $is->appendChild($t);
        $cell->appendChild($is);
    }

    private function findOrCreateCell(DOMDocument $dom, string $ref): DOMElement
    {
        [$col, $rowNo] = $this->splitRef($ref);

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('m', self::NS);

        $row = $xpath->query("//m:sheetData/m:row[@r='{$rowNo}']")->item(0);
        if (! $row instanceof DOMElement) {
            $row = $this->insertRow($dom, $xpath, $rowNo);
        }

        foreach ($row->childNodes as $existing) {
            if ($existing instanceof DOMElement && $existing->getAttribute('r') === $ref) {
                return $existing;
            }
        }

        // เซลล์ยังไม่มี — แทรกให้ถูกลำดับคอลัมน์ Excel ถือว่าไฟล์เสียถ้าลำดับผิด
        $cell = $dom->createElementNS(self::NS, 'c');
        $cell->setAttribute('r', $ref);
        $target = $this->columnIndex($col);
        foreach ($row->childNodes as $existing) {
            if ($existing instanceof DOMElement
                && $this->columnIndex($this->splitRef($existing->getAttribute('r'))[0]) > $target) {
                return $row->insertBefore($cell, $existing);
            }
        }

        return $row->appendChild($cell);
    }

    private function insertRow(DOMDocument $dom, DOMXPath $xpath, int $rowNo): DOMElement
    {
        $sheetData = $xpath->query('//m:sheetData')->item(0);
        $row = $dom->createElementNS(self::NS, 'row');
        $row->setAttribute('r', (string) $rowNo);

        foreach ($sheetData->childNodes as $existing) {
            if ($existing instanceof DOMElement && (int) $existing->getAttribute('r') > $rowNo) {
                return $sheetData->insertBefore($row, $existing);
            }
        }

        return $sheetData->appendChild($row);
    }

    /**
     * ให้ Excel คำนวณสูตรใหม่ทั้งไฟล์ตอนเปิด
     *
     * ค่าที่ cache ไว้ในเซลล์สูตรเป็นของ template เปล่า (เช่นยอดบาทว่าง)
     * และ calcChain.xml ยังชี้ไปที่เซลล์สูตรที่เราเขียนทับเป็นค่าไปแล้ว ถ้าไม่ลบ
     * Excel จะขึ้นว่าไฟล์เสียแล้วขอซ่อม — Excel สร้าง calcChain ใหม่เองได้
     */
    private function forceRecalculation(): void
    {
        $workbook = $this->load('xl/workbook.xml');
        $calcPr = $workbook->getElementsByTagNameNS(self::NS, 'calcPr')->item(0);
        if ($calcPr instanceof DOMElement) {
            $calcPr->setAttribute('fullCalcOnLoad', '1');
            $this->zip->addFromString('xl/workbook.xml', $workbook->saveXML());
        }

        if ($this->zip->locateName('xl/calcChain.xml') === false) {
            return;
        }

        $this->zip->deleteName('xl/calcChain.xml');

        $rels = $this->load('xl/_rels/workbook.xml.rels');
        foreach (iterator_to_array($rels->getElementsByTagNameNS(self::NS_PKG_REL, 'Relationship')) as $rel) {
            if (str_ends_with($rel->getAttribute('Target'), 'calcChain.xml')) {
                $rel->parentNode->removeChild($rel);
            }
        }
        $this->zip->addFromString('xl/_rels/workbook.xml.rels', $rels->saveXML());

        $types = $this->load('[Content_Types].xml');
        foreach (iterator_to_array($types->getElementsByTagName('Override')) as $override) {
            if ($override->getAttribute('PartName') === '/xl/calcChain.xml') {
                $override->parentNode->removeChild($override);
            }
        }
        $this->zip->addFromString('[Content_Types].xml', $types->saveXML());
    }

    private function load(string $path): DOMDocument
    {
        $xml = $this->zip->getFromName($path);
        if ($xml === false) {
            throw new RuntimeException("template ไม่มี {$path}");
        }

        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = true;
        $dom->loadXML($xml, LIBXML_PARSEHUGE);

        return $dom;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function splitRef(string $ref): array
    {
        preg_match('/^([A-Z]+)(\d+)$/', $ref, $m) || throw new RuntimeException("อ้างอิงเซลล์ไม่ถูกต้อง: {$ref}");

        return [$m[1], (int) $m[2]];
    }

    private function columnIndex(string $col): int
    {
        $n = 0;
        foreach (str_split($col) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n;
    }

    private function number(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        // ไม่ให้ PHP พ่นรูปแบบ 1.0E-5 ลงไฟล์
        return rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
    }
}
