<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * เมนูหน้าจัดการเครื่องอ่านบัตรประชาชน
 *
 * วางใต้ "ตั้งค่า" เพราะเป็นการจัดการอุปกรณ์ ไม่ใช่งานประจำวันของเคาน์เตอร์
 *
 * ให้เมนูตาม "ใครถือสิทธิ์ module1/write จริง" ไม่ใช่ระบุชื่อ role ไว้ตายตัว
 * ตอนแรกผมเขียนเป็น superadmin กับ admin แล้วพบว่า role superadmin
 * ไม่มีสิทธิ์ module1 สักอย่างเลย และ hasPermission() ก็ไม่มีทางลัดให้
 * superadmin ด้วย — วันที่มีใครสร้างผู้ใช้ superadmin ขึ้นมา เขาจะเห็นเมนู
 * แล้วกดเจอ 403 การอ่านจากสิทธิ์จริงทำให้เมนูกับประตูตรงกันเสมอ
 */
class CardReaderMenuSeeder extends Seeder
{
    private const MODULE = 'module1';
    private const ACTION = 'write';

    public function run(): void
    {
        $parent = Menu::where('key', 'settings')->first();

        if ($parent === null) {
            return;
        }

        $menu = Menu::firstOrCreate(
            ['key' => 'card-readers.index'],
            [
                'label_th' => 'เครื่องอ่านบัตรประชาชน',
                'label_en' => 'ID Card Readers',
                'route' => 'card-readers.index',
                'icon' => 'identification',
                'parent_id' => $parent->id,
                'order' => 5,
            ]
        );

        $permission = Permission::where('module', self::MODULE)->where('action', self::ACTION)->first();

        if ($permission === null) {
            return;
        }

        $roles = Role::whereHas('permissions', fn ($q) => $q->whereKey($permission->id))->get();

        foreach ($roles as $role) {
            $role->menus()->syncWithoutDetaching([$parent->id, $menu->id]);
        }

        Menu::flushAccessCache();
    }
}
