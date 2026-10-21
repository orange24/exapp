<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * เมนูหน้าจัดการเครื่องอ่านบัตรประชาชน
 *
 * วางใต้ "ตั้งค่า" เพราะเป็นการจัดการอุปกรณ์ ไม่ใช่งานประจำวันของเคาน์เตอร์
 *
 * ให้เมนูตาม "ใครเข้าหน้านี้ได้จริง" ไม่ใช่ระบุชื่อ role ไว้ตายตัว — ถามคำถาม
 * เดียวกับที่ middleware ถามตอนเปิดหน้า (User::canManageCardReaders()) เมนู
 * กับประตูจึงตรงกันเสมอ ตอนแรกผมเขียนเป็น superadmin กับ admin แล้วพบว่า role
 * superadmin ไม่มีสิทธิ์ module1 สักอย่างเลย — วันที่มีใครสร้างผู้ใช้
 * superadmin ขึ้นมา เขาจะเห็นเมนูแล้วกดเจอ 403
 */
class CardReaderMenuSeeder extends Seeder
{
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

        foreach (Role::with('permissions')->get() as $role) {
            if (! $this->roleCanManageCardReaders($role)) {
                continue;
            }

            // ต้องผูกหัวข้อ "ตั้งค่า" ด้วย ไม่งั้นเมนูลูกไม่ถูก render เลย
            $role->menus()->syncWithoutDetaching([$parent->id, $menu->id]);
        }

        Menu::flushAccessCache();
    }

    /**
     * ถาม User::canManageCardReaders() โดยไม่ต้องมีผู้ใช้จริงใน role นั้น
     *
     * role ที่ยังไม่มีใครใช้ก็ต้องได้เมนูไว้ล่วงหน้า เพราะ seeder นี้รันตอน
     * deploy ไม่ใช่ตอนสร้างผู้ใช้
     */
    private function roleCanManageCardReaders(Role $role): bool
    {
        $probe = new User(['role_id' => $role->id]);
        $probe->setRelation('role', $role);

        return $probe->canManageCardReaders();
    }
}
