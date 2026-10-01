<?php

namespace Tests\Feature\Sanction;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class SanctionPermissionTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function userWithRole(string $roleName): User
    {
        $role = Role::where('name', $roleName)->firstOrFail();

        // seedTestData() already owns admin@test.local and staff@test.local —
        // reusing those addresses here violates the users.email unique index
        return User::create([
            'name' => "test {$roleName}",
            'email' => "sanction-{$roleName}@test.local",
            'password' => bcrypt('secret'),
            'role_id' => $role->id,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_branch_manager_can_approve(): void
    {
        $this->assertTrue($this->userWithRole('branch_manager')->hasPermission('module7', 'approve'));
    }

    public function test_admin_can_approve(): void
    {
        $this->assertTrue($this->userWithRole('admin')->hasPermission('module7', 'approve'));
    }

    public function test_staff_cannot_approve(): void
    {
        $staff = $this->userWithRole('staff');

        $this->assertTrue($staff->hasPermission('module7', 'read'));
        $this->assertFalse($staff->hasPermission('module7', 'approve'));
    }

    public function test_auditor_cannot_approve_but_can_export(): void
    {
        $auditor = $this->userWithRole('auditor');

        $this->assertFalse($auditor->hasPermission('module7', 'approve'));
        $this->assertTrue($auditor->hasPermission('module7', 'export'));
    }

    public function test_trader_has_no_sanction_permissions(): void
    {
        $trader = $this->userWithRole('trader');

        $this->assertFalse($trader->hasPermission('module7', 'read'));
        $this->assertFalse($trader->hasPermission('module7', 'approve'));
    }
}
