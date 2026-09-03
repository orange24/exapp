<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // On MySQL use raw SQL — hasColumn() fails on the old MySQL server.
        // Other drivers (sqlite in tests) don't support SHOW COLUMNS.
        $conn = Schema::getConnection();
        $exists = $conn->getDriverName() === 'mysql'
            ? !empty($conn->select("SHOW COLUMNS FROM counter_stock LIKE 'denomination_id'"))
            : Schema::hasColumn('counter_stock', 'denomination_id');

        if (!$exists) {
            Schema::table('counter_stock', function (Blueprint $table) {
                $table->unsignedBigInteger('denomination_id')->nullable()->after('currency_code');
                $table->decimal('avg_cost', 12, 6)->default(0)->after('hold_amount');
                $table->decimal('total_cost_value', 15, 2)->default(0)->after('avg_cost');
            });

            // Unique เดิมคือ (counter_id, currency_code) ซึ่งกันไม่ให้เคาน์เตอร์เดียว
            // ถือ USD 100-50 และ USD 20-10 พร้อมกัน — ต้องรวม denomination_id ด้วย
            if ($conn->getDriverName() === 'mysql') {
                $indexes = collect($conn->select("SHOW INDEX FROM counter_stock WHERE Key_name = 'counter_stock_counter_id_currency_code_unique'"));
                if ($indexes->isNotEmpty()) {
                    $conn->statement("ALTER TABLE counter_stock DROP FOREIGN KEY counter_stock_counter_id_foreign");
                    $conn->statement("ALTER TABLE counter_stock DROP INDEX counter_stock_counter_id_currency_code_unique");
                    $conn->statement("ALTER TABLE counter_stock ADD UNIQUE KEY counter_stock_unique (counter_id, currency_code, denomination_id)");
                    $conn->statement("ALTER TABLE counter_stock ADD CONSTRAINT counter_stock_counter_id_foreign FOREIGN KEY (counter_id) REFERENCES counters(id)");
                }
            } else {
                // sqlite (เทสต์) เคยข้ามขั้นนี้ ทำให้เทสต์ถือได้แค่ denom เดียวต่อสกุล
                // ต่อเคาน์เตอร์ — คนละพฤติกรรมกับ production
                Schema::table('counter_stock', function (Blueprint $table) {
                    $table->dropUnique('counter_stock_counter_id_currency_code_unique');
                    $table->unique(['counter_id', 'currency_code', 'denomination_id'], 'counter_stock_unique');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('counter_stock', function (Blueprint $table) {
            $table->dropColumn(['denomination_id', 'avg_cost', 'total_cost_value']);
        });
    }
};
