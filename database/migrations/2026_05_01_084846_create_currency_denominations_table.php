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
        Schema::create('currency_denominations', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 10);
            $table->string('denom_label', 30);           // "100-50", "20-10", "5", "2-1"
            $table->string('display_name', 50);          // "USD 100-50"
            $table->decimal('rate_buy', 12, 6)->default(0);
            $table->decimal('rate_sell', 12, 6)->default(0);
            $table->integer('seq')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['currency_code', 'seq']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currency_denominations');
    }
};
