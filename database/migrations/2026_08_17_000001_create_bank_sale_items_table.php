<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 1 แถว = 1 ธนบัตรในใบเดียว — ธนาคารส่งของมาครั้งเดียวได้หลาย denom หลายสกุล
 *
 * ใช้เฉพาะ direction='buy' ตอนนี้ ฝั่งขายยังเก็บธนบัตรเดียวไว้ที่ header เหมือนเดิม
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_sale_id')->constrained('bank_sales')->cascadeOnDelete();
            $table->string('currency_code', 10);
            $table->foreignId('denomination_id')->constrained('currency_denominations');
            $table->decimal('amount', 15, 2);        // จำนวนเงินตราต่างประเทศ
            $table->decimal('bank_rate', 12, 6);     // เรทที่ตกลงกับธนาคารของแถวนี้
            $table->decimal('total_thb', 15, 2);     // amount × bank_rate
            $table->timestamps();

            $table->index('bank_sale_id', 'bsi_sale_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_sale_items');
    }
};
