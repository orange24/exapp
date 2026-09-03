<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * bank_sales เก็บทั้งฝั่งขายให้ธนาคารและซื้อจากธนาคาร แยกด้วย direction
 *
 * แถวเดิมทั้งหมดคือรายการขาย — MySQL เติม default ให้เองตอน ADD COLUMN
 * จึงไม่ต้อง backfill
 */
return new class extends Migration
{
    public function up(): void
    {
        if ($this->hasDirectionColumn()) {
            return;
        }

        Schema::table('bank_sales', function (Blueprint $table) {
            $table->string('direction', 10)->default('sell')->after('sale_no');
            $table->index(['direction', 'status', 'created_at'], 'bs_direction_status_idx');
        });
    }

    /**
     * MySQL เวอร์ชันเก่าของเซิร์ฟเวอร์นี้ทำให้ Schema::hasColumn() พัง จึงใช้ SHOW COLUMNS
     * ส่วน sqlite (ที่ใช้ตอนรันเทสต์) ไม่รู้จัก SHOW COLUMNS — ใช้ hasColumn ตามปกติ
     */
    private function hasDirectionColumn(): bool
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return ! empty(DB::select("SHOW COLUMNS FROM bank_sales LIKE 'direction'"));
        }

        return Schema::hasColumn('bank_sales', 'direction');
    }

    public function down(): void
    {
        Schema::table('bank_sales', function (Blueprint $table) {
            $table->dropIndex('bs_direction_status_idx');
            $table->dropColumn('direction');
        });
    }
};
