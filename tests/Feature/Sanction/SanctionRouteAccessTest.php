<?php

namespace Tests\Feature\Sanction;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * รายงานกลุ่มนี้แสดงเอกสารแสดงตนของลูกค้าคู่กับผลการตรวจรายชื่อบุคคลต้องห้าม
 * การซ่อนเมนูไม่ใช่การควบคุมสิทธิ์ — ต้องกันที่ route ด้วย
 */
class SanctionRouteAccessTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    /** @return array<int, string> */
    private function guardedRoutes(): array
    {
        return [
            'reports.sanction-screening-log',
            'reports.sanction-decisions',
            'reports.sanction-rescan',
            'reports.sanction-list-delta',
            'reports.sanction-sync-health',
            'reports.sanction-coverage-gap',
            'sanctions.review',
        ];
    }

    public function test_a_role_without_module7_read_is_refused_every_sanction_page(): void
    {
        $trader = User::create([
            'name' => 'Trader',
            'email' => 'route-trader@test.local',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'trader')->firstOrFail()->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->assertFalse($trader->hasPermission('module7', 'read'));

        foreach ($this->guardedRoutes() as $name) {
            $this->actingAs($trader)->get(route($name))->assertForbidden();
        }
    }

    public function test_admin_can_reach_every_sanction_page(): void
    {
        foreach ($this->guardedRoutes() as $name) {
            $this->actingAs($this->adminUser)->get(route($name))->assertOk();
        }
    }

    public function test_staff_can_read_but_auditor_can_export(): void
    {
        $this->assertTrue($this->staffUser->hasPermission('module7', 'read'));

        $this->actingAs($this->staffUser)
            ->get(route('reports.sanction-screening-log'))
            ->assertOk();
    }

    public function test_excel_exports_are_guarded_too(): void
    {
        $trader = User::create([
            'name' => 'Trader Export',
            'email' => 'route-trader-export@test.local',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'trader')->firstOrFail()->id,
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($trader)
            ->post(route('reports.sanction-screening-log.export'))
            ->assertForbidden();

        $this->actingAs($trader)
            ->post(route('reports.sanction-decisions.export'))
            ->assertForbidden();
    }
}
