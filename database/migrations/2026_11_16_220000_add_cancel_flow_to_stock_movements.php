<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ขอลบรายการเติมเงินทุน / นำเงินบาทออก แล้วให้ผู้จัดการอนุมัติ
 *
 * ใช้ธง N/R/Y แบบเดียวกับ transactions.flag_cancel — N = ปกติ,
 * R = ขอลบรออนุมัติ, Y = ลบแล้ว แถวที่ลบแล้วไม่ถูกลบจริง ยังเห็นเป็นประวัติ
 * แต่ StockMovement::thb() กรองออกจากการคำนวณยอด
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->string('flag_cancel', 1)->default('N');
            $table->string('cancel_reason', 500)->nullable();
            $table->unsignedBigInteger('cancel_requested_by')->nullable();
            $table->timestamp('cancel_requested_at')->nullable();
            $table->unsignedBigInteger('cancel_approved_by')->nullable();
            $table->timestamp('cancel_approved_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn([
                'flag_cancel', 'cancel_reason',
                'cancel_requested_by', 'cancel_requested_at',
                'cancel_approved_by', 'cancel_approved_at',
            ]);
        });
    }
};
