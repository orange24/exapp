<?php

namespace Tests\Feature\Company;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * หน้าจัดการสาขาแบ่งตามบริษัท และทุกสาขาต้องระบุว่าสังกัดบริษัทไหน
 */
class BranchBelongsToCompanyTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Company $south;
    private Company $trading;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $this->south = Company::create(['company_code' => 'SOUTH', 'company_name' => 'บริษัทใต้', 'is_active' => true]);
        $this->trading = Company::create(['company_code' => 'TRD', 'company_name' => 'บริษัทเทรด', 'is_active' => true]);

        $this->branch->update(['company_id' => $this->south->id]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'company_id' => $this->trading->id,
            'branch_code' => 'NEW-01',
            'branch_name' => 'สาขาใหม่',
            'type' => 'branch',
            'is_active' => '1',
        ], $overrides);
    }

    public function test_the_list_is_grouped_by_company(): void
    {
        Branch::create(['company_id' => $this->trading->id, 'branch_code' => 'TRD-01', 'branch_name' => 'สาขาเทรด', 'is_active' => true]);

        $this->actingAsSuperAdmin()->get(route('admin.branches.index'))
            ->assertStatus(200)
            ->assertSeeInOrder(['บริษัทใต้', 'HQ Bangkok', 'บริษัทเทรด', 'สาขาเทรด']);
    }

    public function test_a_branch_without_a_company_is_listed_separately(): void
    {
        Branch::create(['branch_code' => 'LOOSE', 'branch_name' => 'สาขาลอย', 'is_active' => true]);

        $this->actingAsSuperAdmin()->get(route('admin.branches.index'))
            ->assertSeeInOrder(['ยังไม่ได้ผูกบริษัท', 'สาขาลอย']);
    }

    public function test_each_company_has_its_own_add_button(): void
    {
        $this->actingAsSuperAdmin()->get(route('admin.branches.index'))
            ->assertSee(route('admin.branches.create', ['company_id' => $this->south->id]), false)
            ->assertSee(route('admin.branches.create', ['company_id' => $this->trading->id]), false)
            ->assertDontSee('href="' . route('admin.branches.create') . '"', false);
    }

    public function test_adding_from_a_company_preselects_it(): void
    {
        // ตรวจเฉพาะ option ของบริษัท — sidebar มี dropdown เคาน์เตอร์ที่ value ซ้ำกันได้
        $html = $this->actingAsSuperAdmin()->get(route('admin.branches.create', ['company_id' => $this->trading->id]))
            ->assertStatus(200)
            ->getContent();

        $this->assertMatchesRegularExpression('/value="' . $this->trading->id . '" selected>\s*TRD —/', $html);
        $this->assertDoesNotMatchRegularExpression('/value="' . $this->south->id . '" selected>\s*SOUTH —/', $html);
    }

    public function test_a_new_branch_is_saved_under_the_chosen_company(): void
    {
        $this->actingAsSuperAdmin()->post(route('admin.branches.store'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertSame($this->trading->id, (int) Branch::where('branch_code', 'NEW-01')->value('company_id'));
    }

    public function test_a_branch_must_name_its_company(): void
    {
        $this->actingAsSuperAdmin()->post(route('admin.branches.store'), $this->payload(['company_id' => '']))
            ->assertSessionHasErrors('company_id');
    }

    public function test_a_branch_can_be_moved_to_another_company(): void
    {
        $this->actingAsSuperAdmin()->put(route('admin.branches.update', $this->branch), $this->payload([
            'branch_code' => $this->branch->branch_code,
            'branch_name' => $this->branch->branch_name,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($this->trading->id, (int) $this->branch->fresh()->company_id);
    }

    public function test_the_form_offers_the_companies_and_shows_the_current_one(): void
    {
        $this->actingAsSuperAdmin()->get(route('admin.branches.edit', $this->branch))
            ->assertStatus(200)
            ->assertSee('บริษัท (สำนักงานใหญ่)')
            ->assertSee('<option value="' . $this->south->id . '" selected>', false)
            ->assertSee('บริษัทเทรด');
    }
}
