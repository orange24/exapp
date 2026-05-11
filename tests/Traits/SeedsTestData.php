<?php

namespace Tests\Traits;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\CurrencyDenomination;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;

trait SeedsTestData
{
    protected User $adminUser;
    protected User $staffUser;
    protected Branch $branch;
    protected Counter $counter;
    protected Counter $counter2;
    protected Currency $currency;
    protected CurrencyDenomination $denomination;

    protected function seedTestData(): void
    {
        // Roles
        $adminRole = Role::create(['name' => 'admin', 'display_name' => 'Admin']);
        $staffRole = Role::create(['name' => 'staff', 'display_name' => 'Staff']);

        // Branches
        $this->branch = Branch::create([
            'branch_code' => 'BKK-HQ',
            'branch_name' => 'HQ Bangkok',
            'type' => 'hq',
            'city' => 'Bangkok',
            'is_active' => true,
        ]);

        // Counters
        $this->counter = Counter::create([
            'counter_code' => 'BKK-C1',
            'counter_name' => 'Counter 1',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->counter2 = Counter::create([
            'counter_code' => 'BKK-C2',
            'counter_name' => 'Counter 2',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // Currency + Denomination
        $this->currency = Currency::create([
            'currency_code' => 'USD',
            'currency_name' => 'US Dollar',
            'country' => 'US',
            'is_active' => true,
            'seq' => 1,
        ]);

        $this->denomination = CurrencyDenomination::create([
            'currency_code' => 'USD',
            'denom_label' => '100-50',
            'display_name' => 'USD 100-50',
            'seq' => 1,
        ]);

        // Settings
        Setting::create(['setting_key' => 'WORKING_CUT_OFF', 'setting_value' => '03:00:00']);

        // Users
        $this->adminUser = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('password'),
            'role_id' => $adminRole->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->staffUser = User::create([
            'name' => 'Staff',
            'email' => 'staff@test.local',
            'password' => bcrypt('password'),
            'role_id' => $staffRole->id,
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    protected function actingAsAdmin()
    {
        return $this->actingAs($this->adminUser)
            ->withSession([
                'working_counter_id' => $this->counter->id,
                'working_counter_name' => $this->counter->counter_name,
            ]);
    }

    protected function actingAsStaff()
    {
        return $this->actingAs($this->staffUser)
            ->withSession([
                'working_counter_id' => $this->counter->id,
                'working_counter_name' => $this->counter->counter_name,
            ]);
    }
}
