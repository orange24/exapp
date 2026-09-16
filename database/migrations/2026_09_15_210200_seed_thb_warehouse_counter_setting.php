<?php

use App\Models\Setting;
use Illuminate\Database\Migrations\Migration;

/**
 * เคาน์เตอร์ที่ทำหน้าที่เป็น "คลัง" สำหรับเงินบาท — ปลายทางของการส่งบาทคืน
 * และต้นทางของการเติมเงินทุนให้เคาน์เตอร์อื่น
 *
 * เก็บเป็น setting ไม่ใช่เดาจากรหัส/ชื่อสาขา เพราะโครงสาขาของแต่ละ database
 * ไม่เหมือนกัน (uat ยังเป็น 8 สาขาเดิม ส่วน production ยุบเหลือสาขาเดียวแล้ว)
 * และวิธีเดาแบบเดิมใน OpenCloseDay::transferStockToHQ() — มองหา counter_code
 * ที่มี 'HQ' หรือ branch_type='HQ' — ไม่ match อะไรเลยสักแถวในข้อมูลจริง
 * มันจึง return เงียบๆ ทุกครั้งโดยไม่มีใครรู้
 *
 * ปล่อยค่าว่างไว้ให้ admin เลือกเอง ระหว่างที่ยังว่างปุ่มเติมเงิน/ส่งคืนคลัง
 * จะถูก disable พร้อมข้อความบอกเหตุผล ไม่ใช่ล้มเหลวเงียบ
 */
return new class extends Migration
{
    public function up(): void
    {
        Setting::firstOrCreate(
            ['setting_key' => 'THB_WAREHOUSE_COUNTER_ID'],
            [
                'setting_value' => '',
                'description' => 'เคาน์เตอร์คลังกลางสำหรับเงินบาท (ต้นทางเติมเงินทุน / ปลายทางส่งคืน)',
            ]
        );
    }

    public function down(): void
    {
        Setting::where('setting_key', 'THB_WAREHOUSE_COUNTER_ID')->delete();
    }
};
