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
 * branch_manager ถือ module1/write เหมือน admin จึงเข้าหน้านี้ได้
 * แต่ต้องยุ่งได้เฉพาะเครื่องในสาขาตัวเอง — ไม่งั้นจะผูกเครื่องเข้ากับ
 * เคาน์เตอร์สาขาอื่นแล้วยิงข้อมูลบัตรไปโผล่ที่หน้าจอของสาขานั้น
 */
class DeviceManagerScopeTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Branch $otherBranch;
    private Counter $otherCounter;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $this->otherBranch = Branch::create([
            'branch_code' => 'OTHER',
            'branch_name' => 'สาขาอื่น',
            'is_active' => true,
        ]);

        $this->otherCounter = Counter::create([
            'branch_id' => $this->otherBranch->id,
            'counter_name' => 'เคาน์เตอร์สาขาอื่น',
            'counter_code' => 'OTHER-C1',
            'is_active' => true,
        ]);

        $this->manager = User::create([
            'name' => 'ผู้จัดการสาขา',
            'email' => 'scope-manager@test.local',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'branch_manager')->firstOrFail()->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_a_branch_manager_is_only_offered_counters_in_their_own_branch(): void
    {
        $counters = Livewire::actingAs($this->manager)
            ->test('card-reader.device-manager')
            ->instance()->counters;

        $this->assertTrue($counters->every(fn ($c) => $c->branch_id === $this->branch->id));
        $this->assertFalse($counters->contains('id', $this->otherCounter->id));
    }

    public function test_a_branch_manager_cannot_bind_a_device_to_another_branch(): void
    {
        // รายการใน dropdown ไม่ใช่การควบคุมสิทธิ์ — ค่าที่ส่งมาแก้ได้จากฝั่งผู้ใช้
        Livewire::actingAs($this->manager)
            ->test('card-reader.device-manager')
            ->set('name', 'เครื่องแอบผูก')
            ->set('counterId', (string) $this->otherCounter->id)
            ->call('create')
            ->assertHasErrors('counterId');

        $this->assertSame(0, CardReaderDevice::count());
    }

    public function test_a_branch_manager_can_register_a_device_in_their_own_branch(): void
    {
        Livewire::actingAs($this->manager)
            ->test('card-reader.device-manager')
            ->set('name', 'เครื่องสาขาตัวเอง')
            ->set('counterId', (string) $this->counter->id)
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame(1, CardReaderDevice::count());
    }

    public function test_a_branch_manager_does_not_see_another_branchs_devices(): void
    {
        CardReaderDevice::create([
            'name' => 'เครื่องสาขาอื่น',
            'counter_id' => $this->otherCounter->id,
            'token_hash' => CardReaderDevice::hashToken('crd_other'),
        ]);

        $devices = Livewire::actingAs($this->manager)
            ->test('card-reader.device-manager')
            ->instance()->devices;

        $this->assertCount(0, $devices);
    }

    public function test_a_branch_manager_cannot_revoke_another_branchs_device(): void
    {
        $device = CardReaderDevice::create([
            'name' => 'เครื่องสาขาอื่น',
            'counter_id' => $this->otherCounter->id,
            'token_hash' => CardReaderDevice::hashToken('crd_other'),
        ]);

        Livewire::actingAs($this->manager)
            ->test('card-reader.device-manager')
            ->call('revoke', $device->id);

        $this->assertNull($device->refresh()->revoked_at);
    }

    public function test_an_admin_still_sees_every_branch(): void
    {
        CardReaderDevice::create([
            'name' => 'เครื่องสาขาอื่น',
            'counter_id' => $this->otherCounter->id,
            'token_hash' => CardReaderDevice::hashToken('crd_other'),
        ]);

        $page = Livewire::actingAs($this->adminUser)->test('card-reader.device-manager');

        $this->assertTrue($page->instance()->counters->contains('id', $this->otherCounter->id));
        $this->assertCount(1, $page->instance()->devices);
    }
}
