<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * เมนู "วงเงินต่อคนต่อวัน" ใต้หัวข้อตั้งค่า
 *
 * ให้เฉพาะ admin/superadmin — หน้านี้กั้น
 * ด้วย middleware admin ชุดเดียวกับจัดการสาขา เมนูกับประตูจึงตรงกัน
 *
 * รันซ้ำได้ ถ้ามี role อื่นถือเมนูนี้อยู่จะถูกถอนออก (เข้าหน้าไม่ได้อยู่แล้ว)
 */
class DailyLimitMenuSeeder extends Seeder
{
    public function run(): void
    {
        $parent = Menu::where('key', 'settings')->first();

        if ($parent === null) {
            return;
        }

        $menu = Menu::firstOrCreate(
            ['key' => 'admin.daily-limit'],
            [
                'label_th' => 'วงเงินต่อคนต่อวัน',
                'label_en' => 'Daily Limit',
                'route' => 'admin.daily-limit.edit',
                'icon' => 'banknotes',
                'parent_id' => $parent->id,
                'order' => 6,
            ]
        );

        // หน้านี้ผ่าน middleware admin (User::isAdmin) — role อื่นที่มี module1/write
        // เช่น branch_manager จะเห็นเมนูแล้วกดเข้าไปเจอ 403 จึงแจกตาม role ที่เข้าได้จริง
        $adminRoles = ['admin', 'superadmin'];

        foreach (Role::whereIn('name', $adminRoles)->get() as $role) {
            // ต้องผูกหัวข้อ "ตั้งค่า" ด้วย ไม่งั้นเมนูลูกไม่ถูก render เลย
            $role->menus()->syncWithoutDetaching([$parent->id, $menu->id]);
        }

        // รอบก่อนเคยแจกตาม module1/write — ถอนเมนูนี้ (เมนูเดียว) ออกจาก role ที่เข้าไม่ได้
        foreach (Role::whereNotIn('name', $adminRoles)->get() as $role) {
            $role->menus()->detach($menu->id);
        }

        Menu::flushAccessCache();
    }
}
