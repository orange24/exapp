<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('counter_stock', function (Blueprint $table) {
            if (!Schema::hasColumn('counter_stock', 'denomination_id')) {
                $table->unsignedBigInteger('denomination_id')->nullable()->after('currency_code');
            }
            if (!Schema::hasColumn('counter_stock', 'avg_cost')) {
                $table->decimal('avg_cost', 12, 6)->default(0)->after('hold_amount');
            }
            if (!Schema::hasColumn('counter_stock', 'total_cost_value')) {
                $table->decimal('total_cost_value', 15, 2)->default(0)->after('avg_cost');
            }
        });

        // Update unique index — only on MySQL (SQLite doesn't support dropping/recreating indexes the same way)
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $indexes = collect(Schema::getConnection()->select("SHOW INDEX FROM counter_stock WHERE Key_name = 'counter_stock_counter_id_currency_code_unique'"));
            if ($indexes->isNotEmpty()) {
                Schema::getConnection()->statement("ALTER TABLE counter_stock DROP FOREIGN KEY counter_stock_counter_id_foreign");
                Schema::getConnection()->statement("ALTER TABLE counter_stock DROP INDEX counter_stock_counter_id_currency_code_unique");
                Schema::getConnection()->statement("ALTER TABLE counter_stock ADD UNIQUE KEY counter_stock_unique (counter_id, currency_code, denomination_id)");
                Schema::getConnection()->statement("ALTER TABLE counter_stock ADD CONSTRAINT counter_stock_counter_id_foreign FOREIGN KEY (counter_id) REFERENCES counters(id)");
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
