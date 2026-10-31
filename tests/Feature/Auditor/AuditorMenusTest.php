<?php

namespace Tests\Feature\Auditor;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterRate;
use App\Models\Menu;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AuditorMenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * auditor ไม่เห็นข้อมูลหลัก แต่ดูเรททุกสาขาได้ — และหน้าข้อมูลหลักต้องกั้นที่ประตูจริง
 */
class AuditorMenusTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
        $this->seed(AuditorMenuSeeder::class);

        $this->auditor = User::create([
            'name' => 'Auditor', 'email' => 'auditor@test.local', 'password' => bcrypt('password'),
            'role_id' => Role::where('name', 'auditor')->value('id'), 'is_active' => true,
        ]);
    }

    public static function masterDataPages(): array
    {
        return [
            'สกุลเงิน' => ['/admin/currencies'],
            'ลูกค้า' => ['/admin/customers'],
            'ผังบัญชี' => ['/admin/accounts'],
        ];
    }

    /** @dataProvider masterDataPages */
    public function test_auditor_cannot_open_master_data(string $url): void
    {
        $this->actingAs($this->auditor)->get($url)->assertForbidden();
    }

    /** @dataProvider masterDataPages */
    public function test_staff_cannot_open_master_data_by_typing_the_url(string $url): void
    {
        $this->actingAsStaff()->get($url)->assertForbidden();
    }

    public function test_staff_cannot_edit_a_currency_by_posting_directly(): void
    {
        $this->actingAsStaff()->patch(route('admin.currencies.toggle-active', $this->currency))->assertForbidden();
        $this->assertTrue($this->currency->fresh()->is_active);
    }

    public function test_the_seeder_takes_master_data_away_from_an_existing_auditor(): void
    {
        $ids = Menu::whereIn('key', AuditorMenuSeeder::MASTER_DATA_KEYS)->pluck('id');
        Role::where('name', 'auditor')->first()->menus()->syncWithoutDetaching($ids);

        $this->seed(AuditorMenuSeeder::class);

        $this->assertFalse($this->auditor->fresh()->hasMenuAccess('master-data'));
        $this->assertTrue($this->adminUser->fresh()->hasMenuAccess('admin.currencies'), 'admin ยังเห็นเหมือนเดิม');
    }

    public function test_auditor_sees_todays_rates_of_every_branch(): void
    {
        $other = Branch::create(['branch_code' => 'PKT', 'branch_name' => 'ภูเก็ต', 'is_active' => true]);
        $otherCounter = Counter::create(['counter_code' => 'PKT-C1', 'counter_name' => 'PKT 1', 'branch_id' => $other->id, 'is_active' => true]);

        foreach ([[$this->counter, 33.11, 33.55], [$otherCounter, 32.77, 33.99]] as [$counter, $buy, $sell]) {
            CounterRate::create([
                'counter_id' => $counter->id, 'currency_code' => 'USD', 'denomination_id' => $this->denomination->id,
                'rate_date' => now()->toDateString(), 'rate_buy' => $buy, 'rate_sell' => $sell,
            ]);
        }

        $this->assertTrue($this->auditor->hasMenuAccess('rates.overview'));

        $this->actingAs($this->auditor)->get(route('rates.overview'))
            ->assertOk()
            ->assertSee('ภูเก็ต')
            ->assertSee('33.11')
            ->assertSee('32.77');

        $this->actingAs($this->auditor)->get(route('rates.overview', ['branch_id' => $other->id]))
            ->assertOk()
            ->assertSee('32.77')
            ->assertDontSee('33.11');
    }

    public function test_staff_does_not_get_the_overview(): void
    {
        $this->actingAsStaff()->get(route('rates.overview'))->assertForbidden();
    }
}
