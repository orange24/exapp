<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('counter_rates', function (Blueprint $table) {
            $table->unsignedBigInteger('denomination_id')->nullable()->after('currency_code');
            $table->index(['counter_id', 'denomination_id', 'rate_date'], 'idx_counter_denom_date');
        });
    }

    public function down(): void
    {
        Schema::table('counter_rates', function (Blueprint $table) {
            $table->dropIndex('idx_counter_denom_date');
            $table->dropColumn('denomination_id');
        });
    }
};
