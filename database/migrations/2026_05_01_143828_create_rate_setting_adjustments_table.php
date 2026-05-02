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
        Schema::create('rate_setting_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setting_master_id')->constrained('rate_setting_masters')->cascadeOnDelete();
            $table->foreignId('denomination_id')->constrained('currency_denominations');
            $table->decimal('cal_rate_buy', 10, 6)->default(0);
            $table->decimal('cal_rate_sell', 10, 6)->default(0);
            $table->timestamps();
            $table->unique(['setting_master_id', 'denomination_id'], 'rate_adj_master_denom_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_setting_adjustments');
    }
};
