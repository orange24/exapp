<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Setting;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            ['key' => 'BOT_INSTITUTION_CODE', 'value' => '', 'desc' => 'รหัสสถาบันผู้ส่งข้อมูล (เลขทะเบียนนิติบุคคล 13 หลัก)'],
            ['key' => 'BOT_LICENSE_NO', 'value' => '', 'desc' => 'เลขที่ใบอนุญาต ธปท. (เช่น MC125990001)'],
            ['key' => 'BOT_COMPANY_NAME', 'value' => '', 'desc' => 'ชื่อบุคคลรับอนุญาต (ชื่อบริษัท)'],
        ];

        foreach ($settings as $s) {
            Setting::firstOrCreate(
                ['setting_key' => $s['key']],
                ['setting_value' => $s['value'], 'description' => $s['desc']]
            );
        }
    }

    public function down(): void
    {
        Setting::whereIn('setting_key', ['BOT_INSTITUTION_CODE', 'BOT_LICENSE_NO', 'BOT_COMPANY_NAME'])->delete();
    }
};
