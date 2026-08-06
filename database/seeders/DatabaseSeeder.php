<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            RoleSeeder::class,
            CurrencySeeder::class,
            CounterSeeder::class,
            SettingSeeder::class,
            PermissionSeeder::class,
            MenuSeeder::class,
            AccountSeeder::class,
            CurrencyDenominationSeeder::class,
        ]);

        // Create default admin user
        $adminRole   = \App\Models\Role::where('name', 'admin')->first();
        $defaultBranch = \App\Models\Branch::where('branch_code', 'BKK-HQ')->first();

        User::firstOrCreate(
            ['email' => 'admin@fx.local'],
            [
                'name'      => 'System Admin',
                'password'  => \Illuminate\Support\Facades\Hash::make('Admin@1234'),
                'role_id'   => $adminRole?->id,
                'branch_id' => $defaultBranch?->id,
                'is_active' => true,
            ]
        );

        // Staff user for each branch
        $staffRole = \App\Models\Role::where('name', 'staff')->first();
        \App\Models\Branch::all()->each(function ($branch) use ($staffRole) {
            User::firstOrCreate(
                ['email' => 'staff.' . strtolower(str_replace('-', '', $branch->branch_code)) . '@fx.local'],
                [
                    'name'      => 'Staff ' . $branch->branch_name,
                    'password'  => \Illuminate\Support\Facades\Hash::make('Staff@1234'),
                    'role_id'   => $staffRole?->id,
                    'branch_id' => $branch->id,
                    'is_active' => true,
                ]
            );
        });

        // Branch Manager user (BKK-HQ)
        $branchManagerRole = \App\Models\Role::where('name', 'branch_manager')->first();
        User::firstOrCreate(
            ['email' => 'manager.bkk@fx.local'],
            [
                'name'      => 'Manager ' . ($defaultBranch?->branch_name ?? 'BKK-HQ'),
                'password'  => \Illuminate\Support\Facades\Hash::make('Manager@1234'),
                'role_id'   => $branchManagerRole?->id,
                'branch_id' => $defaultBranch?->id,
                'is_active' => true,
            ]
        );

        // Trader user (manages BKK-HQ, HKT-HQ, BKK-01)
        $traderRole = \App\Models\Role::where('name', 'trader')->first();
        $tradedBranches = \App\Models\Branch::whereIn('branch_code', ['BKK-HQ', 'HKT-HQ', 'BKK-01'])
            ->pluck('id')
            ->toArray();

        User::firstOrCreate(
            ['email' => 'trader.test@fx.local'],
            [
                'name'      => 'Trader Test',
                'password'  => \Illuminate\Support\Facades\Hash::make('Trader@1234'),
                'role_id'   => $traderRole?->id,
                'branch_id' => $defaultBranch?->id, // Primary branch: BKK-HQ
                'managed_branch_ids' => $tradedBranches,
                'is_active' => true,
            ]
        );
    }
}
