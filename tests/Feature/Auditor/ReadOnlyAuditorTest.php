<?php

namespace Tests\Feature\Auditor;

use App\Exceptions\ReadOnlyViolation;
use App\Livewire\User\ChangePassword;
use App\Models\Customer;
use App\Models\Menu;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuditorMenuSeeder;
use Database\Seeders\BotSubmissionMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * ผู้ตรวจสอบสองระดับ — ระดับอ่านอย่างเดียวเห็นทุกอย่างเท่า auditor แต่แก้อะไรไม่ได้
 * รวมถึงหน้าแก้ไขที่จะเพิ่มให้ auditor ในอนาคตด้วย
 */
class ReadOnlyAuditorTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private User $auditor;

    private User $readOnly;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(AuditorMenuSeeder::class);
        $this->seed(BotSubmissionMenuSeeder::class);

        $this->auditor = User::create([
            'name' => 'Auditor', 'email' => 'auditor@test.local', 'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'auditor')->value('id'), 'is_active' => true,
        ]);

        $this->readOnly = User::create([
            'name' => 'Read Only', 'email' => 'readonly@test.local', 'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'auditor_readonly')->value('id'), 'is_active' => true,
        ]);
    }

    public function test_the_role_exists_after_migrating(): void
    {
        // migration ต้องสร้างเอง — seeder ไม่รันตอน deploy
        $this->assertSame('ผู้ตรวจสอบ (อ่านอย่างเดียว)', Role::where('name', 'auditor_readonly')->value('display_name'));
    }

    public function test_it_sees_exactly_the_auditor_menus(): void
    {
        $this->assertEqualsCanonicalizing(
            $this->auditor->getAccessibleMenus()->pluck('key')->all(),
            $this->readOnly->getAccessibleMenus()->pluck('key')->all(),
        );
        $this->assertTrue($this->readOnly->hasMenuAccess('reports.bot-submission'));
    }

    public function test_a_menu_given_to_auditor_later_reaches_it_too(): void
    {
        $menu = Menu::create(['key' => 'future.page', 'label_th' => 'หน้าใหม่', 'label_en' => 'New', 'order' => 99]);
        Role::where('name', 'auditor')->first()->menus()->attach($menu->id);
        Menu::flushAccessCache();

        $this->assertTrue($this->readOnly->fresh()->hasMenuAccess('future.page'));
    }

    public function test_it_keeps_auditor_read_permissions_but_never_a_writing_one(): void
    {
        // ถึงวันหนึ่งจะมีคนให้ auditor แก้อะไรได้ ระดับอ่านอย่างเดียวต้องไม่ได้ตาม
        $write = Permission::firstOrCreate(['module' => 'module2', 'action' => 'write']);
        Role::where('name', 'auditor')->first()->permissions()->syncWithoutDetaching([$write->id]);

        $this->assertTrue($this->auditor->hasPermission('module2', 'write'));
        $this->assertFalse($this->readOnly->hasPermission('module2', 'write'));
        $this->assertSame(
            $this->auditor->hasPermission('module2', 'read'),
            $this->readOnly->hasPermission('module2', 'read'),
        );
    }

    public function test_any_write_to_business_data_is_refused(): void
    {
        $this->actingAs($this->readOnly);

        $this->expectException(ReadOnlyViolation::class);

        Customer::create(['type' => 'individual', 'id_type' => 'passport', 'id_number' => 'X1']);
    }

    public function test_raw_query_builder_writes_are_refused_too(): void
    {
        $this->actingAs($this->readOnly);

        $this->expectException(ReadOnlyViolation::class);

        DB::table('settings')->where('setting_key', 'WORKING_CUT_OFF')->update(['setting_value' => '04:00:00']);
    }

    public function test_a_normal_auditor_is_not_restricted(): void
    {
        $this->actingAs($this->auditor);

        Customer::create(['type' => 'individual', 'id_type' => 'passport', 'id_number' => 'X2']);

        $this->assertDatabaseHas('customers', ['id_number' => 'X2']);
    }

    public function test_a_refused_write_answers_403_not_500(): void
    {
        Route::post('/_test/readonly-write', function () {
            Customer::create(['type' => 'individual', 'id_type' => 'passport', 'id_number' => 'X3']);
        })->middleware('web');

        $this->actingAs($this->readOnly)->post('/_test/readonly-write')
            ->assertForbidden()
            ->assertSee('อ่านได้อย่างเดียว');
    }

    public function test_it_can_log_in_and_out(): void
    {
        $this->post(route('login'), ['email' => 'readonly@test.local', 'password' => 'password', 'remember' => 1])
            ->assertRedirect();
        $this->assertAuthenticatedAs($this->readOnly);

        $this->post(route('logout'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_it_can_change_its_own_password(): void
    {
        $this->actingAs($this->readOnly);

        Livewire::test(ChangePassword::class)
            ->set('current_password', 'password')
            ->set('new_password', 'NewPass@1234')
            ->set('confirm_password', 'NewPass@1234')
            ->call('changePassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('NewPass@1234', $this->readOnly->fresh()->password));
    }

    public function test_every_page_on_its_menu_opens(): void
    {
        // เปิดหน้าก็มีการเขียนเบื้องหลัง (session, ประวัติการเข้าใช้) — ต้องไม่โดนกันจนหน้าพัง
        $routes = $this->readOnly->getAccessibleMenus()->pluck('route')->filter()
            ->filter(fn ($name) => Route::has($name) && count(Route::getRoutes()->getByName($name)->parameterNames()) === 0);

        $this->assertNotEmpty($routes);

        foreach ($routes as $name) {
            $status = $this->actingAs($this->readOnly)->get(route($name))->getStatusCode();
            $this->assertLessThan(500, $status, "หน้า {$name} พัง ({$status})");
        }
    }
}
