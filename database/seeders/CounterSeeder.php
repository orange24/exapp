<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CounterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $counters = [
            ['code' => 'BKK-C1', 'name' => 'เคาน์เตอร์ 1 (BKK)',     'branch_code' => 'BKK-HQ'],
            ['code' => 'BKK-C2', 'name' => 'เคาน์เตอร์ 2 (BKK)',     'branch_code' => 'BKK-HQ'],
            ['code' => 'HKT-C1', 'name' => 'เคาน์เตอร์ 1 (Phuket)',  'branch_code' => 'HKT-HQ'],
            ['code' => 'HKT-C2', 'name' => 'เคาน์เตอร์ 2 (Phuket)',  'branch_code' => 'HKT-HQ'],
            ['code' => 'SUV-C1', 'name' => 'เคาน์เตอร์ สุวรรณภูมิ', 'branch_code' => 'BKK-01'],
            ['code' => 'PAT-C1', 'name' => 'เคาน์เตอร์ ป่าตอง',     'branch_code' => 'HKT-01'],
        ];
        foreach ($counters as $c) {
            $branch = \App\Models\Branch::where('branch_code', $c['branch_code'])->first();
            if ($branch) {
                \App\Models\Counter::firstOrCreate(
                    ['counter_code' => $c['code']],
                    ['counter_name' => $c['name'], 'branch_id' => $branch->id, 'is_active' => true]
                );
            }
        }
    }
}
