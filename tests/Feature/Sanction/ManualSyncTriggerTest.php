<?php

namespace Tests\Feature\Sanction;

use App\Models\Role;
use App\Models\SanctionSyncRun;
use App\Models\User;
use App\Services\Sanction\SyncTrigger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * หน้าสั่งอัปเดตรายชื่อ ปปง. ด้วยมือ
 *
 * URL นี้ไปยิงเซิร์ฟเวอร์หน่วยงานราชการและเขียนทับรายชื่อทั้งชุด
 * จึงต้องกันทั้งเรื่องสิทธิ์และเรื่องการสั่งซ้อนกัน
 */
class ManualSyncTriggerTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function userWithRole(string $role, string $email): User
    {
        return User::create([
            'name' => $role,
            'email' => $email,
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', $role)->firstOrFail()->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    /** ตัวสั่งงานปลอม — เทสต้องไม่ยิงเซิร์ฟเวอร์ ปปง. จริง */
    private function fakeTrigger(): SyncTrigger
    {
        $fake = new class extends SyncTrigger
        {
            public int $calls = 0;

            public function start(int $userId): array
            {
                $this->calls++;

                return ['ok' => true, 'message' => 'เริ่มแล้ว'];
            }
        };

        $this->app->instance(SyncTrigger::class, $fake);

        return $fake;
    }

    public function test_a_guest_cannot_reach_the_sync_page(): void
    {
        $this->get(route('sanctions.sync'))->assertRedirect(route('login'));
    }

    public function test_a_role_with_only_read_cannot_trigger_a_sync(): void
    {
        $fake = $this->fakeTrigger();
        $staff = $this->userWithRole('staff', 'sync-staff@test.local');

        $this->assertTrue($staff->hasPermission('module7', 'read'));
        $this->assertFalse($staff->hasPermission('module7', 'write'));

        $this->actingAs($staff)->get(route('sanctions.sync', ['action' => 'update']))->assertForbidden();

        // สำคัญกว่าสถานะ 403 คือต้องไม่มีการยิงออกไปจริง
        $this->assertSame(0, $fake->calls);
    }

    public function test_an_admin_can_open_the_page_and_trigger_a_sync(): void
    {
        $fake = $this->fakeTrigger();
        $admin = $this->userWithRole('admin', 'sync-admin@test.local');

        $this->actingAs($admin)->get(route('sanctions.sync'))->assertOk();
        $this->assertSame(0, $fake->calls, 'เปิดหน้าเฉย ๆ ต้องไม่สั่งงาน');

        $this->actingAs($admin)
            ->get(route('sanctions.sync', ['action' => 'update']))
            ->assertRedirect(route('sanctions.sync'));

        $this->assertSame(1, $fake->calls);
    }

    public function test_the_trigger_url_redirects_so_a_refresh_does_not_fire_it_again(): void
    {
        $this->fakeTrigger();
        $admin = $this->userWithRole('admin', 'sync-redirect@test.local');

        $response = $this->actingAs($admin)->get(route('sanctions.sync', ['action' => 'update']));

        // ปลายทางต้องไม่มี action=update ติดไปด้วย ไม่งั้นกด refresh คือสั่งซ้ำ
        $this->assertSame(route('sanctions.sync'), $response->headers->get('Location'));
    }

    public function test_a_sync_already_running_is_not_started_again(): void
    {
        SanctionSyncRun::create([
            'list_code' => 'freeze_05_th',
            'started_at' => now()->subMinutes(5),
            'finished_at' => null,
            'status' => 'running',
            'source_adapter' => 'amlo_public',
        ]);

        $trigger = app(SyncTrigger::class);
        $result = $trigger->start(1);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('กำลังทำงานอยู่', $result['message']);
    }

    public function test_a_run_abandoned_longer_than_the_job_timeout_no_longer_blocks(): void
    {
        SanctionSyncRun::create([
            'list_code' => 'freeze_05_th',
            'started_at' => now()->subMinutes(SyncTrigger::STALE_AFTER_MINUTES + 1),
            'finished_at' => null,
            'status' => 'running',
            'source_adapter' => 'amlo_public',
        ]);

        // container ที่ถูกฆ่ากลางทางไม่เคยเขียน finished_at
        // ถ้าไม่มีกำหนดหมดอายุ แถวนั้นจะล็อกไม่ให้ใครสั่งอัปเดตได้อีกเลยตลอดกาล
        $this->assertNull(app(SyncTrigger::class)->inProgress());
    }

    public function test_a_finished_run_does_not_block(): void
    {
        SanctionSyncRun::create([
            'list_code' => 'freeze_05_th',
            'started_at' => now()->subMinutes(5),
            'finished_at' => now()->subMinutes(2),
            'status' => 'success',
            'source_adapter' => 'amlo_public',
        ]);

        $this->assertNull(app(SyncTrigger::class)->inProgress());
    }
}
