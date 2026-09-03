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
        $hasDenomColumn = fn (string $table) => $conn->getDriverName() === 'mysql'
            ? !empty($conn->select("SHOW COLUMNS FROM {$table} LIKE 'denomination_id'"))
            : Schema::hasColumn($table, 'denomination_id');

        // --- inventory table ---
        if (!$hasDenomColumn('inventory')) {
            Schema::table('inventory', function (Blueprint $table) {
                $table->unsignedBigInteger('denomination_id')->nullable()->after('currency_code');
                $table->unsignedBigInteger('counter_id')->nullable()->after('branch_id');
                $table->decimal('avg_cost', 12, 6)->default(0)->after('closing_balance');
                $table->decimal('total_value', 15, 2)->default(0)->after('avg_cost');
            });

            // Fix unique index — MySQL only
            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $indexes = collect(Schema::getConnection()->select("SHOW INDEX FROM inventory WHERE Key_name = 'inventory_branch_id_currency_code_date_unique'"));
                if ($indexes->isNotEmpty()) {
                    Schema::getConnection()->statement("ALTER TABLE inventory DROP FOREIGN KEY inventory_branch_id_foreign");
                    Schema::getConnection()->statement("ALTER TABLE inventory DROP INDEX inventory_branch_id_currency_code_date_unique");
                    Schema::getConnection()->statement("ALTER TABLE inventory ADD UNIQUE KEY inventory_daily_unique (branch_id, counter_id, currency_code, denomination_id, date)");
                    Schema::getConnection()->statement("ALTER TABLE inventory ADD CONSTRAINT inventory_branch_id_foreign FOREIGN KEY (branch_id) REFERENCES branches(id)");
                }
            }
        }

        // --- transactions_detail table ---
        if (!$hasDenomColumn('transactions_detail')) {
            Schema::table('transactions_detail', function (Blueprint $table) {
                $table->unsignedBigInteger('denomination_id')->nullable()->after('currency_code');
            });
        }
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropColumn(['denomination_id', 'counter_id', 'avg_cost', 'total_value']);
        });

        Schema::table('transactions_detail', function (Blueprint $table) {
            $table->dropColumn('denomination_id');
        });
    }
};
