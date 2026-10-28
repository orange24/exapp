<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * เมนูหน้าจัดการบริษัท
 *
 * วางใต้ "ตั้งค่า" เหนือ "จัดการสาขา" เพราะบริษัทเป็นเจ้าของสาขา — คนที่เข้า
 * มาตั้งค่าระบบครั้งแรกต้องสร้างบริษัทก่อนถึงจะมีที่ให้สาขาไปสังกัด
 *
 * ให้เมนูตามสิทธิ์ module1/write จริง ไม่ใช่ระบุชื่อ role ไว้ตายตัว เมนูกับ
 * ประตูจึงตรงกันเสมอ (หน้านี้กั้นด้วย middleware ชุดเดียวกับจัดการสาขา)
 */
class CompanyMenuSeeder extends Seeder
{
    public function run(): void
    {
        $parent = Menu::where('key', 'settings')->first();

        if ($parent === null) {
            return;
        }

        $menu = Menu::firstOrCreate(
            ['key' => 'admin.companies'],
            [
                'label_th' => 'จัดการบริษัท',
                'label_en' => 'Companies',
                'route' => 'admin.companies.index',
                'icon' => 'building-office-2',
                'parent_id' => $parent->id,
                // 0 เพื่อให้อยู่เหนือ "จัดการสาขา" (order 1) โดยไม่ต้องขยับของเดิม
                'order' => 0,
            ]
        );

        $permission = Permission::where('module', 'module1')->where('action', 'write')->first();

        if ($permission === null) {
            return;
        }

        $roles = Role::whereHas('permissions', fn ($q) => $q->whereKey($permission->id))->get();

        foreach ($roles as $role) {
            // ต้องผูกหัวข้อ "ตั้งค่า" ด้วย ไม่งั้นเมนูลูกไม่ถูก render เลย
            $role->menus()->syncWithoutDetaching([$parent->id, $menu->id]);
        }

        Menu::flushAccessCache();
    }
}
