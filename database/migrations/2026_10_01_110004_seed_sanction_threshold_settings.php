<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Setting::updateOrCreate(
            ['setting_key' => 'sanction_threshold_potential'],
            ['setting_value' => '70', 'description' => 'คะแนนขั้นต่ำที่ถือว่าเป็นชื่อใกล้เคียง ต้องอนุมัติก่อนทำรายการ']
        );

        Setting::updateOrCreate(
            ['setting_key' => 'sanction_threshold_red'],
            ['setting_value' => '85', 'description' => 'คะแนนขั้นต่ำที่แสดงแถบเตือนสีแดง (ต่ำกว่านี้เป็นสีส้ม)']
        );
    }

    public function down(): void
    {
        Setting::whereIn('setting_key', [
            'sanction_threshold_potential',
            'sanction_threshold_red',
        ])->delete();
    }
};
