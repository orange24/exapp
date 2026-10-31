<?php

namespace Tests\Feature\Auditor;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * auditor ตรวจสอบทุกสาขา จึงไม่ได้ "ประจำ" สาขาไหน
 *
 * เดิม auditor ถูกบังคับให้มีสาขาเหมือนพนักงานเคาน์เตอร์ ผลคือเห็นข้อมูล
 * แค่สาขาเดียว ทั้งที่หน้าที่คือดูให้ครบทุกสาขา และ sidebar ยังขึ้น
 * "เคาน์เตอร์ทำงาน" ให้ด้วย ทั้งที่ auditor ไม่ได้นั่งเคาน์เตอร์
 */
class AuditorIsNotPostedToABranchTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private User $auditor;
    private Branch $otherBranch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $this->otherBranch = Branch::create([
            'branch_code' => 'HKT-01',
            'branch_name' => 'สาขาภูเก็ต',
            'is_active' => true,
        ]);

        $this->auditor = User::create([
            'name' => 'ผู้ตรวจสอบ',
            'email' => 'auditor-scope@test.local',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'auditor')->firstOrFail()->id,
            'branch_id' => null,
            'is_active' => true,
        ]);
    }

    public function test_an_auditor_sees_every_branch(): void
    {
        $visible = $this->auditor->getVisibleBranchIds();

        $this->assertContains($this->branch->id, $visible);
        $this->assertContains($this->otherBranch->id, $visible, 'auditor ต้องตรวจได้ทุกสาขา ไม่ใช่สาขาเดียว');
        $this->assertCount(Branch::count(), $visible);
    }

    /** สาขายังว่างได้ แม้ไม่ได้ผูกสาขาก็ต้องไม่พังตอนคำนวณขอบเขต */
    public function test_an_auditor_without_a_branch_still_sees_every_branch(): void
    {
        $this->assertNull($this->auditor->branch_id);
        $this->assertCount(Branch::count(), $this->auditor->getVisibleBranchIds());
    }

    public function test_an_auditor_is_not_shown_a_working_counter(): void
    {
        $this->assertFalse(
            $this->auditor->worksAtACounter(),
            'auditor ไม่ได้นั่งเคาน์เตอร์ sidebar จึงไม่ควรขึ้น "เคาน์เตอร์ทำงาน"',
        );

        // ตรวจที่ฟอร์มเปลี่ยนเคาน์เตอร์ ไม่ใช่ที่คำว่า "เคาน์เตอร์ทำงาน" เฉย ๆ
        // เพราะคำนั้นไปโผล่ในคอมเมนต์ของบล็อก <script> ด้วย ซึ่งไม่ใช่ UI
        $this->actingAs($this->auditor)->get('/dashboard')
            ->assertStatus(200)
            ->assertDontSee(route('switch-counter'), false)
            ->assertDontSee('เลือกเคาน์เตอร์ทำงาน');
    }

    public function test_the_counter_staff_still_get_their_working_counter(): void
    {
        // กันแก้เกินตัว — คนที่นั่งเคาน์เตอร์จริงต้องไม่หายไปด้วย
        $this->assertTrue($this->staffUser->worksAtACounter());
        $this->assertTrue($this->adminUser->worksAtACounter());

        $this->actingAsStaff()->get('/dashboard')
            ->assertSee(route('switch-counter'), false)
            ->assertSee('เลือกเคาน์เตอร์ทำงาน');
    }

    public function test_an_auditor_can_be_created_without_a_branch(): void
    {
        $this->actingAsSuperAdmin()->post(route('admin.users.store'), [
            'name' => 'ผู้ตรวจสอบคนใหม่',
            'email' => 'auditor-new@test.local',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role_id' => Role::where('name', 'auditor')->firstOrFail()->id,
            'is_active' => 1,
        ])->assertSessionHasNoErrors();

        $created = User::where('email', 'auditor-new@test.local')->firstOrFail();

        $this->assertNull($created->branch_id);
    }

    public function test_counter_roles_still_must_have_a_branch(): void
    {
        // auditor ไม่ต้องมีสาขา ไม่ได้แปลว่าใครก็ไม่ต้องมี — staff ที่ไม่มีสาขา
        // จะไม่มีเคาน์เตอร์ให้ลงเวรและใช้งานหน้าซื้อ/ขายไม่ได้เลย
        $this->actingAsSuperAdmin()->post(route('admin.users.store'), [
            'name' => 'พนักงานไร้สาขา',
            'email' => 'staff-no-branch@test.local',
            'password' => 'Password@123',
            'password_confirmation' => 'Password@123',
            'role_id' => Role::where('name', 'staff')->firstOrFail()->id,
            'is_active' => 1,
        ])->assertSessionHasErrors('branch_ids');
    }
}
