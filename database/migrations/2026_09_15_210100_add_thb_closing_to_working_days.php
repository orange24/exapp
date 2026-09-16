<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ยอดปิดเงินบาทประจำวัน: ระบบคำนวณ expected จาก movement จริง พนักงานนับเงิน
 * แล้วกรอก actual ผลต่างถูกโพสต์กลับเป็น adjustment movement ตอน admin อนุมัติ
 * (ดู ThbCashService::postClosingVariance) — ยอดยกมาวันถัดไปจึงเท่ากับเงินที่
 * นับได้จริง ไม่ใช่ยอดตามบัญชี
 *
 * opening_thb_cash (คอลัมน์เดิม) เปลี่ยนความหมายจาก "ยอดที่พนักงานกรอกมือ"
 * เป็น "snapshot ยอดยกมาที่ระบบคำนวณได้ ณ ตอนเปิดวัน" เก็บไว้เพื่อ audit
 * ส่วนเงินที่เติมเข้าไปจริงเป็น movement row (thb_in / reference_type=thb_topup)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('working_days', function (Blueprint $table) {
            $table->decimal('closing_thb_expected', 15, 2)->nullable()->after('closing_notes')
                ->comment('ยอดบาทปลายวันตามระบบ');
            $table->decimal('closing_thb_actual', 15, 2)->nullable()->after('closing_thb_expected')
                ->comment('ยอดบาทที่นับได้จริง');
            $table->decimal('closing_thb_variance', 15, 2)->nullable()->after('closing_thb_actual')
                ->comment('actual - expected');
        });
    }

    public function down(): void
    {
        Schema::table('working_days', function (Blueprint $table) {
            $table->dropColumn(['closing_thb_expected', 'closing_thb_actual', 'closing_thb_variance']);
        });
    }
};
