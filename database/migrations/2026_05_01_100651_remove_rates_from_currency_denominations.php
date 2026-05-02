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
        Schema::table('currency_denominations', function (Blueprint $table) {
            $table->dropColumn(['rate_buy', 'rate_sell']);
        });
    }

    public function down(): void
    {
        Schema::table('currency_denominations', function (Blueprint $table) {
            $table->decimal('rate_buy', 12, 6)->default(0)->after('display_name');
            $table->decimal('rate_sell', 12, 6)->default(0)->after('rate_buy');
        });
    }
};
