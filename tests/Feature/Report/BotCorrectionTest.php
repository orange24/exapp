<?php

namespace Tests\Feature\Report;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Services\Bot\BotSubmission;
use Database\Seeders\BotSubmissionMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * แก้บิลที่ส่งแบงค์ชาติไม่ได้ จากหน้าส่งข้อมูลให้แบงค์ชาติ — auditor แก้ได้เอง
 * ส่วนผู้ตรวจสอบแบบอ่านอย่างเดียวดูได้อย่างเดียว
 */
class BotCorrectionTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private User $auditor;
    private User $readOnly;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->seed(BotSubmissionMenuSeeder::class);

        $this->auditor = User::create([
            'name' => 'Auditor', 'email' => 'auditor@test.local', 'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'auditor')->value('id'), 'is_active' => true,
        ]);
        $this->readOnly = User::create([
            'name' => 'Read Only', 'email' => 'readonly@test.local', 'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'auditor_readonly')->value('id'), 'is_active' => true,
        ]);
    }

    private function customer(array $attrs = []): int
    {
        return DB::table('customers')->insertGetId(array_merge([
            'type' => 'individual',
            'id_type' => 'passport',
            'id_number' => 'P' . (++$this->seq),
            'name_en' => 'Customer ' . $this->seq,
            'nationality' => 'JPN',
            'created_at' => now(),
            'updated_at' => now(),
        ], $attrs));
    }

    private function bill(?int $customerId, array $master = [], ?Counter $counter = null): int
    {
        $counter ??= $this->counter;

        $id = DB::table('transactions_master')->insertGetId(array_merge([
            'trns_no' => 'B' . str_pad((string) (++$this->seq), 6, '0', STR_PAD_LEFT),
            'trns_type' => 'BUYING',
            'counter_id' => $counter->id,
            'counter_name' => $counter->counter_name,
            'customer_id' => $customerId,
            'cust_name' => null,
            'flag_cancel' => 'N',
            'trns_datetime' => '2026-01-05 10:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ], $master));

        DB::table('transactions_detail')->insert([
            'transaction_id' => $id, 'currency_code' => 'USD', 'unit_price' => 33,
            'amount' => 100, 'total' => 3300, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $id;
    }

    private function submission(): BotSubmission
    {
        return new BotSubmission($this->branch->fresh(), 2026, 1);
    }

    private function page(User $user)
    {
        return $this->actingAs($user)->get(route('reports.bot-submission', [
            'branch_id' => $this->branch->id, 'year' => 2026, 'month' => 1,
        ]));
    }

    public function test_skipped_bills_say_how_they_can_be_fixed(): void
    {
        $unlinked = $this->bill(null);
        $badNationality = $this->customer(['nationality' => 'SAR']);
        $this->bill($badNationality);
        $this->bill($this->customer(['type' => 'corporate']), ['trns_type' => 'SELLING']);

        $skipped = $this->submission()->skipped();

        $this->assertSame([BotSubmission::FIX_LINK, BotSubmission::FIX_CUSTOMER, null], array_column($skipped, 'fix'));
        $this->assertSame($unlinked, $skipped[0]['transaction_id']);
        $this->assertSame($badNationality, $skipped[1]['customer_id']);
    }

    public function test_an_auditor_sees_the_fix_buttons(): void
    {
        $this->bill(null);
        $this->bill($this->customer(['nationality' => 'SAR']));

        $this->page($this->auditor)->assertOk()->assertSee('ผูกลูกค้า')->assertSee('แก้ข้อมูลลูกค้า');
    }

    public function test_an_auditor_fixes_a_nationality_and_the_bill_goes_into_the_file(): void
    {
        $customerId = $this->customer(['nationality' => 'SAR', 'name_en' => 'ALZUMAN']);
        $billId = $this->bill($customerId);

        $this->actingAs($this->auditor)
            ->patch(route('reports.bot-submission.customer.update', $billId), [
                'nationality' => 'SAU', 'id_type' => 'passport', 'id_number' => 'P777',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('SAU', Customer::find($customerId)->nationality);
        $this->assertSame([], $this->submission()->skipped());
        $this->assertCount(1, $this->submission()->rows(BotSubmission::FOREIGN)['buy']);

        $log = AuditLog::where('action', 'bot_submission.customer_corrected')->sole();
        $this->assertSame($this->auditor->id, $log->user_id);
        $this->assertSame('SAR', $log->old_values['nationality']);
    }

    public function test_a_nationality_outside_the_list_is_refused(): void
    {
        $customerId = $this->customer(['nationality' => 'SAR']);
        $billId = $this->bill($customerId);

        $this->actingAs($this->auditor)
            ->patch(route('reports.bot-submission.customer.update', $billId), [
                'nationality' => 'SAR', 'id_type' => 'passport', 'id_number' => 'P1',
            ])
            ->assertSessionHasErrors('nationality');

        $this->assertSame('SAR', Customer::find($customerId)->nationality);
    }

    public function test_an_auditor_links_an_existing_customer_to_a_bill(): void
    {
        $customerId = $this->customer(['nationality' => 'THA', 'id_type' => 'national_id', 'name_th' => 'สมชาย ใจดี']);
        $billId = $this->bill(null);

        $this->actingAs($this->auditor)
            ->post(route('reports.bot-submission.customer.link', $billId), ['customer_id' => $customerId])
            ->assertSessionHasNoErrors();

        $bill = DB::table('transactions_master')->find($billId);
        $this->assertSame($customerId, (int) $bill->customer_id);
        $this->assertSame('สมชาย ใจดี', $bill->cust_name, 'บิลที่ไม่มีชื่อลูกค้าได้ชื่อจากลูกค้าที่ผูก');
        $this->assertCount(1, $this->submission()->rows(BotSubmission::THAI)['buy']);
        $this->assertSame(1, AuditLog::where('action', 'bot_submission.customer_linked')->count());
    }

    public function test_linking_keeps_the_name_already_on_the_bill(): void
    {
        $billId = $this->bill(null, ['cust_name' => 'ชื่อบนบิล']);

        $this->actingAs($this->auditor)
            ->post(route('reports.bot-submission.customer.link', $billId), ['customer_id' => $this->customer()]);

        $this->assertSame('ชื่อบนบิล', DB::table('transactions_master')->find($billId)->cust_name);
    }

    public function test_an_auditor_creates_a_new_customer_for_a_bill(): void
    {
        $billId = $this->bill(null);

        $this->actingAs($this->auditor)
            ->post(route('reports.bot-submission.customer.link', $billId), [
                'name' => 'นภัส เพชรพิชัย', 'id_type' => 'national_id', 'id_number' => '1100000000001', 'nationality' => 'THA',
            ])
            ->assertSessionHasNoErrors();

        $customer = Customer::where('id_number', '1100000000001')->sole();
        $this->assertSame('นภัส เพชรพิชัย', $customer->name_th);
        $this->assertSame($customer->id, (int) DB::table('transactions_master')->find($billId)->customer_id);
        $this->assertSame([], $this->submission()->skipped());
    }

    public function test_creating_a_customer_whose_id_already_exists_is_refused(): void
    {
        $this->customer(['id_number' => 'DUP1']);
        $billId = $this->bill(null);

        $this->actingAs($this->auditor)
            ->post(route('reports.bot-submission.customer.link', $billId), [
                'name' => 'X', 'id_type' => 'passport', 'id_number' => 'DUP1', 'nationality' => 'JPN',
            ])
            ->assertSessionHasErrors('id_number');

        $this->assertNull(DB::table('transactions_master')->find($billId)->customer_id);
    }

    public function test_a_bill_that_already_has_a_customer_cannot_be_relinked(): void
    {
        $billId = $this->bill($this->customer());

        $this->actingAs($this->auditor)
            ->post(route('reports.bot-submission.customer.link', $billId), ['customer_id' => $this->customer()])
            ->assertStatus(422);
    }

    public function test_search_finds_customers_by_name_or_id(): void
    {
        $this->customer(['name_en' => 'QAIS SALIM', 'id_number' => 'OM123']);

        $this->actingAs($this->auditor)->getJson(route('reports.bot-submission.customers', ['q' => 'qais']))
            ->assertOk()->assertJsonPath('0.id_number', 'OM123');
        $this->actingAs($this->auditor)->getJson(route('reports.bot-submission.customers', ['q' => 'OM12']))
            ->assertOk()->assertJsonPath('0.name', 'QAIS SALIM');
    }

    // ── อ่านอย่างเดียว ─────────────────────────────────────────

    public function test_a_read_only_auditor_sees_the_list_without_fix_buttons(): void
    {
        $this->bill(null);

        $this->page($this->readOnly)->assertOk()
            ->assertSee('ไม่ได้ผูกข้อมูลลูกค้า')
            ->assertDontSee('ผูกลูกค้าเข้าบิล');
    }

    public function test_a_read_only_auditor_cannot_fix_by_posting_directly(): void
    {
        $customerId = $this->customer(['nationality' => 'SAR']);
        $billId = $this->bill($customerId);
        $unlinked = $this->bill(null);

        $this->actingAs($this->readOnly)
            ->patch(route('reports.bot-submission.customer.update', $billId), [
                'nationality' => 'SAU', 'id_type' => 'passport', 'id_number' => 'P1',
            ])->assertForbidden();
        $this->actingAs($this->readOnly)
            ->post(route('reports.bot-submission.customer.link', $unlinked), ['customer_id' => $customerId])
            ->assertForbidden();

        $this->assertSame('SAR', Customer::find($customerId)->nationality);
        $this->assertNull(DB::table('transactions_master')->find($unlinked)->customer_id);
    }

    public function test_a_read_only_auditor_can_still_download_the_file(): void
    {
        $this->bill($this->customer());

        $this->actingAs($this->readOnly)
            ->post(route('reports.bot-submission.export'), [
                'branch_id' => $this->branch->id, 'year' => 2026, 'month' => 1, 'group' => BotSubmission::FOREIGN,
            ])
            ->assertOk()
            ->assertDownload();
    }

    // ── ขอบเขต ─────────────────────────────────────────

    public function test_staff_cannot_fix(): void
    {
        $billId = $this->bill(null);

        $this->actingAsStaff()
            ->post(route('reports.bot-submission.customer.link', $billId), ['customer_id' => $this->customer()])
            ->assertForbidden();
    }

    public function test_a_branch_manager_cannot_fix_a_bill_of_another_branch(): void
    {
        $other = Branch::create(['branch_code' => 'OTHER', 'branch_name' => 'อีกสาขา', 'is_active' => true]);
        $otherCounter = Counter::create(['counter_code' => 'OT-C1', 'counter_name' => 'OT', 'branch_id' => $other->id, 'is_active' => true]);
        $billId = $this->bill(null, [], $otherCounter);

        $manager = User::create([
            'name' => 'Manager', 'email' => 'manager@test.local', 'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'branch_manager')->value('id'), 'branch_id' => $this->branch->id, 'is_active' => true,
        ]);
        $manager->branches()->sync([$this->branch->id]);

        $this->actingAs($manager)
            ->post(route('reports.bot-submission.customer.link', $billId), ['customer_id' => $this->customer()])
            ->assertForbidden();
    }
}
