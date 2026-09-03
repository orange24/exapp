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
            ? !empty($conn->select("SHOW COLUMNS FROM stock_movements LIKE 'denomination_id'"))
            : Schema::hasColumn('stock_movements', 'denomination_id');

        if (!$exists) {
            Schema::table('stock_movements', function (Blueprint $table) {
                $table->unsignedBigInteger('denomination_id')->nullable()->after('currency_code');
                $table->decimal('unit_price', 12, 6)->default(0)->after('amount');
            });

            if (Schema::getConnection()->getDriverName() === 'mysql') {
                $indexes = collect(Schema::getConnection()->select("SHOW INDEX FROM stock_movements WHERE Key_name = 'stock_movements_denom_idx'"));
                if ($indexes->isEmpty()) {
                    Schema::getConnection()->statement("ALTER TABLE stock_movements ADD INDEX stock_movements_denom_idx (counter_id, currency_code, denomination_id, moved_at)");
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropColumn(['denomination_id', 'unit_price']);
        });
    }
};
