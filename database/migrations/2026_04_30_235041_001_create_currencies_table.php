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
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 10)->unique();   // USD, EUR, GBP
            $table->string('currency_name', 100);            // US Dollar
            $table->string('currency_name_th', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('country_flag', 100)->nullable(); // flags/{currency_code}.png
            $table->decimal('rate_buy', 10, 4)->default(0);  // global default rate
            $table->decimal('rate_sell', 10, 4)->default(0);
            $table->decimal('sell_discount_rate', 10, 4)->default(0);
            $table->integer('seq')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);   // THB
            $table->string('target_currency', 10)->default('THB');
            $table->string('tenant_code', 20)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
