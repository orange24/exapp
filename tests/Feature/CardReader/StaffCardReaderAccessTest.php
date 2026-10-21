<?php

namespace Tests\Feature\CardReader;

use App\Models\Branch;
use App\Models\CardReaderDevice;
use App\Models\Counter;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * staff ตั้งเครื่องอ่านบัตรที่เคาน์เตอร์ตัวเองได้เอง ไม่ต้องรอ admin
 *
 * แต่สาขาถูกล็อกไว้ที่สาขาที่เขาประจำการ/ลงเวรอยู่ — เลือกสาขาอื่นไม่ได้
 * ทั้งใน dropdown และเมื่อยิงค่า counter_id ของสาขาอื่นเข้ามาตรงๆ
 */
class StaffCardReaderAccessTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Branch $otherBranch;
    private Counter $otherCounter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        // ถ้าไม่ seed สิทธิ์ชุดหลัก module1 จะไม่มีอยู่เลย แล้ว seeder เมนู
        // จะเงียบออกไปก่อนผูก role — เทสจะเขียวโดยไม่ได้ตรวจอะไร
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\CardReaderMenuSeeder::class);

        $this->otherBranch = Branch::create([
            'branch_code' => 'HKT-01',
            'branch_name' => 'สาขาภูเก็ต',
            'type' => 'branch',
            'city' => 'Phuket',
            'is_active' => true,
        ]);

        $this->otherCounter = Counter::create([
            'branch_id' => $this->otherBranch->id,
            'counter_name' => 'เคาน์เตอร์ภูเก็ต',
            'counter_code' => 'HKT-C1',
            'is_active' => true,
        ]);
    }

    public function test_staff_sees_the_card_reader_menu_under_settings(): void
    {
        $menus = $this->staffUser->getAccessibleMenus();

        // ต้องได้ทั้งหัวข้อ "ตั้งค่า" และลูกของมัน ไม่งั้นลูกไม่ถูก render เลย
        $this->assertTrue($menus->contains('key', 'settings'), 'staff ไม่เห็นหัวข้อ ตั้งค่า เมนูลูกจึงไม่โผล่');
        $this->assertTrue($menus->contains('key', 'card-readers.index'), 'staff ไม่เห็นเมนูเครื่องอ่านบัตร');
    }

    public function test_the_menu_link_renders_in_the_staff_sidebar(): void
    {
        $this->actingAsStaff()->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('เครื่องอ่านบัตรประชาชน')
            ->assertSee(route('card-readers.index'), false);
    }

    public function test_staff_can_open_the_card_reader_page(): void
    {
        $this->actingAsStaff()->get(route('card-readers.index'))->assertStatus(200);
    }

    public function test_an_auditor_is_still_refused(): void
    {
        $auditor = User::create([
            'name' => 'ผู้ตรวจสอบ',
            'email' => 'auditor-card-reader@test.local',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'auditor')->firstOrFail()->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($auditor)->get(route('card-readers.index'))->assertStatus(403);
    }

    public function test_staff_is_only_offered_counters_in_their_own_branch(): void
    {
        $counters = Livewire::actingAs($this->staffUser)
            ->test('card-reader.device-manager')
            ->instance()->counters;

        $this->assertTrue($counters->every(fn ($c) => $c->branch_id === $this->branch->id));
        $this->assertFalse($counters->contains('id', $this->otherCounter->id));
    }

    public function test_the_page_shows_the_locked_branch_and_no_other(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test('card-reader.device-manager')
            ->assertSee($this->branch->branch_name)
            ->assertDontSee($this->otherBranch->branch_name);
    }

    public function test_the_counter_dropdown_starts_on_the_working_counter(): void
    {
        // staff มีเคาน์เตอร์ที่ลงเวรอยู่แล้ว ไม่ต้องให้เลือกซ้ำ
        $this->actingAs($this->staffUser)->withSession(['working_counter_id' => $this->counter2->id]);

        Livewire::test('card-reader.device-manager')
            ->assertSet('counterId', (string) $this->counter2->id);
    }

    public function test_staff_can_register_a_device_at_their_own_counter(): void
    {
        Livewire::actingAs($this->staffUser)
            ->test('card-reader.device-manager')
            ->set('name', 'เครื่องเคาน์เตอร์ 1')
            ->set('counterId', (string) $this->counter->id)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame($this->counter->id, CardReaderDevice::firstOrFail()->counter_id);
    }

    public function test_staff_cannot_bind_a_device_to_another_branch(): void
    {
        // รายการใน dropdown ไม่ใช่การควบคุมสิทธิ์ — ค่าที่ส่งมาแก้ได้จากฝั่งผู้ใช้
        Livewire::actingAs($this->staffUser)
            ->test('card-reader.device-manager')
            ->set('name', 'เครื่องแอบผูก')
            ->set('counterId', (string) $this->otherCounter->id)
            ->call('create')
            ->assertHasErrors('counterId');

        $this->assertSame(0, CardReaderDevice::count());
    }

    public function test_staff_does_not_see_or_revoke_another_branchs_device(): void
    {
        $device = CardReaderDevice::create([
            'name' => 'เครื่องสาขาภูเก็ต',
            'counter_id' => $this->otherCounter->id,
            'token_hash' => CardReaderDevice::hashToken('crd_other'),
        ]);

        $page = Livewire::actingAs($this->staffUser)->test('card-reader.device-manager');

        $this->assertCount(0, $page->instance()->devices);

        $page->call('revoke', $device->id);

        $this->assertNull($device->refresh()->revoked_at);
    }

    public function test_staff_can_revoke_a_device_at_their_own_branch(): void
    {
        $device = CardReaderDevice::create([
            'name' => 'เครื่องเคาน์เตอร์ 1',
            'counter_id' => $this->counter->id,
            'token_hash' => CardReaderDevice::hashToken('crd_own'),
        ]);

        Livewire::actingAs($this->staffUser)
            ->test('card-reader.device-manager')
            ->call('revoke', $device->id);

        $this->assertNotNull($device->refresh()->revoked_at);
    }

    /**
     * staff ที่ไปลงเวรสาขาอื่น (sidebar "สาขาทำงาน") ต้องตั้งเครื่องที่สาขานั้น
     * เพราะเครื่องอ่านตั้งอยู่ตรงหน้าเขาจริง ไม่ใช่ที่สาขาที่เขาถูกจ้างมา
     */
    public function test_the_locked_branch_follows_the_working_branch(): void
    {
        $this->actingAs($this->staffUser)->withSession([
            'working_branch_id' => $this->otherBranch->id,
            'working_counter_id' => $this->otherCounter->id,
        ]);

        $page = Livewire::test('card-reader.device-manager');

        $counters = $page->instance()->counters;

        $this->assertTrue($counters->contains('id', $this->otherCounter->id));
        $this->assertFalse($counters->contains('id', $this->counter->id));
    }
}
