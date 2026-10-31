<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * เมนูหน้าจัดการบริษัท
 *
 * วางใต้ "ตั้งค่า" เหนือ "จัดการสาขา" เพราะบริษัทเป็นเจ้าของสาขา — คนที่เข้า
 * มาตั้งค่าระบบครั้งแรกต้องสร้างบริษัทก่อนถึงจะมีที่ให้สาขาไปสังกัด
 *
 * ให้เมนูเฉพาะ superadmin ตรงกับ middleware ที่กั้นหน้านี้ (ชุดเดียวกับ
 * จัดการสาขา) — ถอนเมนูออกจาก role อื่นทำที่ SuperAdminMenuSeeder
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

        // หน้านี้กั้นด้วย middleware superadmin — ให้เมนูเฉพาะ superadmin
        $role = Role::where('name', 'superadmin')->first();

        // ต้องผูกหัวข้อ "ตั้งค่า" ด้วย ไม่งั้นเมนูลูกไม่ถูก render เลย
        $role?->menus()->syncWithoutDetaching([$parent->id, $menu->id]);

        Menu::flushAccessCache();
    }
}
