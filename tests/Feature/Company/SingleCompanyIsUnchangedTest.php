<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ลูกค้าที่ใช้อยู่ตอนนี้มีบริษัทเดียว การเพิ่มชั้นบริษัทเข้าไปต้องไม่เปลี่ยน
 * อะไรสำหรับเขาเลย — ไม่มี dropdown โผล่ ไม่มีข้อมูลหาย ไม่ต้องตั้งค่าอะไรใหม่
 */
class SingleCompanyIsUnchangedTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $company = Company::create([
            'company_code' => 'ONLY',
            'company_name' => 'บริษัทเดียว',
            'is_active' => true,
        ]);

        $this->branch->update(['company_id' => $company->id]);
    }

    public function test_the_staff_still_see_their_branch(): void
    {
        $this->assertContains($this->branch->id, $this->staffUser->fresh()->getVisibleBranchIds());
    }

    public function test_the_admin_still_sees_every_branch(): void
    {
        $this->assertContains($this->branch->id, $this->adminUser->fresh()->getVisibleBranchIds());
    }

    public function test_no_company_dropdown_appears(): void
    {
        $this->actingAsStaff()->get('/dashboard')
            ->assertStatus(200)
            ->assertDontSee(route('switch-company'), false);
    }

    public function test_pages_that_list_branches_still_render(): void
    {
        // หน้านี้วิ่งผ่าน getVisibleBranchIds() ซึ่งเป็นจุดที่เพิ่ม scope บริษัท
        // เข้าไป ถ้าบีบแรงเกินจะเหลือศูนย์สาขาแล้วหน้าว่างเปล่า
        $this->actingAsAdmin()->get(route('admin.branches.index'))
            ->assertStatus(200)
            ->assertSee($this->branch->branch_name);
    }

    public function test_the_buy_page_still_asks_to_open_the_working_day_first(): void
    {
        // 302 ตรงนี้คือขั้นตอนเปิดวันทำการตามปกติ มีมาก่อนฟีเจอร์บริษัทแล้ว
        // เขียนไว้กันคนอ่านทีหลังเข้าใจผิดว่า scope บริษัทกรองสาขาทิ้งจนหมด
        $this->actingAsStaff()->get('/transaction/buy')
            ->assertRedirect(route('inventory.open-close-day'));
    }

    /**
     * ระบบที่ยังไม่มีบริษัทเลยต้องไม่ทำให้ทุกหน้าว่างเปล่า
     *
     * ถ้า narrowToActiveCompany() บีบตอนไม่มีบริษัท ผลคือไม่เหลือสาขาสักแห่ง
     * แล้วทั้งระบบขึ้น "ไม่มีข้อมูล" โดยไม่มีอะไรบอกสาเหตุ
     */
    public function test_a_system_with_no_companies_at_all_still_works(): void
    {
        $this->branch->update(['company_id' => null]);
        Company::query()->delete();

        $this->assertContains($this->branch->id, $this->adminUser->fresh()->getVisibleBranchIds());
    }
}
