<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ข้อมูลคู่ค้าของรายการซื้อ — ซื้อเข้าไม่ได้มาจากธนาคารเสมอไป
 *
 * ชื่อคู่ค้ายังเก็บที่ bank_name เหมือนเดิม (ฝั่งขายก็ใช้คอลัมน์นี้) เพิ่มเฉพาะ
 * ข้อมูลระบุตัวตนที่ฝั่งซื้อต้องบันทึกตาม KYC
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->hasColumn('bank_sales', 'customer_id')) {
            return;
        }

        Schema::table('bank_sales', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('bank_account')
                ->constrained('customers')->nullOnDelete();
            $table->string('customer_passport_no', 50)->nullable()->after('customer_id');
            $table->string('customer_nationality', 50)->nullable()->after('customer_passport_no');
            $table->string('customer_passport_expiry', 20)->nullable()->after('customer_nationality');
        });
    }

    /** MySQL เก่าของเซิร์ฟเวอร์นี้ทำให้ Schema::hasColumn() พัง — sqlite ตอนเทสต์ใช้ปกติได้ */
    private function hasColumn(string $table, string $column): bool
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return ! empty(DB::select("SHOW COLUMNS FROM {$table} LIKE '{$column}'"));
        }

        return Schema::hasColumn($table, $column);
    }

    public function down(): void
    {
        Schema::table('bank_sales', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['customer_id', 'customer_passport_no', 'customer_nationality', 'customer_passport_expiry']);
        });
    }
};
