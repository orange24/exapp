<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * ย้ายเมนูโครงสร้างระบบ — จัดการบริษัท สาขา ผู้ใช้ สิทธิ์ — ไปเป็นของ superadmin
 *
 * หน้าพวกนี้กั้นด้วย middleware superadmin แล้ว role อื่นที่ยังถือเมนูอยู่จะ
 * เห็นลิงก์แล้วกดเข้าไปเจอ 403 จึงถอนออกให้ตรงกับประตู
 *
 * หยุดโดยไม่แตะอะไรถ้ายังไม่มี superadmin ที่ใช้งานได้ — ไม่งั้นจะไม่มีใคร
 * เหลือเข้าหน้าจัดการผู้ใช้ไปตั้ง superadmin ได้อีก
 *
 * รันซ้ำได้
 */
class SuperAdminMenuSeeder extends Seeder
{
    public const MENU_KEYS = ['admin.companies', 'admin.branches', 'admin.users', 'admin.permissions'];

    public function run(): void
    {
        $superadmin = Role::where('name', 'superadmin')->first();

        $hasSuperAdmin = $superadmin !== null
            && User::where('role_id', $superadmin->id)->where('is_active', true)->exists();

        if (! $hasSuperAdmin) {
            $this->command?->error('ยังไม่มีผู้ใช้ role superadmin ที่ใช้งานได้ — ไม่ได้ย้ายเมนู ตั้ง superadmin ก่อนแล้วรันใหม่');

            return;
        }

        $parent = Menu::where('key', 'settings')->first();
        $menuIds = Menu::whereIn('key', self::MENU_KEYS)->pluck('id')->all();

        if ($parent === null || $menuIds === []) {
            return;
        }

        // ต้องผูกหัวข้อ "ตั้งค่า" ด้วย ไม่งั้นเมนูลูกไม่ถูก render เลย
        $superadmin->menus()->syncWithoutDetaching([$parent->id, ...$menuIds]);

        foreach (Role::whereKeyNot($superadmin->id)->get() as $role) {
            $role->menus()->detach($menuIds);
        }

        Menu::flushAccessCache();
    }
}
