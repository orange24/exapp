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
        Schema::create('counter_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_id')->constrained('counters')->onDelete('cascade');
            $table->string('currency_code', 10);
            $table->decimal('rate_buy', 10, 4)->default(0);
            $table->decimal('rate_sell', 10, 4)->default(0);
            $table->decimal('sell_discount_rate', 10, 4)->default(0); // booth-to-booth discount
            $table->foreignId('set_by')->nullable()->constrained('users');
            $table->timestamp('rate_date')->nullable();  // trading date this rate applies to
            $table->timestamps();
            $table->index(['counter_id', 'currency_code', 'rate_date'], 'idx_counter_rates_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counter_rates');
    }
};
