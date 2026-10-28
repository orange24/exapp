<?php

namespace Tests\Feature\Company;

use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class SwitchCompanyTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Company $allowed;
    private Company $forbidden;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $this->allowed = Company::create(['company_code' => 'OK', 'company_name' => 'เข้าได้', 'is_active' => true]);
        $this->forbidden = Company::create(['company_code' => 'NO', 'company_name' => 'เข้าไม่ได้', 'is_active' => true]);

        $this->branch->update(['company_id' => $this->allowed->id]);
        $this->staffUser->companies()->sync([$this->allowed->id]);
    }

    public function test_a_user_can_switch_to_a_company_they_may_reach(): void
    {
        $this->actingAs($this->staffUser)
            ->post(route('switch-company'), ['company_id' => $this->allowed->id])
            ->assertRedirect();

        $this->assertSame($this->allowed->id, session('active_company_id'));
    }

    public function test_switching_to_a_company_they_may_not_reach_is_refused(): void
    {
        // dropdown ไม่ใช่การควบคุมสิทธิ์ — ค่าที่ส่งมาแก้ได้จากฝั่งผู้ใช้
        $this->actingAs($this->staffUser)
            ->post(route('switch-company'), ['company_id' => $this->forbidden->id])
            ->assertForbidden();

        $this->assertNotSame($this->forbidden->id, session('active_company_id'));
    }

    public function test_switching_company_clears_the_working_branch_and_counter(): void
    {
        // เคาน์เตอร์ที่ลงเวรอยู่เป็นของบริษัทเดิม ถ้าไม่ล้าง ผู้ใช้จะยังยิงบิล
        // เข้าเคาน์เตอร์ของบริษัทที่เขาเพิ่งสลับออกมา
        $second = Company::create(['company_code' => 'TWO', 'company_name' => 'สอง', 'is_active' => true]);
        $this->staffUser->companies()->sync([$this->allowed->id, $second->id]);

        $this->actingAs($this->staffUser)
            ->withSession([
                'active_company_id' => $this->allowed->id,
                'working_branch_id' => $this->branch->id,
                'working_counter_id' => $this->counter->id,
            ])
            ->post(route('switch-company'), ['company_id' => $second->id]);

        $this->assertNull(session('working_branch_id'));
        $this->assertNull(session('working_counter_id'));
    }

    public function test_a_user_with_two_companies_is_offered_the_switcher(): void
    {
        $second = Company::create(['company_code' => 'TWO', 'company_name' => 'บริษัทที่สอง', 'is_active' => true]);
        $this->staffUser->companies()->sync([$this->allowed->id, $second->id]);

        $this->actingAs($this->staffUser)->get('/dashboard')
            ->assertStatus(200)
            ->assertSee(route('switch-company'), false)
            ->assertSee('บริษัทที่สอง');
    }

    public function test_a_user_with_one_company_is_not_shown_a_pointless_dropdown(): void
    {
        $this->staffUser->companies()->sync([$this->allowed->id]);

        $this->actingAs($this->staffUser)->get('/dashboard')
            ->assertStatus(200)
            ->assertDontSee(route('switch-company'), false);
    }
}
