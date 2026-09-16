<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * เลิกใช้แนวคิด "คลังเงินบาท"
 *
 * ตอนออกแบบเลือกให้การเติมเงินทุน/ส่งคืนตัดสองขา (คลังลด เคาน์เตอร์เพิ่ม)
 * ซึ่งต้องระบุว่าเคาน์เตอร์ไหนเป็นคลัง แต่ในงานจริงเงินบาทเข้าลิ้นชักตรงจาก
 * บัญชีธนาคาร/เจ้าของ ไม่ได้ผ่านคลังกลางที่ไหน — หลักเดียวกับที่การซื้อ/ขาย
 * กับธนาคารไม่ถูกนับในบัญชีเงินสดเคาน์เตอร์ เพราะจ่าย-รับผ่านบัญชี
 *
 * จึงเปลี่ยนเป็นตัดขาเดียว และ setting นี้ไม่มีใครอ่านอีก — ลบทิ้งเพื่อไม่ให้
 * เหลือค่า config ที่ดูเหมือนต้องตั้งแต่ไม่มีผลอะไร
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::where('setting_key', 'THB_WAREHOUSE_COUNTER_ID')->delete();
    }

    public function down(): void
    {
        Setting::firstOrCreate(
            ['setting_key' => 'THB_WAREHOUSE_COUNTER_ID'],
            [
                'setting_value' => '',
                'description' => 'เคาน์เตอร์คลังกลางสำหรับเงินบาท (ต้นทางเติมเงินทุน / ปลายทางส่งคืน)',
            ]
        );
    }
};
