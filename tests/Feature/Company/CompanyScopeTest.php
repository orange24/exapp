<?php

namespace Tests\Feature\Company;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * บริษัทคือเจ้าของสต็อก บิล และบัญชี — สาขาเป็นแค่สถานที่ของบริษัทนั้น
 *
 * ข้อมูลเงินทุกตารางผูกกับสาขาหรือเคาน์เตอร์อยู่แล้ว จึงสืบขึ้นไปถึงบริษัทได้
 * โดยไม่ต้องเติม company_id ลงทุกตาราง จุดคอขวดคือ getVisibleBranchIds()
 * ซึ่งทั้งระบบเรียกใช้ — บีบที่จุดนี้จุดเดียวแล้วทุกหน้าถูก scope ตามบริษัท
 */
class CompanyScopeTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    public function test_a_company_owns_its_branches(): void
    {
        $company = Company::create([
            'company_code' => 'ACME',
            'company_name' => 'บริษัท เอซีเอ็มอี จำกัด',
            'is_active' => true,
        ]);

        $branch = Branch::create([
            'branch_code' => 'ACME-01',
            'branch_name' => 'สาขาอโศก',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $this->assertTrue($company->branches->contains('id', $branch->id));
        $this->assertSame($company->id, $branch->company->id);
    }

    public function test_the_company_code_is_unique(): void
    {
        Company::create(['company_code' => 'DUP', 'company_name' => 'หนึ่ง', 'is_active' => true]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Company::create(['company_code' => 'DUP', 'company_name' => 'สอง', 'is_active' => true]);
    }

    public function test_existing_branches_are_adopted_by_a_default_company(): void
    {
        // จำลองสภาพ "สาขาที่มีอยู่ก่อนฟีเจอร์นี้" แล้วรัน migration ย้ายข้อมูลซ้ำ
        DB::table('branches')->update(['company_id' => null]);
        DB::table('companies')->delete();

        $migration = require database_path(
            'migrations/2026_10_24_100001_add_company_id_to_branches_table.php'
        );
        $migration->up();

        $this->assertNotNull(
            $this->branch->fresh()->company_id,
            'สาขาที่มีอยู่ก่อนต้องถูกผูกเข้าบริษัทตั้งต้น ไม่งั้นข้อมูลเดิมหายไปจากทุกหน้า',
        );
    }

    public function test_a_user_reaches_only_the_companies_they_are_assigned(): void
    {
        $mine = Company::create(['company_code' => 'MINE', 'company_name' => 'ของฉัน', 'is_active' => true]);
        $theirs = Company::create(['company_code' => 'THEIRS', 'company_name' => 'ของคนอื่น', 'is_active' => true]);

        $this->staffUser->companies()->sync([$mine->id]);

        $ids = $this->staffUser->fresh()->accessibleCompanyIds();

        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_an_admin_reaches_every_company(): void
    {
        Company::create(['company_code' => 'A', 'company_name' => 'เอ', 'is_active' => true]);
        Company::create(['company_code' => 'B', 'company_name' => 'บี', 'is_active' => true]);

        // ไม่ได้ผูกบริษัทให้ admin สักราย — admin ต้องเข้าได้ทุกบริษัทอยู่ดี
        $this->assertCount(Company::count(), $this->adminUser->accessibleCompanyIds());
    }

    public function test_a_user_with_no_assignment_falls_back_to_their_own_branch_company(): void
    {
        // ผู้ใช้เดิมทั้งระบบยังไม่มีแถวใน company_user — ห้ามให้เขาเข้าไม่ได้เลย
        $company = Company::create(['company_code' => 'FALL', 'company_name' => 'สำรอง', 'is_active' => true]);
        $this->branch->update(['company_id' => $company->id]);

        $this->assertSame([$company->id], $this->staffUser->fresh()->accessibleCompanyIds());
    }

    public function test_visible_branches_are_limited_to_the_active_company(): void
    {
        $mine = Company::create(['company_code' => 'M1', 'company_name' => 'บริษัทหนึ่ง', 'is_active' => true]);
        $other = Company::create(['company_code' => 'M2', 'company_name' => 'บริษัทสอง', 'is_active' => true]);

        $this->branch->update(['company_id' => $mine->id]);

        $otherBranch = Branch::create([
            'branch_code' => 'OTH-01',
            'branch_name' => 'สาขาบริษัทสอง',
            'company_id' => $other->id,
            'is_active' => true,
        ]);

        // admin เห็นทุกสาขาก็จริง แต่ต้องเห็นเฉพาะของบริษัทที่กำลังเลือกอยู่
        session(['active_company_id' => $mine->id]);

        $visible = $this->adminUser->getVisibleBranchIds();

        $this->assertContains($this->branch->id, $visible);
        $this->assertNotContains(
            $otherBranch->id,
            $visible,
            'สาขาของบริษัทอื่นต้องไม่โผล่ ไม่งั้นสต็อกและบิลข้ามบริษัทกัน',
        );
    }

    public function test_the_active_company_defaults_to_the_first_one_the_user_can_reach(): void
    {
        $company = Company::create(['company_code' => 'DEF', 'company_name' => 'ตั้งต้น', 'is_active' => true]);
        $this->branch->update(['company_id' => $company->id]);

        session()->forget('active_company_id');

        $this->assertSame($company->id, $this->staffUser->fresh()->activeCompanyId());
    }

    public function test_a_stale_session_company_is_ignored(): void
    {
        // ผู้ใช้ถูกถอดสิทธิ์บริษัทออกระหว่างที่ยัง login ค้างอยู่ — ค่าใน session
        // ต้องไม่ถูกเชื่อ ไม่งั้นเขายังทำรายการของบริษัทนั้นต่อได้
        $mine = Company::create(['company_code' => 'OK', 'company_name' => 'ที่เข้าได้', 'is_active' => true]);
        $gone = Company::create(['company_code' => 'NO', 'company_name' => 'ที่ถูกถอด', 'is_active' => true]);

        $this->branch->update(['company_id' => $mine->id]);
        $this->staffUser->companies()->sync([$mine->id]);

        session(['active_company_id' => $gone->id]);

        $this->assertSame($mine->id, $this->staffUser->fresh()->activeCompanyId());
    }

    public function test_an_auditor_still_sees_every_branch_within_the_active_company(): void
    {
        // auditor ตรวจทุกสาขา แต่ยังอยู่ในกรอบบริษัทที่เลือกอยู่
        $mine = Company::create(['company_code' => 'AUD1', 'company_name' => 'บริษัทที่ตรวจ', 'is_active' => true]);
        $other = Company::create(['company_code' => 'AUD2', 'company_name' => 'บริษัทอื่น', 'is_active' => true]);

        $this->branch->update(['company_id' => $mine->id]);

        $otherBranch = Branch::create([
            'branch_code' => 'AUD-02',
            'branch_name' => 'สาขาบริษัทอื่น',
            'company_id' => $other->id,
            'is_active' => true,
        ]);

        $auditor = \App\Models\User::create([
            'name' => 'ผู้ตรวจสอบ',
            'email' => 'auditor-company@test.local',
            'password' => bcrypt('password'),
            'role_id' => \App\Models\Role::where('name', 'auditor')->firstOrFail()->id,
            'branch_id' => null,
            'is_active' => true,
        ]);
        $auditor->companies()->sync([$mine->id]);

        session(['active_company_id' => $mine->id]);

        $visible = $auditor->getVisibleBranchIds();

        $this->assertContains($this->branch->id, $visible);
        $this->assertNotContains($otherBranch->id, $visible);
    }
}
