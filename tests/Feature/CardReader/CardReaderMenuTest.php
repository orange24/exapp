<?php

namespace Tests\Feature\CardReader;

use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class CardReaderMenuTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        // trait ไม่ได้ seed สิทธิ์ชุดหลัก ถ้าไม่เรียกเอง module1 จะไม่มีอยู่เลย
        // แล้วเทสจะเขียวโดยที่ไม่ได้ตรวจอะไร เพราะไม่มี role ไหนถือสิทธิ์ให้เทียบ
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\CardReaderMenuSeeder::class);
    }

    public function test_the_menu_exists_and_its_route_resolves(): void
    {
        $menu = Menu::where('key', 'card-readers.index')->first();

        $this->assertNotNull($menu, 'ไม่มีเมนู ผู้ดูแลจะหาหน้านี้ไม่เจอ ต้องพิมพ์ URL เอง');
        $this->assertSame('settings', Menu::find($menu->parent_id)?->key);

        // ลิงก์ที่ชี้ไป route ที่ไม่มีอยู่จะพังตอน render sidebar ทั้งแถบ
        $this->assertTrue(\Illuminate\Support\Facades\Route::has($menu->route));
    }

    public function test_no_role_is_shown_a_link_it_would_be_refused(): void
    {
        $menu = Menu::where('key', 'card-readers.index')->firstOrFail();

        foreach (Role::with('menus')->get() as $role) {
            $sees = $role->menus->contains('id', $menu->id);

            $user = new User(['role_id' => $role->id]);
            $user->setRelation('role', $role);

            $allowed = $user->hasPermission('module1', 'write');

            $this->assertSame(
                $allowed,
                $sees,
                "role {$role->name}: เห็นเมนู=" . var_export($sees, true)
                    . " แต่สิทธิ์=" . var_export($allowed, true),
            );
        }
    }
}
