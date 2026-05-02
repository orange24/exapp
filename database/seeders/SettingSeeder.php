<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'WORKING_CUT_OFF',    'value' => '03:00:00',  'desc' => 'เวลาตัดรอบวันทำงาน'],
            ['key' => 'DOC_PREFIX_BUY',     'value' => 'B',         'desc' => 'คำนำหน้าเลขที่เอกสารซื้อ'],
            ['key' => 'DOC_PREFIX_SELL',    'value' => 'S',         'desc' => 'คำนำหน้าเลขที่เอกสารขาย'],
            ['key' => 'COMPANY_NAME',       'value' => 'FX Exchange Co., Ltd.', 'desc' => 'ชื่อบริษัท'],
            ['key' => 'AMLA_THRESHOLD_THB', 'value' => '2000000',   'desc' => 'วงเงิน AMLA (THB)'],
            ['key' => 'AMLA_THRESHOLD_USD', 'value' => '50000',     'desc' => 'วงเงิน AMLA (USD equivalent)'],
        ];
        foreach ($settings as $s) {
            \App\Models\Setting::firstOrCreate(
                ['setting_key' => $s['key']],
                ['setting_value' => $s['value'], 'description' => $s['desc']]
            );
        }
    }
}
