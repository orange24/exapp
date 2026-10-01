<?php

use App\Models\Permission;
use App\Models\RolePermission;
use Database\Seeders\SanctionPermissionSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new SanctionPermissionSeeder())->run();
    }

    public function down(): void
    {
        $ids = Permission::where('module', SanctionPermissionSeeder::MODULE)->pluck('id');

        RolePermission::whereIn('permission_id', $ids)->delete();
        Permission::whereIn('id', $ids)->delete();
    }
};
