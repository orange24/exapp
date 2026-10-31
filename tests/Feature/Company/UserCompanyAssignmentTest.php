<?php

namespace Tests\Feature\Company;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Counter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ผูกผู้ใช้กับบริษัท (หลายบริษัท) และสาขา (หลายสาขา) จากฟอร์มผู้ใช้
 *
 * staff กับ branch_manager ทำงานประจำสาขาจึงต้องเลือกสาขา ส่วน auditor/trader
 * ดูทุกสาขาของบริษัทที่ผูกไว้ เลือกแค่บริษัทพอ
 */
class UserCompanyAssignmentTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Company $south;
    private Company $trading;
    private Branch $tradingBranch;
    private Counter $tradingCounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $this->south = Company::create(['company_code' => 'SOUTH', 'company_name' => 'บริษัทใต้', 'is_active' => true]);
        $this->trading = Company::create(['company_code' => 'TRD', 'company_name' => 'บริษัทเทรด', 'is_active' => true]);

        $this->branch->update(['company_id' => $this->south->id]);

        $this->tradingBranch = Branch::create([
            'company_id' => $this->trading->id,
            'branch_code' => 'TRD-01',
            'branch_name' => 'สาขาเทรด',
            'is_active' => true,
        ]);

        $this->tradingCounter = Counter::create([
            'counter_code' => 'TRD-C1',
            'counter_name' => 'เคาน์เตอร์เทรด',
            'branch_id' => $this->tradingBranch->id,
            'is_active' => true,
        ]);
    }

    private function role(string $name): int
    {
        return Role::where('name', $name)->firstOrFail()->id;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'พนักงานสองบริษัท',
            'email' => 'two-companies@test.local',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role_id' => $this->role('staff'),
            'company_ids' => [$this->south->id, $this->trading->id],
            'branch_ids' => [$this->branch->id, $this->tradingBranch->id],
            'is_active' => 1,
        ], $overrides);
    }

    public function test_the_form_lists_each_company_with_its_branches(): void
    {
        $this->actingAsSuperAdmin()->get(route('admin.users.create'))
            ->assertStatus(200)
            ->assertSee('บริษัทใต้')
            ->assertSee('บริษัทเทรด')
            ->assertSee('name="company_ids[]"', false)
            ->assertSee('name="branch_ids[]"', false)
            ->assertSee('สาขาเทรด');
    }

    public function test_a_staff_can_be_given_two_companies_and_several_branches(): void
    {
        $this->actingAsSuperAdmin()->post(route('admin.users.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'two-companies@test.local')->firstOrFail();

        $this->assertEqualsCanonicalizing([$this->south->id, $this->trading->id], $user->companies()->pluck('companies.id')->all());
        $this->assertEqualsCanonicalizing([$this->branch->id, $this->tradingBranch->id], $user->branches()->pluck('branches.id')->all());
        $this->assertContains($user->branch_id, [$this->branch->id, $this->tradingBranch->id], 'สาขาต้นสังกัดต้องเป็นหนึ่งในสาขาที่เลือก');
    }

    public function test_editing_replaces_the_previous_assignment(): void
    {
        $this->staffUser->companies()->sync([$this->south->id, $this->trading->id]);
        $this->staffUser->branches()->sync([$this->branch->id, $this->tradingBranch->id]);

        $this->actingAsSuperAdmin()->put(route('admin.users.update', $this->staffUser), $this->payload([
            'email' => $this->staffUser->email,
            'password' => '',
            'password_confirmation' => '',
            'company_ids' => [$this->trading->id],
            'branch_ids' => [$this->tradingBranch->id],
        ]))->assertSessionHasNoErrors();

        $this->staffUser->refresh();

        $this->assertSame([$this->trading->id], $this->staffUser->companies()->pluck('companies.id')->all());
        $this->assertSame([$this->tradingBranch->id], $this->staffUser->branches()->pluck('branches.id')->all());
        $this->assertSame($this->tradingBranch->id, $this->staffUser->branch_id, 'สาขาเดิมหลุดจากรายการแล้ว ต้องย้ายต้นสังกัดตาม');
    }

    public function test_a_staff_must_have_a_branch(): void
    {
        $this->actingAsSuperAdmin()->post(route('admin.users.store'), $this->payload(['branch_ids' => []]))
            ->assertSessionHasErrors('branch_ids');
    }

    public function test_a_staff_needs_a_branch_in_every_company_ticked(): void
    {
        // ติ๊กบริษัทแต่ไม่มีสาขาให้ลงเวร สลับไปบริษัทนั้นแล้วจะไม่มีที่ทำงาน
        $this->actingAsSuperAdmin()->post(route('admin.users.store'), $this->payload([
            'branch_ids' => [$this->branch->id],
        ]))->assertSessionHasErrors('branch_ids');
    }

    public function test_a_branch_outside_the_ticked_companies_is_refused(): void
    {
        $this->actingAsSuperAdmin()->post(route('admin.users.store'), $this->payload([
            'company_ids' => [$this->south->id],
            'branch_ids' => [$this->branch->id, $this->tradingBranch->id],
        ]))->assertSessionHasErrors('branch_ids');
    }

    public function test_a_non_admin_must_have_a_company(): void
    {
        $this->actingAsSuperAdmin()->post(route('admin.users.store'), $this->payload(['company_ids' => []]))
            ->assertSessionHasErrors('company_ids');
    }

    public function test_an_auditor_picks_companies_only(): void
    {
        $this->actingAsSuperAdmin()->post(route('admin.users.store'), $this->payload([
            'role_id' => $this->role('auditor'),
            'branch_ids' => [],
        ]))->assertSessionHasNoErrors();

        $user = User::where('email', 'two-companies@test.local')->firstOrFail();

        $this->assertCount(2, $user->companies);
        $this->assertCount(0, $user->branches);
        $this->assertNull($user->branch_id);
    }

    public function test_an_admin_needs_no_company(): void
    {
        // admin เข้าได้ทุกบริษัทอยู่แล้ว ไม่ต้องไล่ติ๊ก
        $this->actingAsSuperAdmin()->post(route('admin.users.store'), $this->payload([
            'role_id' => $this->role('admin'),
            'company_ids' => [],
            'branch_ids' => [],
        ]))->assertSessionHasNoErrors();
    }

    public function test_staff_may_only_switch_to_their_assigned_branches(): void
    {
        $other = Branch::create(['company_id' => $this->south->id, 'branch_code' => 'X', 'branch_name' => 'ไม่ได้ผูก', 'is_active' => true]);
        $this->staffUser->companies()->sync([$this->south->id]);
        $this->staffUser->branches()->sync([$this->branch->id]);

        $this->assertSame([$this->branch->id], $this->staffUser->selectableBranches()->pluck('id')->all());

        $this->actingAsStaff()->post(route('switch-branch'), ['branch_id' => $other->id])
            ->assertForbidden();
    }

    public function test_switching_company_puts_staff_at_a_branch_of_the_new_company(): void
    {
        // เดิมสลับแล้วสาขาทำงานถูกล้าง จนไปตกที่ users.branch_id ซึ่งเป็นของ
        // บริษัทเก่า หน้าทุกหน้าในบริษัทใหม่จึงว่างเปล่า
        $this->staffUser->companies()->sync([$this->south->id, $this->trading->id]);
        $this->staffUser->branches()->sync([$this->branch->id, $this->tradingBranch->id]);

        $this->actingAs($this->staffUser)
            ->withSession([
                'active_company_id' => $this->south->id,
                'working_branch_id' => $this->branch->id,
                'working_counter_id' => $this->counter->id,
            ])
            ->post(route('switch-company'), ['company_id' => $this->trading->id]);

        $this->assertSame($this->tradingBranch->id, session('working_branch_id'));
        $this->assertSame($this->tradingCounter->id, session('working_counter_id'));
        $this->assertSame([$this->tradingBranch->id], $this->staffUser->fresh()->getVisibleBranchIds());
        $this->assertSame($this->tradingBranch->id, $this->staffUser->fresh()->last_working_branch_id, 'login ครั้งหน้าต้องกลับมาที่สาขานี้');
    }

    public function test_the_add_form_ticks_yessouth_by_default(): void
    {
        $yes = Company::create(['company_code' => 'YESSOUTH', 'company_name' => 'บริษัท เยส เซาเทิร์น', 'is_active' => true]);

        $html = $this->actingAsSuperAdmin()->get(route('admin.users.create'))->getContent();

        $this->assertMatchesRegularExpression('/value="' . $yes->id . '"\s+[^>]*checked/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="' . $this->trading->id . '"\s+[^>]*checked/', $html);
    }

    public function test_existing_users_are_put_in_yessouth(): void
    {
        $yes = Company::create(['company_code' => 'YESSOUTH', 'company_name' => 'บริษัท เยส เซาเทิร์น', 'is_active' => true]);
        $this->staffUser->companies()->sync([]);

        // คนที่ผูกไว้แล้วต้องไม่ถูกทับ
        $kept = User::create([
            'name' => 'ผูกไว้แล้ว',
            'email' => 'kept@test.local',
            'password' => bcrypt('password'),
            'role_id' => $this->role('auditor'),
            'is_active' => true,
        ]);
        $kept->companies()->sync([$this->trading->id]);

        $migration = require database_path('migrations/2026_10_30_100000_assign_users_to_default_company.php');
        $migration->up();
        $migration->up(); // รันซ้ำได้

        $this->assertSame([$yes->id], $this->staffUser->companies()->pluck('companies.id')->all());
        $this->assertSame([$this->trading->id], $kept->companies()->pluck('companies.id')->all());
        $this->assertCount(0, $this->adminUser->companies, 'admin เข้าได้ทุกบริษัทอยู่แล้ว ไม่ต้องผูก');
    }

    public function test_the_sidebar_shows_company_then_working_branch_for_staff(): void
    {
        $this->staffUser->companies()->sync([$this->south->id, $this->trading->id]);
        $this->staffUser->branches()->sync([$this->branch->id, $this->tradingBranch->id]);

        $this->actingAs($this->staffUser)
            ->withSession(['active_company_id' => $this->trading->id, 'working_branch_id' => $this->tradingBranch->id])
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertSeeInOrder([route('switch-company'), route('switch-branch')], false)
            ->assertSee('สาขาเทรด');
    }

    public function test_other_roles_get_no_working_branch_dropdown(): void
    {
        $auditor = User::create([
            'name' => 'ผู้ตรวจ',
            'email' => 'aud@test.local',
            'password' => bcrypt('password'),
            'role_id' => $this->role('auditor'),
            'is_active' => true,
        ]);
        $auditor->companies()->sync([$this->south->id, $this->trading->id]);

        $this->actingAs($auditor)->get('/dashboard')
            ->assertSee(route('switch-company'), false)
            ->assertDontSee(route('switch-branch'), false);
    }

    public function test_login_opens_the_company_of_the_branch_last_worked_at(): void
    {
        $this->staffUser->companies()->sync([$this->south->id, $this->trading->id]);
        $this->staffUser->branches()->sync([$this->branch->id, $this->tradingBranch->id]);
        $this->staffUser->update(['last_working_branch_id' => $this->tradingBranch->id]);

        $this->post(route('login'), ['email' => $this->staffUser->email, 'password' => 'password']);

        $this->assertSame($this->trading->id, session('active_company_id'));
        $this->assertSame($this->tradingBranch->id, session('working_branch_id'));
    }

    public function test_a_branch_manager_sees_the_branches_ticked(): void
    {
        $manager = User::create([
            'name' => 'ผู้จัดการ',
            'email' => 'bm@test.local',
            'password' => bcrypt('password'),
            'role_id' => $this->role('branch_manager'),
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $second = Branch::create(['company_id' => $this->south->id, 'branch_code' => 'S2', 'branch_name' => 'ใต้สอง', 'is_active' => true]);
        $manager->companies()->sync([$this->south->id]);
        $manager->branches()->sync([$this->branch->id, $second->id]);

        $this->assertEqualsCanonicalizing([$this->branch->id, $second->id], $manager->getVisibleBranchIds());
        $this->assertTrue($manager->canManageBranch($second->id));
    }
}
