<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'superadmin', 'display_name' => 'Super Admin'],
            ['name' => 'admin',      'display_name' => 'ผู้ดูแลระบบ'],
            ['name' => 'staff',      'display_name' => 'พนักงาน'],
            ['name' => 'auditor',    'display_name' => 'ผู้ตรวจสอบ'],
        ];
        foreach ($roles as $r) {
            \App\Models\Role::firstOrCreate(['name' => $r['name']], $r);
        }
    }
}
