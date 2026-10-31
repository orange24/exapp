<?php

namespace Tests\Feature\Admin;

use App\Models\Menu;
use App\Models\Role;
use Database\Seeders\CompanyMenuSeeder;
use Database\Seeders\SuperAdminMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * บริษัท สาขา ผู้ใช้ สิทธิ์ เป็นของ superadmin — admin ไม่เห็นเมนูและเข้าหน้าไม่ได้
 */
class SuperAdminOwnsStructureTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->seed(CompanyMenuSeeder::class);
    }

    private function holders(string $key): array
    {
        return Menu::where('key', $key)->firstOrFail()->roles()->pluck('name')->sort()->values()->all();
    }

    public static function pages(): array
    {
        return [
            'บริษัท' => ['/admin/companies'],
            'สาขา' => ['/admin/branches'],
            'ผู้ใช้' => ['/admin/users'],
            'สิทธิ์' => ['/admin/permissions'],
        ];
    }

    /** @dataProvider pages */
    public function test_admin_is_refused_and_superadmin_gets_in(string $url): void
    {
        $this->actingAsAdmin()->get($url)->assertForbidden();
        $this->actingAsSuperAdmin()->get($url)->assertOk();
    }

    public function test_admin_keeps_the_day_to_day_settings(): void
    {
        $this->actingAsAdmin()->get('/admin/sessions')->assertOk();
        $this->actingAsAdmin()->get(route('admin.daily-limit.edit'))->assertOk();
    }

    public function test_a_fresh_menu_seed_gives_the_menus_to_superadmin_only(): void
    {
        foreach (SuperAdminMenuSeeder::MENU_KEYS as $key) {
            $this->assertSame(['superadmin'], $this->holders($key), $key);
        }
    }

    public function test_the_seeder_takes_the_menus_away_from_admin_on_an_existing_database(): void
    {
        // ฐานข้อมูลเดิมที่ admin ยังถือเมนูอยู่
        $ids = Menu::whereIn('key', SuperAdminMenuSeeder::MENU_KEYS)->pluck('id');
        Role::where('name', 'admin')->first()->menus()->syncWithoutDetaching($ids);
        Role::where('name', 'branch_manager')->first()->menus()->syncWithoutDetaching($ids);

        $this->seed(SuperAdminMenuSeeder::class);

        foreach (SuperAdminMenuSeeder::MENU_KEYS as $key) {
            $this->assertSame(['superadmin'], $this->holders($key), $key);
        }
        $this->assertTrue(Role::where('name', 'admin')->first()->menus()->where('key', 'settings')->exists(), 'admin ยังมีหัวข้อตั้งค่า');
    }

    public function test_the_seeder_does_nothing_until_a_superadmin_user_exists(): void
    {
        $ids = Menu::whereIn('key', SuperAdminMenuSeeder::MENU_KEYS)->pluck('id');
        Role::where('name', 'admin')->first()->menus()->syncWithoutDetaching($ids);
        $this->superAdminUser->update(['is_active' => false]);

        $this->seed(SuperAdminMenuSeeder::class);

        $this->assertContains('admin', $this->holders('admin.users'), 'ไม่มี superadmin — ห้ามถอนเมนูจาก admin ไม่งั้นไม่มีใครจัดการผู้ใช้ได้');
    }
}
