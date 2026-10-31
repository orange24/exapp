<?php

namespace Tests\Feature\Transaction;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * การ์ดรับซื้อ/จ่ายขายบนหน้าหลัก และตัวหน้าซื้อ/ขาย ต้องขึ้นตามสิทธิ์เมนู
 *
 * เดิมการ์ดขึ้นให้ทุก role รวมถึง auditor ที่ไม่มีเมนูธุรกรรมเลย และพิมพ์
 * URL ตรงก็เข้าหน้าซื้อ/ขายได้
 */
class BuySellFollowsMenuAccessTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $this->auditor = User::create([
            'name' => 'ผู้ตรวจ',
            'email' => 'auditor-buysell@test.local',
            'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'auditor')->firstOrFail()->id,
            'is_active' => true,
        ]);
    }

    public function test_a_role_without_the_menu_sees_no_buy_sell_cards(): void
    {
        $this->actingAs($this->auditor)->get('/dashboard')
            ->assertStatus(200)
            ->assertDontSee(route('transaction.buy'), false)
            ->assertDontSee(route('transaction.sell'), false);
    }

    public function test_a_role_without_the_menu_cannot_open_the_pages_directly(): void
    {
        $this->actingAs($this->auditor)->get(route('transaction.buy'))->assertForbidden();
        $this->actingAs($this->auditor)->get(route('transaction.sell'))->assertForbidden();
    }

    public function test_staff_still_get_both_cards(): void
    {
        $this->actingAsStaff()->get('/dashboard')
            ->assertSee(route('transaction.buy'), false)
            ->assertSee(route('transaction.sell'), false);
    }

    public function test_the_cards_follow_the_permission_screen(): void
    {
        // เอาเมนูจ่ายขายออกจาก staff ที่หน้าตั้งสิทธิ์ → การ์ดจ่ายขายต้องหายตาม
        $staffRole = Role::where('name', 'staff')->firstOrFail();
        $staffRole->menus()->detach(Menu::where('key', 'transaction.sell')->value('id'));
        Menu::flushAccessCache();

        $this->actingAsStaff()->get('/dashboard')
            ->assertSee(route('transaction.buy'), false)
            ->assertDontSee(route('transaction.sell'), false);

        $this->actingAsStaff()->get(route('transaction.sell'))->assertForbidden();
    }
}
