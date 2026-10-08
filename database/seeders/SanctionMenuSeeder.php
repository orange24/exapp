<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * เมนูของระบบตรวจรายชื่อบุคคลที่ถูกกำหนด
 *
 * แยกออกมาเป็น seeder แทนที่จะเขียนไว้ใน migration ตรง ๆ เพราะใน test suite
 * migration รันก่อนที่ `SeedsTestData` จะสร้าง role — migration จึงหา role ไม่เจอ
 * และผูกเมนูให้ role ไม่ได้เลย (เหมือนที่ `SanctionPermissionSeeder` เจอ)
 * migration เรียก seeder ตัวนี้, test ก็เรียกซ้ำได้เพราะทุกขั้นเป็น idempotent
 */
class SanctionMenuSeeder extends Seeder
{
    /**
     * สิทธิ์ module7 ของ branch_manager คือ read+approve — คิวตรวจสอบจึงเปิดให้ด้วย
     * trader ไม่มีสิทธิ์ module7 เลย ห้ามใส่ (จะได้ลิงก์ที่กดแล้ว 403)
     *
     * @var array<int, string>
     */
    private const REVIEW_ROLES = ['admin', 'superadmin', 'branch_manager', 'auditor'];

    /**
     * auditor มี read+export ตรงกับงานดึงรายงานส่ง ปปง.
     *
     * @var array<int, string>
     */
    private const REPORT_ROLES = ['admin', 'superadmin', 'auditor'];

    /** @var array<int, array{0: string, 1: string, 2: string, 3: int}> */
    public const REPORT_MENUS = [
        ['reports.sanction-screening-log', 'บันทึกการตรวจรายชื่อ', 'Screening Log', 20],
        ['reports.sanction-decisions', 'การอนุมัติเมื่อพบชื่อใกล้เคียง', 'Screening Decisions', 21],
        ['reports.sanction-rescan', 'ลูกค้าเดิมที่กลายเป็นชื่อต้องห้าม', 'Rescan Findings', 22],
        ['reports.sanction-list-delta', 'การเปลี่ยนแปลงรายชื่อ ปปง.', 'List Changes', 23],
        ['reports.sanction-sync-health', 'สุขภาพการอัปเดตรายชื่อ', 'Sync Health', 24],
        ['reports.sanction-coverage-gap', 'ธุรกรรมที่ไม่ได้ตรวจรายชื่อ', 'Coverage Gap', 25],
    ];

    public function run(): void
    {
        $this->seedReviewMenu();
        $this->seedReportMenus();
    }

    public function seedReviewMenu(): void
    {
        // order 8 — ท้ายสุดของ sidebar. order 5 ตามแผนเดิมชนกับเมนู "คลังสินค้า"
        // ที่ MenuSeeder จองไว้แล้ว ทำให้ลำดับสองเมนูนี้สลับกันไปมา
        $parent = Menu::firstOrCreate(
            ['key' => 'sanctions'],
            [
                'label_th' => 'รายชื่อบุคคลต้องห้าม',
                'label_en' => 'Sanction Screening',
                'route' => null,
                'icon' => 'shield-check',
                'parent_id' => null,
                'order' => 8,
            ]
        );

        $review = Menu::firstOrCreate(
            ['key' => 'sanctions.review'],
            [
                'label_th' => 'รายการรอตรวจสอบ',
                'label_en' => 'Review Queue',
                'route' => 'sanctions.review',
                'icon' => 'clipboard-list',
                'parent_id' => $parent->id,
                'order' => 1,
            ]
        );

        // หน้าสั่งอัปเดตรายชื่อให้เฉพาะคนที่มี module7/write (superadmin, admin)
        // branch_manager กับ staff เห็นแล้วกดไม่ได้ จะกลายเป็นลิงก์ที่เด้ง 403
        $sync = Menu::firstOrCreate(
            ['key' => 'sanctions.sync'],
            [
                'label_th' => 'อัปเดตรายชื่อ ปปง.',
                'label_en' => 'Update AMLO List',
                'route' => 'sanctions.sync',
                'icon' => 'arrow-path',
                'parent_id' => $parent->id,
                'order' => 2,
            ]
        );

        $this->attach(self::REVIEW_ROLES, [$parent, $review]);
        $this->attach(self::SYNC_ROLES, [$sync]);
    }

    /** เฉพาะ role ที่ถือ module7/write จริง ตาม SanctionPermissionSeeder */
    private const SYNC_ROLES = ['superadmin', 'admin'];

    public function seedReportMenus(): void
    {
        $parent = Menu::firstOrCreate(
            ['key' => 'reports'],
            [
                'label_th' => 'รายงาน',
                'label_en' => 'Reports',
                'route' => null,
                'icon' => 'document-chart-bar',
                'parent_id' => null,
                'order' => 4,
            ]
        );

        $menus = [$parent];

        foreach (self::REPORT_MENUS as [$key, $labelTh, $labelEn, $order]) {
            $menus[] = Menu::firstOrCreate(
                ['key' => $key],
                [
                    'label_th' => $labelTh,
                    'label_en' => $labelEn,
                    'route' => $key,
                    'icon' => 'document-text',
                    'parent_id' => $parent->id,
                    'order' => $order,
                ]
            );
        }

        $this->attach(self::REPORT_ROLES, $menus);
    }

    /**
     * @param  array<int, string>  $roleNames
     * @param  array<int, Menu>  $menus
     */
    private function attach(array $roleNames, array $menus): void
    {
        $menuIds = array_map(fn (Menu $m): int => $m->id, $menus);

        Role::whereIn('name', $roleNames)->get()->each(
            // syncWithoutDetaching กัน unique key ของ menu_role ชนตอนรันซ้ำ
            fn (Role $role) => $role->menus()->syncWithoutDetaching($menuIds)
        );

        Menu::flushAccessCache();
    }
}
