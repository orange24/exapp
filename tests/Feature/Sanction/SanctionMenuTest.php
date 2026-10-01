<?php

namespace Tests\Feature\Sanction;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\SanctionMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ฟีเจอร์ที่หาไม่เจอก็เท่ากับไม่มี — ผู้ตรวจ ปปง. ขอ "บันทึกการตรวจรายชื่อ"
 * แล้วต้องมีคนเปิดให้ได้โดยไม่ต้องบอก URL
 *
 * เมนูที่ชี้ route ผิดชื่อจะถูก `$menuUrl` ใน layout กลืน exception ทิ้ง
 * ลิงก์จะหายไปเงียบ ๆ ไม่มีใครรู้ — เทสนี้เป็นที่เดียวที่จับได้
 */
class SanctionMenuTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    /** @return array<int, string> */
    private function expectedKeys(): array
    {
        return array_merge(
            ['sanctions', 'sanctions.review'],
            array_column(SanctionMenuSeeder::REPORT_MENUS, 0)
        );
    }

    public function test_every_sanction_menu_row_exists_and_is_active(): void
    {
        foreach ($this->expectedKeys() as $key) {
            $menu = Menu::where('key', $key)->first();

            $this->assertNotNull($menu, "ไม่พบเมนู {$key}");
            $this->assertTrue($menu->is_active, "เมนู {$key} ถูกปิดอยู่");
            $this->assertNotSame('', $menu->label_th, "เมนู {$key} ไม่มีชื่อไทย");
        }
    }

    public function test_the_review_queue_menu_points_at_the_review_route(): void
    {
        $parent = Menu::where('key', 'sanctions')->firstOrFail();
        $review = Menu::where('key', 'sanctions.review')->firstOrFail();

        $this->assertNull($parent->route, 'เมนูหัวข้อไม่ควรมี route');
        $this->assertSame('sanctions.review', $review->route);
        $this->assertSame($parent->id, $review->parent_id);
    }

    public function test_the_six_report_menus_hang_under_the_reports_parent(): void
    {
        $reports = Menu::where('key', 'reports')->firstOrFail();

        foreach (SanctionMenuSeeder::REPORT_MENUS as [$key, $labelTh, $labelEn, $order]) {
            $menu = Menu::where('key', $key)->firstOrFail();

            $this->assertSame($key, $menu->route, "เมนู {$key} ชี้ route ผิด");
            $this->assertSame($reports->id, $menu->parent_id, "เมนู {$key} ไม่ได้อยู่ใต้ 'รายงาน'");
            $this->assertSame($labelTh, $menu->label_th);
            $this->assertSame($order, $menu->order);
        }
    }

    public function test_every_menu_route_name_actually_resolves(): void
    {
        $routed = Menu::whereIn('key', $this->expectedKeys())->whereNotNull('route')->get();

        $this->assertCount(7, $routed, 'คาดว่ามีเมนูที่มี route 7 รายการ');

        foreach ($routed as $menu) {
            $this->assertTrue(
                Route::has($menu->route),
                "เมนู {$menu->key} ชี้ route '{$menu->route}' ที่ไม่มีอยู่จริง"
            );

            // route() ต้องสร้าง URL ได้โดยไม่ต้องส่งพารามิเตอร์
            $this->assertNotSame('', route($menu->route));
        }
    }

    public function test_the_expected_roles_hold_the_sanction_menus(): void
    {
        $expectations = [
            'sanctions' => ['admin', 'superadmin', 'branch_manager', 'auditor'],
            'sanctions.review' => ['admin', 'superadmin', 'branch_manager', 'auditor'],
        ];

        foreach (array_column(SanctionMenuSeeder::REPORT_MENUS, 0) as $key) {
            $expectations[$key] = ['admin', 'superadmin', 'auditor'];
        }

        foreach ($expectations as $key => $roleNames) {
            $attached = Menu::where('key', $key)->firstOrFail()
                ->roles()->pluck('name')->sort()->values()->all();

            foreach ($roleNames as $roleName) {
                $this->assertContains($roleName, $attached, "role {$roleName} ควรเห็นเมนู {$key}");
            }

            $this->assertNotContains('trader', $attached, "trader ไม่มีสิทธิ์ module7 แต่เห็นเมนู {$key}");
        }
    }

    public function test_no_role_is_shown_a_sanction_menu_it_would_be_refused(): void
    {
        $menus = Menu::whereIn('key', $this->expectedKeys())->whereNotNull('route')->get();

        foreach (Role::all() as $role) {
            $visible = $menus->filter(
                fn (Menu $m): bool => $m->roles()->where('roles.id', $role->id)->exists()
            );

            if ($visible->isEmpty()) {
                continue;
            }

            $user = User::create([
                'name' => "Probe {$role->name}",
                'email' => "menu-probe-{$role->name}@test.local",
                'password' => bcrypt('password'),
                'role_id' => $role->id,
                'branch_id' => $this->branch->id,
                'is_active' => true,
            ]);

            foreach ($visible as $menu) {
                // 403 ที่นี่หมายถึงเมนูโฆษณาหน้าที่ role นั้นเปิดไม่ได้
                $this->actingAs($user)
                    ->get(route($menu->route))
                    ->assertStatus(200);
            }
        }
    }

    public function test_the_admin_sidebar_links_to_the_review_queue_and_the_screening_log(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('sanctions.review'), false)
            ->assertSee(route('reports.sanction-screening-log'), false)
            ->assertSee('รายชื่อบุคคลต้องห้าม');
    }
}
