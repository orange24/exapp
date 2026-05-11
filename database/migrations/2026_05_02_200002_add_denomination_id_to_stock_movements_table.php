<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('stock_movements', 'denomination_id')) {
                $table->unsignedBigInteger('denomination_id')->nullable()->after('currency_code');
            }
            if (!Schema::hasColumn('stock_movements', 'unit_price')) {
                $table->decimal('unit_price', 12, 6)->default(0)->after('amount');
            }
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            $indexes = collect(Schema::getConnection()->select("SHOW INDEX FROM stock_movements WHERE Key_name = 'stock_movements_denom_idx'"));
            if ($indexes->isEmpty()) {
                Schema::getConnection()->statement("ALTER TABLE stock_movements ADD INDEX stock_movements_denom_idx (counter_id, currency_code, denomination_id, moved_at)");
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
