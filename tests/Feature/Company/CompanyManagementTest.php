<?php

namespace Tests\Feature\Company;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * หน้าจัดการบริษัท — อยู่ใต้ "ตั้งค่า" คู่กับจัดการสาขา
 *
 * เป็นงานตั้งค่าระดับระบบ จึงให้เฉพาะคนที่ถือสิทธิ์ module1/write เหมือนหน้า
 * จัดการสาขาและจัดการผู้ใช้
 */
class CompanyManagementTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\CompanyMenuSeeder::class);
    }

    public function test_the_menu_sits_under_settings_next_to_branches(): void
    {
        $menu = \App\Models\Menu::where('key', 'admin.companies')->first();

        $this->assertNotNull($menu, 'ไม่มีเมนู ผู้ดูแลจะหาหน้านี้ไม่เจอ ต้องพิมพ์ URL เอง');
        $this->assertSame('settings', \App\Models\Menu::find($menu->parent_id)?->key);
        $this->assertTrue(\Illuminate\Support\Facades\Route::has($menu->route));
    }

    public function test_no_role_is_shown_a_link_it_would_be_refused(): void
    {
        $menu = \App\Models\Menu::where('key', 'admin.companies')->firstOrFail();

        foreach (\App\Models\Role::with('menus')->get() as $role) {
            $sees = $role->menus->contains('id', $menu->id);

            $user = new \App\Models\User(['role_id' => $role->id]);
            $user->setRelation('role', $role);

            $this->assertSame(
                $user->hasPermission('module1', 'write'),
                $sees,
                "role {$role->name}: เห็นเมนู=" . var_export($sees, true),
            );
        }
    }

    public function test_an_admin_can_open_the_page(): void
    {
        Company::create(['company_code' => 'VIEW', 'company_name' => 'บริษัททดสอบ', 'is_active' => true]);

        $this->actingAsAdmin()->get(route('admin.companies.index'))
            ->assertStatus(200)
            ->assertSee('บริษัททดสอบ');
    }

    public function test_a_staff_is_refused(): void
    {
        $this->actingAsStaff()->get(route('admin.companies.index'))->assertStatus(403);
    }

    public function test_an_admin_can_create_a_company(): void
    {
        $this->actingAsAdmin()->post(route('admin.companies.store'), [
            'company_code' => 'NEWCO',
            'company_name' => 'บริษัทใหม่ จำกัด',
            'tax_id' => '0105500000000',
            'is_active' => 1,
        ])->assertRedirect(route('admin.companies.index'));

        $this->assertDatabaseHas('companies', ['company_code' => 'NEWCO']);
    }

    public function test_a_duplicate_company_code_is_refused(): void
    {
        Company::create(['company_code' => 'TAKEN', 'company_name' => 'มีแล้ว', 'is_active' => true]);

        $this->actingAsAdmin()->post(route('admin.companies.store'), [
            'company_code' => 'TAKEN',
            'company_name' => 'ซ้ำ',
            'is_active' => 1,
        ])->assertSessionHasErrors('company_code');
    }

    public function test_an_admin_can_edit_a_company(): void
    {
        $company = Company::create(['company_code' => 'EDIT', 'company_name' => 'ชื่อเดิม', 'is_active' => true]);

        $this->actingAsAdmin()->put(route('admin.companies.update', $company), [
            'company_code' => 'EDIT',
            'company_name' => 'ชื่อใหม่',
            'is_active' => 1,
        ])->assertRedirect(route('admin.companies.index'));

        $this->assertSame('ชื่อใหม่', $company->fresh()->company_name);
    }

    public function test_a_company_with_branches_cannot_be_deactivated_into_orphans(): void
    {
        // ปิดบริษัทที่ยังมีสาขาเปิดอยู่ = สาขาเหล่านั้นหายไปจากทุกหน้าทันที
        // โดยไม่มีใครรู้ว่าทำไม ต้องเตือนก่อน ไม่ใช่ปล่อยให้กดผ่าน
        $company = Company::create(['company_code' => 'HASBR', 'company_name' => 'มีสาขา', 'is_active' => true]);

        Branch::create([
            'branch_code' => 'HB-01',
            'branch_name' => 'สาขาที่ยังเปิด',
            'company_id' => $company->id,
            'is_active' => true,
        ]);

        $this->actingAsAdmin()
            ->patch(route('admin.companies.toggle-active', $company))
            ->assertSessionHas('error');

        $this->assertTrue($company->fresh()->is_active, 'บริษัทต้องยังเปิดอยู่');
    }

    public function test_a_company_with_no_active_branches_can_be_deactivated(): void
    {
        $company = Company::create(['company_code' => 'EMPTY', 'company_name' => 'ไม่มีสาขา', 'is_active' => true]);

        $this->actingAsAdmin()->patch(route('admin.companies.toggle-active', $company));

        $this->assertFalse($company->fresh()->is_active);
    }
}
