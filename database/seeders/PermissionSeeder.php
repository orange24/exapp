<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Create all permissions for modules 1-6 with actions: read, write, print, export, delete.
     * Also assign default permissions to admin role.
     */
    public function run(): void
    {
        $modules = [
            'module1' => 'จัดการผู้ใช้ (User Management)',
            'module2' => 'ข้อมูลหลัก (Master Data)',
            'module3' => 'ธุรกรรม (Transaction Engine)',
            'module4' => 'บัญชี (Accounting Ledgers)',
            'module5' => 'แดชบอร์ด (Dashboard & Inventory)',
            'module6' => 'รายงาน (Compliance & Reporting)',
        ];

        $actions = ['read', 'write', 'print', 'export', 'delete'];

        foreach ($modules as $module => $description) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(
                    ['module' => $module, 'action' => $action],
                    ['description' => "{$description} — {$action}"]
                );
            }
        }

        // Assign all permissions to admin role
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $allPermissions = Permission::all();
            foreach ($allPermissions as $perm) {
                RolePermission::firstOrCreate([
                    'role_id'       => $adminRole->id,
                    'permission_id' => $perm->id,
                ]);
            }
        }

        // Staff: read + write + print for modules 2, 3, 5
        $staffRole = Role::where('name', 'staff')->first();
        if ($staffRole) {
            $staffPerms = Permission::whereIn('module', ['module2', 'module3', 'module5'])
                ->whereIn('action', ['read', 'write', 'print'])
                ->get();
            foreach ($staffPerms as $perm) {
                RolePermission::firstOrCreate([
                    'role_id'       => $staffRole->id,
                    'permission_id' => $perm->id,
                ]);
            }
        }

        // Auditor: read + print + export for all modules
        $auditorRole = Role::where('name', 'auditor')->first();
        if ($auditorRole) {
            $auditorPerms = Permission::whereIn('action', ['read', 'print', 'export'])->get();
            foreach ($auditorPerms as $perm) {
                RolePermission::firstOrCreate([
                    'role_id'       => $auditorRole->id,
                    'permission_id' => $perm->id,
                ]);
            }
        }
    }
}
