<?php

namespace App\Services\Bot;

use App\Models\Branch;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

/**
 * ข้อมูลซื้อ/ขายเงินตราต่างประเทศรายเดือนของสาขาหนึ่ง สำหรับส่งแบงค์ชาติ
 * ลงในไฟล์ template ทางการ (resources/templates/bot/emc-template.xlsx)
 *
 * แยกเป็นสองไฟล์ตามที่แบงค์ชาติให้ส่ง — คนไทย (TH) กับชาวต่างชาติ (FR)
 * รายการที่ระบุตัวลูกค้าไม่ได้ไม่ถูกใส่ในไฟล์ แต่รายงานกลับใน skipped()
 * ให้หน้าจอเตือน เพื่อให้ไปแก้ข้อมูลแล้วออกไฟล์ใหม่ ไม่ใช่ส่งไฟล์ที่แบงค์ชาติตีกลับ
 */
class BotSubmission
{
    public const THAI = 'TH';
    public const FOREIGN = 'FR';

    private const TEMPLATE = 'templates/bot/emc-template.xlsx';
    private const DIMENSION = 'templates/bot/emc-dimension.php';

    /** แถวข้อมูลใน template คือ 9-500 */
    private const FIRST_ROW = 9;
    public const ROWS_PER_FILE = 492;

    /** ค่าใน dropdown ของ template ต้องสะกดตรงตัวอักษร */
    private const TYPE_THAI = 'คนไทย';
    private const TYPE_THAI_CORP = 'นิติบุคคลไทย';
    private const TYPE_FOREIGN = 'ชาวต่างชาติ';

    private const ID_CARD = '324001 : เลขประจำตัวประชาชน';
    private const ID_PASSPORT = '324002 : เลขที่หนังสือเดินทาง';
    private const ID_CORP = '324004 : เลขที่จดทะเบียนนิติบุคคล';
    private const ID_OTHER = '324012 : รหัสอื่นๆ';

    private const THAI_MONTHS = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
        5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
        9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม',
    ];

    /** @var array{TH: array{buy: list<array>, sell: list<array>}, FR: array{buy: list<array>, sell: list<array>}} */
    private array $rows = [
        self::THAI => ['buy' => [], 'sell' => []],
        self::FOREIGN => ['buy' => [], 'sell' => []],
    ];

    /** @var list<array{trns_no: string, date: string, name: string, reason: string}> */
    private array $skipped = [];

    private Carbon $start;
    private Carbon $end;

    public function __construct(private Branch $branch, int $year, int $month)
    {
        $this->start = Carbon::create($year, $month, 1)->startOfDay();
        $this->end = $this->start->copy()->endOfMonth();

        $this->collect();
    }

    /**
     * @return array{buy: list<array>, sell: list<array>}
     */
    public function rows(string $group): array
    {
        return $this->rows[$group];
    }

    /**
     * @return list<array{trns_no: string, date: string, name: string, reason: string}>
     */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /** ชื่อไฟล์ตามรูปแบบที่แบงค์ชาติใช้ เช่น MMCSMC225590091_20260131_EMC_TH */
    public function baseName(string $group): string
    {
        $license = preg_replace('/\s+/', '', (string) $this->licenseNo());

        return "MMCS{$license}_{$this->end->format('Ymd')}_EMC_{$group}";
    }

    /**
     * สร้างไฟล์สำหรับดาวน์โหลด — ไฟล์ .xlsx เดียว หรือ .zip ถ้ารายการเกิน
     * จำนวนแถวที่ template รับได้ต่อไฟล์
     *
     * @return array{path: string, name: string}
     */
    public function export(string $group, string $workDir): array
    {
        $buy = array_chunk($this->rows[$group]['buy'], self::ROWS_PER_FILE);
        $sell = array_chunk($this->rows[$group]['sell'], self::ROWS_PER_FILE);
        $parts = max(count($buy), count($sell), 1);

        $writer = new XlsxTemplateWriter(resource_path(self::TEMPLATE));
        $files = [];

        for ($i = 0; $i < $parts; $i++) {
            $name = $this->baseName($group) . ($parts > 1 ? '_' . ($i + 1) : '') . '.xlsx';
            $path = $workDir . '/' . $name;

            $writer->save([
                'Provider Info' => $this->providerInfo(),
                'Buy FX' => $this->sheetCells($buy[$i] ?? []),
                'Sell FX' => $this->sheetCells($sell[$i] ?? []),
            ], $path);

            $files[$name] = $path;
        }

        if ($parts === 1) {
            return ['path' => reset($files), 'name' => array_key_first($files)];
        }

        $zipName = $this->baseName($group) . '.zip';
        $zipPath = $workDir . '/' . $zipName;
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('สร้างไฟล์ zip ไม่สำเร็จ');
        }
        foreach ($files as $name => $path) {
            $zip->addFile($path, $name);
        }
        $zip->close();

        return ['path' => $zipPath, 'name' => $zipName];
    }

    /**
     * ช่องสีขาวของชีต Provider Info — ช่องวันที่ชุดข้อมูล (B9) เป็นสูตร
     * คำนวณจากเดือน/ปีเอง ไม่ต้องใส่
     *
     * @return array<string, string|int>
     */
    private function providerInfo(): array
    {
        $company = $this->branch->company;

        return [
            'B2' => (string) ($company?->tax_id ?: $this->branch->tax_id),
            'B3' => (string) ($company?->company_name ?: $this->branch->company_name),
            'B4' => (string) $this->licenseNo(),
            'B5' => (string) $this->branch->branch_name,
            'B6' => (string) ($this->branch->address ?: $company?->address),
            'B7' => self::THAI_MONTHS[$this->start->month],
            'B8' => $this->start->year,
        ];
    }

    private function licenseNo(): ?string
    {
        return $this->branch->company?->license_no ?: $this->branch->license_no;
    }

    /**
     * เขียนเฉพาะช่องที่ต้องกรอก ช่องอื่น (วัตถุประสงค์ จุดซื้อ สื่อที่จ่าย
     * จำนวนเงินบาท ฯลฯ) เป็นสูตรของ template ที่คำนวณจากประเภทลูกค้าเอง
     *
     * ประเภทรหัสลูกค้า (D) และสัญชาติของชาวต่างชาติ (F) ใน template มีสูตร
     * ตั้งต้นที่ให้ค่าว่างสำหรับลูกค้ากลุ่มนี้ แล้วให้เลือกจาก dropdown ทับ —
     * การเขียนค่าทับจึงเป็นวิธีใช้ template ตามที่ออกแบบไว้ ส่วนสัญชาติคนไทย
     * สูตรใส่ TH ให้เองจึงไม่แตะ
     *
     * @param  list<array>  $rows
     * @return array<string, string|int|float>
     */
    private function sheetCells(array $rows): array
    {
        $cells = [];
        $excelEpoch = Carbon::create(1899, 12, 30);

        foreach ($rows as $i => $row) {
            $r = self::FIRST_ROW + $i;

            $cells["A{$r}"] = (int) $excelEpoch->diffInDays($row['date']);
            $cells["B{$r}"] = $row['customer_type'];
            $cells["C{$r}"] = $row['name'];
            $cells["D{$r}"] = $row['id_type'];
            $cells["E{$r}"] = $row['id_number'];
            if ($row['country'] !== null) {
                $cells["F{$r}"] = $row['country'];
            }
            $cells["K{$r}"] = $row['currency'];
            $cells["M{$r}"] = $row['rate'];
            $cells["N{$r}"] = $row['fc_amount'];
        }

        return $cells;
    }

    private function collect(): void
    {
        $dimension = require resource_path(self::DIMENSION);
        $countries = new CountryCode($dimension['countries']);
        $currencies = $dimension['currencies'];

        $lines = DB::table('transactions_master as m')
            ->join('transactions_detail as d', 'd.transaction_id', '=', 'm.id')
            ->join('counters as c', 'c.id', '=', 'm.counter_id')
            ->leftJoin('customers as cu', 'cu.id', '=', 'm.customer_id')
            ->where('c.branch_id', $this->branch->id)
            ->where('m.flag_cancel', '!=', 'Y')
            ->whereBetween('m.trns_datetime', [$this->start, $this->end])
            ->orderBy('m.trns_datetime')->orderBy('m.id')->orderBy('d.id')
            ->get([
                'm.trns_no', 'm.trns_type', 'm.trns_datetime', 'm.cust_name', 'm.customer_id',
                'cu.id as cust_id', 'cu.type as cust_kind', 'cu.id_type', 'cu.id_number', 'cu.nationality',
                'cu.name_en', 'cu.name_th', 'cu.first_name', 'cu.last_name',
                'd.currency_code', 'd.unit_price', 'd.amount', 'd.total',
            ]);

        foreach ($lines as $line) {
            $date = Carbon::parse($line->trns_datetime);
            $isBuy = $line->trns_type === 'BUYING';
            $name = $this->customerName($line);

            $skip = fn (string $reason) => $this->skip($line->trns_no, $date, $name, $reason);

            if ($line->cust_id === null) {
                $skip('ไม่ได้ผูกข้อมูลลูกค้า');
                continue;
            }
            if (trim((string) $line->id_number) === '') {
                $skip('ไม่มีเลขบัตรประชาชน/หนังสือเดินทาง');
                continue;
            }

            $currency = strtoupper(trim((string) $line->currency_code));
            if (! in_array($currency, $currencies, true)) {
                $skip("สกุลเงิน {$currency} ไม่อยู่ในรายการของแบงค์ชาติ");
                continue;
            }

            $customer = $this->classify($line, $countries, $isBuy);
            if (is_string($customer)) {
                $skip($customer);
                continue;
            }

            // transactions_detail สลับความหมายคอลัมน์ตามประเภทบิล —
            // BUYING: amount = เงินต่างประเทศ, total = บาท / SELLING กลับกัน
            $fc = (float) ($isBuy ? $line->amount : $line->total);
            $thb = (float) ($isBuy ? $line->total : $line->amount);

            $this->rows[$customer['group']][$isBuy ? 'buy' : 'sell'][] = [
                'trns_no' => $line->trns_no,
                'date' => $date->copy()->startOfDay(),
                'customer_type' => $customer['type'],
                'name' => $name,
                'id_type' => $customer['id_type'],
                'id_number' => trim((string) $line->id_number),
                'country' => $customer['country'],
                'currency' => $currency,
                'rate' => (float) $line->unit_price,
                'fc_amount' => $fc,
                'thb_amount' => $thb,
            ];
        }
    }

    /**
     * @return array{group: string, type: string, id_type: string, country: ?string}|string
     *         ข้อความเหตุผลถ้าจัดกลุ่มไม่ได้
     */
    private function classify(object $line, CountryCode $countries, bool $isBuy): array|string
    {
        if ($line->cust_kind === 'corporate') {
            // แบบฟอร์มขาย (Customer_Type_Sell) ไม่มีประเภทนิติบุคคลไทยให้เลือก
            if (! $isBuy) {
                return 'แบบฟอร์มขายของแบงค์ชาติไม่มีประเภทนิติบุคคลไทย';
            }

            return ['group' => self::THAI, 'type' => self::TYPE_THAI_CORP, 'id_type' => self::ID_CORP, 'country' => null];
        }

        $country = $countries->toAlpha2($line->nationality);

        // ไม่มีสัญชาติแต่ใช้บัตรประชาชน — บัตรประชาชนไทยออกให้คนไทยเท่านั้น
        if ($country === null && $line->id_type === 'national_id') {
            $country = 'TH';
        }

        if ($country === null) {
            $raw = trim((string) $line->nationality);

            return $raw === '' ? 'ไม่ได้ระบุสัญชาติ' : "อ่านสัญชาติ \"{$raw}\" ไม่ออก";
        }

        if ($country === 'TH') {
            $idType = match ($line->id_type) {
                'national_id' => self::ID_CARD,
                'passport' => self::ID_PASSPORT,
                default => self::ID_OTHER,
            };

            return ['group' => self::THAI, 'type' => self::TYPE_THAI, 'id_type' => $idType, 'country' => null];
        }

        return [
            'group' => self::FOREIGN,
            'type' => self::TYPE_FOREIGN,
            'id_type' => $line->id_type === 'passport' ? self::ID_PASSPORT : self::ID_OTHER,
            'country' => $country,
        ];
    }

    private function customerName(object $line): string
    {
        $full = trim(trim((string) $line->first_name) . ' ' . trim((string) $line->last_name));

        foreach ([$line->cust_name, $line->name_en, $line->name_th, $full] as $candidate) {
            if (trim((string) $candidate) !== '') {
                return trim((string) $candidate);
            }
        }

        return '';
    }

    private function skip(string $trnsNo, Carbon $date, string $name, string $reason): void
    {
        // บิลหลายสกุลเงินถูกข้ามด้วยเหตุผลเดียวกัน แสดงครั้งเดียวพอ
        foreach ($this->skipped as $s) {
            if ($s['trns_no'] === $trnsNo && $s['reason'] === $reason) {
                return;
            }
        }

        $this->skipped[] = ['trns_no' => $trnsNo, 'date' => $date->format('d/m/Y'), 'name' => $name, 'reason' => $reason];
    }
}
