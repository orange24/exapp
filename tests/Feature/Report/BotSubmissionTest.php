<?php

namespace Tests\Feature\Report;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Counter;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use App\Services\Bot\BotSubmission;
use App\Services\Bot\CountryCode;
use Database\Seeders\BotSubmissionMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ส่งข้อมูลให้แบงค์ชาติ — ใส่รายการลงไฟล์ template ทางการ แยกไฟล์คนไทย/ต่างชาติ
 */
class BotSubmissionTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Company $company;
    private int $seq = 0;
    private string $workDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->seed(BotSubmissionMenuSeeder::class);

        $this->company = Company::create([
            'company_code' => 'YESSOUTH',
            'company_name' => 'บริษัท เยส เซาเทิร์น เอ็กซ์เชนจ์ จำกัด',
            'tax_id' => '0835559011285',
            'license_no' => 'MC225590091',
            'address' => '99/28 หมู่ 1 ต.กะทู้',
            'is_active' => true,
        ]);
        $this->branch->update(['company_id' => $this->company->id, 'address' => 'ที่อยู่สาขา HQ']);

        $this->workDir = storage_path('framework/testing/bot-' . uniqid());
        File::ensureDirectoryExists($this->workDir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->workDir);
        parent::tearDown();
    }

    private function customer(array $attrs): int
    {
        return DB::table('customers')->insertGetId(array_merge([
            'type' => 'individual',
            'id_type' => 'passport',
            'id_number' => 'P' . (++$this->seq),
            'name_en' => 'Customer ' . $this->seq,
            'nationality' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    /**
     * @param  array<int, array{0: string, 1: float, 2: float, 3: float}>  $lines  [ccy, rate, amount, total]
     */
    private function bill(string $type, ?int $customerId, string $when, array $lines, array $master = [], ?Counter $counter = null): string
    {
        $counter ??= $this->counter;
        $no = ($type === 'BUYING' ? 'B' : 'S') . str_pad((string) (++$this->seq), 6, '0', STR_PAD_LEFT);

        $id = DB::table('transactions_master')->insertGetId(array_merge([
            'trns_no' => $no,
            'trns_type' => $type,
            'counter_id' => $counter->id,
            'counter_name' => $counter->counter_name,
            'customer_id' => $customerId,
            'cust_name' => null,
            'flag_cancel' => 'N',
            'trns_datetime' => $when,
            'created_at' => now(),
            'updated_at' => now(),
        ], $master));

        foreach ($lines as [$ccy, $rate, $amount, $total]) {
            DB::table('transactions_detail')->insert([
                'transaction_id' => $id,
                'currency_code' => $ccy,
                'unit_price' => $rate,
                'amount' => $amount,
                'total' => $total,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $no;
    }

    private function submission(int $month = 1): BotSubmission
    {
        return new BotSubmission($this->branch->fresh(), 2026, $month);
    }

    /** inline string ถูกอ่านกลับมาเป็น RichText — เทียบเป็นข้อความ */
    private function text(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, string $ref): string
    {
        return (string) $sheet->getCell($ref)->getValue();
    }

    // ── การจัดกลุ่ม ─────────────────────────────────────────

    public function test_thai_and_foreign_customers_go_to_separate_files(): void
    {
        $thai = $this->customer(['id_type' => 'national_id', 'id_number' => '1100100100100', 'nationality' => 'THA']);
        $sg = $this->customer(['nationality' => 'Singapore']);
        $de = $this->customer(['nationality' => 'DEU']);

        $this->bill('BUYING', $thai, '2026-01-05 10:00:00', [['USD', 33.5, 100, 3350]]);
        $this->bill('BUYING', $sg, '2026-01-06 10:00:00', [['SGD', 25, 200, 5000]]);
        $this->bill('SELLING', $de, '2026-01-07 10:00:00', [['EUR', 38, 3800, 100]]);

        $s = $this->submission();

        $this->assertCount(1, $s->rows('TH')['buy']);
        $this->assertSame('คนไทย', $s->rows('TH')['buy'][0]['customer_type']);
        $this->assertSame('324001 : เลขประจำตัวประชาชน', $s->rows('TH')['buy'][0]['id_type']);
        $this->assertNull($s->rows('TH')['buy'][0]['country'], 'สัญชาติคนไทยปล่อยให้สูตรของ template ใส่ TH เอง');

        $this->assertSame(['SG'], array_column($s->rows('FR')['buy'], 'country'));
        $this->assertSame('ชาวต่างชาติ', $s->rows('FR')['buy'][0]['customer_type']);
        $this->assertSame('324002 : เลขที่หนังสือเดินทาง', $s->rows('FR')['buy'][0]['id_type']);
        $this->assertSame(['DE'], array_column($s->rows('FR')['sell'], 'country'));
    }

    public function test_a_thai_with_a_passport_is_still_thai(): void
    {
        $id = $this->customer(['id_type' => 'passport', 'nationality' => 'ไทย']);
        $this->bill('BUYING', $id, '2026-01-05 10:00:00', [['USD', 33.5, 100, 3350]]);

        $row = $this->submission()->rows('TH')['buy'][0];

        $this->assertSame('คนไทย', $row['customer_type']);
        $this->assertSame('324002 : เลขที่หนังสือเดินทาง', $row['id_type']);
    }

    public function test_selling_reads_the_foreign_amount_from_the_other_column(): void
    {
        // transactions_detail สลับความหมายคอลัมน์ — SELLING: amount = บาท, total = เงินต่างประเทศ
        $id = $this->customer(['nationality' => 'USA']);
        $this->bill('SELLING', $id, '2026-01-05 10:00:00', [['USD', 34, 3400, 100]]);

        $row = $this->submission()->rows('FR')['sell'][0];

        $this->assertSame(100.0, $row['fc_amount']);
        $this->assertSame(3400.0, $row['thb_amount']);
    }

    public function test_a_bill_with_several_currencies_becomes_several_rows(): void
    {
        $id = $this->customer(['nationality' => 'GBR']);
        $this->bill('BUYING', $id, '2026-01-05 10:00:00', [['USD', 33, 100, 3300], ['GBP', 44, 50, 2200]]);

        $this->assertSame(['USD', 'GBP'], array_column($this->submission()->rows('FR')['buy'], 'currency'));
    }

    public function test_bills_that_cannot_be_reported_are_listed_not_written(): void
    {
        $this->bill('BUYING', null, '2026-01-05 10:00:00', [['USD', 33, 100, 3300]], ['cust_name' => 'VIP']);
        $this->bill('BUYING', $this->customer(['nationality' => '']), '2026-01-05 11:00:00', [['USD', 33, 1, 33]]);
        $this->bill('BUYING', $this->customer(['nationality' => 'Atlantis']), '2026-01-05 12:00:00', [['USD', 33, 1, 33]]);
        $this->bill('BUYING', $this->customer(['nationality' => 'JPN', 'id_number' => '']), '2026-01-05 13:00:00', [['USD', 33, 1, 33]]);

        $s = $this->submission();

        $this->assertSame([], $s->rows('TH')['buy']);
        $this->assertSame([], $s->rows('FR')['buy']);
        $this->assertSame([
            'ไม่ได้ผูกข้อมูลลูกค้า',
            'ไม่ได้ระบุสัญชาติ',
            'อ่านสัญชาติ "Atlantis" ไม่ออก',
            'ไม่มีเลขบัตรประชาชน/หนังสือเดินทาง',
        ], array_column($s->skipped(), 'reason'));
        $this->assertSame('VIP', $s->skipped()[0]['name']);
    }

    public function test_only_this_branch_this_month_and_not_cancelled(): void
    {
        $id = $this->customer(['nationality' => 'FRA']);
        $other = Branch::create(['company_id' => $this->company->id, 'branch_code' => 'X', 'branch_name' => 'อื่น', 'is_active' => true]);
        $otherCounter = Counter::create(['counter_code' => 'X-C1', 'counter_name' => 'X', 'branch_id' => $other->id, 'is_active' => true]);

        $keep = $this->bill('BUYING', $id, '2026-01-31 23:59:00', [['EUR', 38, 1, 38]]);
        $this->bill('BUYING', $id, '2026-02-01 00:00:00', [['EUR', 38, 1, 38]]);
        $this->bill('BUYING', $id, '2026-01-10 10:00:00', [['EUR', 38, 1, 38]], ['flag_cancel' => 'Y']);
        $this->bill('BUYING', $id, '2026-01-10 10:00:00', [['EUR', 38, 1, 38]], [], $otherCounter);

        $this->assertSame([$keep], array_column($this->submission()->rows('FR')['buy'], 'trns_no'));
    }

    // ── ไฟล์ ───────────────────────────────────────────────

    public function test_the_file_is_the_template_filled_in_with_formulas_kept(): void
    {
        $id = $this->customer(['nationality' => 'SGP', 'id_number' => 'K1234567']);
        $this->bill('BUYING', $id, '2026-01-15 10:00:00', [['SGD', 25.5, 200, 5100]], ['cust_name' => 'TAN AH KOW']);

        $file = $this->submission()->export('FR', $this->workDir);

        $this->assertSame('MMCSMC225590091_20260131_EMC_FR.xlsx', $file['name']);

        $book = IOFactory::load($file['path']);
        $info = $book->getSheetByName('Provider Info');
        $this->assertSame('0835559011285', $this->text($info, 'B2'));
        $this->assertSame('MC225590091', $this->text($info, 'B4'));
        $this->assertSame('HQ Bangkok', $this->text($info, 'B5'));
        $this->assertSame('ที่อยู่สาขา HQ', $this->text($info, 'B6'));
        $this->assertSame('มกราคม', $this->text($info, 'B7'));
        $this->assertEquals(2026, $this->text($info, 'B8'));
        $this->assertStringStartsWith('=DATE(', $this->text($info, 'B9'), 'วันที่ชุดข้อมูลยังเป็นสูตรของ template');

        $buy = $book->getSheetByName('Buy FX');
        $this->assertEquals(46037, $this->text($buy, 'A9'), '15/01/2026 ใน Excel serial');
        $this->assertSame('ชาวต่างชาติ', $this->text($buy, 'B9'));
        $this->assertSame('TAN AH KOW', $this->text($buy, 'C9'));
        $this->assertSame('K1234567', $this->text($buy, 'E9'));
        $this->assertSame('SG', $this->text($buy, 'F9'));
        $this->assertSame('SGD', $this->text($buy, 'K9'));
        $this->assertEquals(25.5, $this->text($buy, 'M9'));
        $this->assertEquals(200, $this->text($buy, 'N9'));

        // ช่องที่ template คำนวณเอง ต้องยังเป็นสูตรและคำนวณจากค่าที่ใส่ได้
        $this->assertStringStartsWith('=', $this->text($buy, 'Q9'));
        $this->assertEquals(5100, $buy->getCell('Q9')->getCalculatedValue());
        $this->assertSame('เดินทาง/ท่องเที่ยว', $buy->getCell('H9')->getCalculatedValue());
        $this->assertSame('0753600001 : เงินสด', $buy->getCell('J9')->getCalculatedValue());

        // dropdown และชีตอ้างอิงที่ซ่อนอยู่ยังครบ
        $this->assertCount(15, $buy->getDataValidationCollection());
        $this->assertSame('veryHidden', $book->getSheetByName('Dimension')->getSheetState());
    }

    public function test_too_many_rows_for_one_template_are_split_into_a_zip(): void
    {
        $id = $this->customer(['nationality' => 'JPN']);
        $lines = array_fill(0, BotSubmission::ROWS_PER_FILE + 1, ['JPY', 0.22, 1000, 220]);
        $this->bill('BUYING', $id, '2026-01-15 10:00:00', $lines);

        $file = $this->submission()->export('FR', $this->workDir);

        $this->assertSame('MMCSMC225590091_20260131_EMC_FR.zip', $file['name']);
        $zip = new \ZipArchive();
        $zip->open($file['path']);
        $this->assertSame(2, $zip->numFiles);
        $this->assertNotFalse($zip->locateName('MMCSMC225590091_20260131_EMC_FR_2.xlsx'));
        $zip->close();
    }

    // ── หน้าจอและสิทธิ์ ──────────────────────────────────────

    public function test_the_page_previews_the_chosen_group_and_lists_skipped_bills(): void
    {
        $this->bill('BUYING', $this->customer(['nationality' => 'THA', 'name_en' => 'สมชาย']), '2026-01-05 10:00:00', [['USD', 33, 1, 33]]);
        $this->bill('BUYING', null, '2026-01-06 10:00:00', [['USD', 33, 1, 33]], ['cust_name' => 'ลูกค้าเดินผ่าน']);

        $this->actingAsAdmin()
            ->get(route('reports.bot-submission', ['year' => 2026, 'month' => 1, 'branch_id' => $this->branch->id, 'group' => 'TH']))
            ->assertStatus(200)
            ->assertSee('สมชาย')
            ->assertSee('ลูกค้าเดินผ่าน')
            ->assertSee('ไม่ได้ผูกข้อมูลลูกค้า')
            ->assertSee('MMCSMC225590091_20260131_EMC_TH.xlsx');
    }

    public function test_download_returns_the_file(): void
    {
        $this->bill('BUYING', $this->customer(['nationality' => 'THA']), '2026-01-05 10:00:00', [['USD', 33, 1, 33]]);

        $response = $this->actingAsAdmin()->post(route('reports.bot-submission.export'), [
            'branch_id' => $this->branch->id, 'year' => 2026, 'month' => 1, 'group' => 'TH',
        ]);

        $response->assertOk();
        $this->assertStringContainsString('MMCSMC225590091_20260131_EMC_TH.xlsx', $response->headers->get('content-disposition'));
    }

    public function test_a_branch_of_another_company_cannot_be_exported(): void
    {
        $otherCompany = Company::create(['company_code' => 'OTHER', 'company_name' => 'อื่น', 'is_active' => true]);
        $foreign = Branch::create(['company_id' => $otherCompany->id, 'branch_code' => 'OT', 'branch_name' => 'นอกบริษัท', 'is_active' => true]);

        $this->actingAsAdmin()
            ->withSession(['active_company_id' => $this->company->id])
            ->post(route('reports.bot-submission.export'), [
                'branch_id' => $foreign->id, 'year' => 2026, 'month' => 1, 'group' => 'TH',
            ])->assertForbidden();
    }

    public function test_the_menu_goes_to_compliance_roles_not_counter_staff(): void
    {
        $roles = Menu::where('key', 'reports.bot-submission')->firstOrFail()->roles()->pluck('name')->all();

        $this->assertEqualsCanonicalizing(['superadmin', 'admin', 'branch_manager', 'auditor'], $roles);

        $this->actingAsStaff()->get(route('reports.bot-submission'))->assertForbidden();
    }

    public function test_country_names_codes_and_passport_codes_are_understood(): void
    {
        $codes = new CountryCode((require resource_path('templates/bot/emc-dimension.php'))['countries']);

        $this->assertSame('TH', $codes->toAlpha2('THA'));
        $this->assertSame('TH', $codes->toAlpha2('ไทย'));
        $this->assertSame('SG', $codes->toAlpha2('Singapore'));
        $this->assertSame('SG', $codes->toAlpha2('sg'));
        $this->assertSame('DE', $codes->toAlpha2('D'), 'พาสปอร์ตเยอรมันใช้รหัส D');
        $this->assertSame('GB', $codes->toAlpha2('GBR'));
        $this->assertSame('DE', $codes->toAlpha2('เยอรมนี'));
        $this->assertNull($codes->toAlpha2(''));
        $this->assertNull($codes->toAlpha2('XX'), 'รหัสที่ไม่อยู่ใน dropdown ของ template ใช้ไม่ได้');
    }
}
