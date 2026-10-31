<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * เมนู "ส่งข้อมูลให้แบงค์ชาติ" ใต้หัวข้อรายงาน
 *
 * เป็นงานส่งหน่วยงานกำกับ ไม่ใช่งานหน้าเคาน์เตอร์ จึงให้เฉพาะคนที่ดูแล
 * ภาพรวมของสาขา — เปลี่ยนภายหลังได้ที่หน้าตั้งสิทธิ์ ตัวหน้าเช็คสิทธิ์จาก
 * เมนูนี้ (hasMenuAccess) เมนูกับประตูจึงตรงกันเสมอ
 *
 * รันซ้ำได้ ไม่ลบสิทธิ์ที่ปรับไว้แล้ว
 */
class BotSubmissionMenuSeeder extends Seeder
{
    private const ROLES = ['superadmin', 'admin', 'branch_manager', 'auditor'];

    public function run(): void
    {
        $parent = Menu::where('key', 'reports')->first();

        if ($parent === null) {
            return;
        }

        $menu = Menu::firstOrCreate(
            ['key' => 'reports.bot-submission'],
            [
                'label_th' => 'ส่งข้อมูลให้แบงค์ชาติ',
                'label_en' => 'BOT Submission',
                'route' => 'reports.bot-submission',
                'icon' => 'building-library',
                'parent_id' => $parent->id,
                'order' => 20,
            ]
        );

        foreach (Role::whereIn('name', self::ROLES)->get() as $role) {
            // ต้องผูกหัวข้อ "รายงาน" ด้วย ไม่งั้นเมนูลูกไม่ถูก render
            $role->menus()->syncWithoutDetaching([$parent->id, $menu->id]);
        }

        Menu::flushAccessCache();
    }
}
