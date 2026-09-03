<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ปลายทางที่รับของเข้า (กองกลาง) ของรายการซื้อจากธนาคาร
 *
 * เดิมเก็บเป็นแถวใน bank_sale_sources ซึ่งกำกวม — คำว่า "source" หมายถึง
 * เคาน์เตอร์ต้นทางของฝั่งขาย พอฝั่งซื้อแตกเป็นหลายธนบัตรแล้ว จำนวนเงินไป
 * อยู่ที่ bank_sale_items แถว source จึงเหลือแค่ "ปลายทางคือใคร" — ย้ายมาไว้ที่ header
 *
 * ฝั่งขายไม่ใช้คอลัมน์นี้ (null) และยังใช้ bank_sale_sources เหมือนเดิมทุกอย่าง
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasColumn('bank_sales', 'destination_counter_id')) {
            Schema::table('bank_sales', function (Blueprint $table) {
                $table->foreignId('destination_counter_id')->nullable()
                    ->after('denomination_id')
                    ->constrained('counters')->nullOnDelete();
            });
        }

        $this->backfillExistingPurchases();
    }

    /**
     * รายการซื้อที่สร้างไว้ก่อนมี items — ย้ายค่าจาก header มาเป็น item 1 แถว
     * และดึงปลายทางจาก source แถวแรก เพื่อให้หน้าจอใหม่อ่านทุกใบด้วยทางเดียวกัน
     */
    private function backfillExistingPurchases(): void
    {
        $purchases = DB::table('bank_sales')->where('direction', 'buy')->get();

        foreach ($purchases as $purchase) {
            if (! $purchase->destination_counter_id) {
                $source = DB::table('bank_sale_sources')
                    ->where('bank_sale_id', $purchase->id)
                    ->orderBy('id')
                    ->first();

                if ($source) {
                    DB::table('bank_sales')->where('id', $purchase->id)
                        ->update(['destination_counter_id' => $source->counter_id]);
                }
            }

            $hasItems = DB::table('bank_sale_items')->where('bank_sale_id', $purchase->id)->exists();
            if ($hasItems || ! $purchase->denomination_id) {
                continue;
            }

            DB::table('bank_sale_items')->insert([
                'bank_sale_id'    => $purchase->id,
                'currency_code'   => $purchase->currency_code,
                'denomination_id' => $purchase->denomination_id,
                'amount'          => $purchase->total_amount,
                'bank_rate'       => $purchase->bank_rate,
                'total_thb'       => $purchase->total_thb,
                'created_at'      => $purchase->created_at,
                'updated_at'      => $purchase->updated_at,
            ]);
        }
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
            $table->dropForeign(['destination_counter_id']);
            $table->dropColumn('destination_counter_id');
        });
    }
};
