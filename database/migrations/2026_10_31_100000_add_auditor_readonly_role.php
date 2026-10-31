<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ผู้ตรวจสอบแบบอ่านอย่างเดียว — เห็นเมนูและข้อมูลเท่ากับ auditor ทุกอย่าง
 * แต่แก้อะไรไม่ได้เลย (ดู User::isReadOnly และ ReadOnlyGuard)
 *
 * เป็น migration ไม่ใช่ seeder เพราะ role ต้องมีก่อนที่ใครจะเลือกได้ในฟอร์มผู้ใช้
 * และ migration รันเองตอน deploy ส่วน seeder ไม่รัน
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('roles')->where('name', 'auditor_readonly')->exists()) {
            return;
        }

        DB::table('roles')->insert([
            'name' => 'auditor_readonly',
            'display_name' => 'ผู้ตรวจสอบ (อ่านอย่างเดียว)',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('name', 'auditor_readonly')->delete();
    }
};
