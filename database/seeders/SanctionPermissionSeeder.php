<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Database\Seeder;

class SanctionPermissionSeeder extends Seeder
{
    public const MODULE = 'module7';

    private const DESCRIPTION = 'ตรวจรายชื่อบุคคลต้องห้าม (Sanction Screening)';

    public function run(): void
    {
        // actions มาตรฐานเหมือน module อื่น
        foreach (['read', 'write', 'print', 'export', 'delete'] as $action) {
            Permission::firstOrCreate(
                ['module' => self::MODULE, 'action' => $action],
                ['description' => self::DESCRIPTION . " — {$action}"]
            );
        }

        // approve สร้างนอก loop โดยตั้งใจ — ถ้าใส่เข้าไปใน $actions ของ PermissionSeeder
        // มันจะไปงอก approve ให้ครบทั้ง 6 module เดิมที่ไม่มีความหมาย
        Permission::firstOrCreate(
            ['module' => self::MODULE, 'action' => 'approve'],
            ['description' => self::DESCRIPTION . ' — approve (อนุมัติทำรายการต่อเมื่อพบชื่อใกล้เคียง)']
        );

        $grants = [
            'superadmin' => ['read', 'write', 'print', 'export', 'delete', 'approve'],
            'admin' => ['read', 'write', 'print', 'export', 'delete', 'approve'],
            'branch_manager' => ['read', 'approve'],
            'auditor' => ['read', 'export'],
            'staff' => ['read'],
            // trader ไม่ได้สิทธิ์อะไรเลย — ไม่ได้อยู่หน้าเคาน์เตอร์
        ];

        foreach ($grants as $roleName => $actions) {
            $role = Role::where('name', $roleName)->first();

            if ($role === null) {
                continue;
            }

            foreach ($actions as $action) {
                $permission = Permission::where('module', self::MODULE)->where('action', $action)->first();

                if ($permission === null) {
                    continue;
                }

                RolePermission::firstOrCreate([
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                ]);
            }
        }
    }
}
