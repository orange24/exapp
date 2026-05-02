<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            ['branch_code' => 'BKK-HQ',  'branch_name' => 'สำนักงานใหญ่ กรุงเทพฯ', 'type' => 'hq',     'city' => 'Bangkok'],
            ['branch_code' => 'HKT-HQ',  'branch_name' => 'สำนักงานใหญ่ ภูเก็ต',   'type' => 'hq',     'city' => 'Phuket'],
            ['branch_code' => 'BKK-01',  'branch_name' => 'สาขา สุวรรณภูมิ',        'type' => 'branch', 'city' => 'Bangkok'],
            ['branch_code' => 'HKT-01',  'branch_name' => 'สาขา ป่าตอง',            'type' => 'branch', 'city' => 'Phuket'],
            ['branch_code' => 'HKT-02',  'branch_name' => 'สาขา กะตะ',              'type' => 'branch', 'city' => 'Phuket'],
        ];
        foreach ($branches as $b) {
            \App\Models\Branch::firstOrCreate(['branch_code' => $b['branch_code']], array_merge($b, ['is_active' => true]));
        }
    }
}
