<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * เมนูของผู้ตรวจสอบ (auditor)
 *
 * - เพิ่ม "เรททุกสาขา" ใต้อัตราแลกเปลี่ยน — ดูเรทวันนี้ของทุกสาขาที่มีสิทธิ์
 * - ถอนกลุ่ม "ข้อมูลหลัก" ออก — หน้าสกุลเงิน/ลูกค้า/ผังบัญชีมีปุ่มแก้ไข
 *   และตอนนี้ route ผูกกับเมนูแล้ว (middleware menu:) ถอนเมนูคือปิดประตูด้วย
 *
 * รันซ้ำได้
 */
class AuditorMenuSeeder extends Seeder
{
    /** role ที่ได้เมนูเรททุกสาขา */
    public const OVERVIEW_ROLES = ['superadmin', 'admin', 'auditor'];

    public const MASTER_DATA_KEYS = ['master-data', 'admin.currencies', 'admin.customers', 'admin.accounts'];

    public function run(): void
    {
        $parent = Menu::where('key', 'rates')->first();

        if ($parent === null) {
            return;
        }

        $menu = Menu::firstOrCreate(
            ['key' => 'rates.overview'],
            [
                'label_th' => 'เรททุกสาขา',
                'label_en' => 'Rate Overview',
                'route' => 'rates.overview',
                'icon' => 'currency-dollar',
                'parent_id' => $parent->id,
                'order' => 5,
            ]
        );

        foreach (Role::whereIn('name', self::OVERVIEW_ROLES)->get() as $role) {
            // ต้องผูกหัวข้อ "อัตราแลกเปลี่ยน" ด้วย ไม่งั้นเมนูลูกไม่ถูก render เลย
            $role->menus()->syncWithoutDetaching([$parent->id, $menu->id]);
        }

        Role::where('name', 'auditor')->first()?->menus()->detach(
            Menu::whereIn('key', self::MASTER_DATA_KEYS)->pluck('id')
        );

        Menu::flushAccessCache();
    }
}
