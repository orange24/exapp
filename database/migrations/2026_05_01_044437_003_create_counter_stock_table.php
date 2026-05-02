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
        Schema::create('counter_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_id')->constrained('counters');
            $table->string('currency_code', 10);
            $table->decimal('quantity', 15, 2)->default(0);       // current intraday stock
            $table->decimal('hold_amount', 15, 2)->default(0);    // reserved by bookings
            $table->timestamps();
            $table->unique(['counter_id', 'currency_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('counter_stock');
    }
};
