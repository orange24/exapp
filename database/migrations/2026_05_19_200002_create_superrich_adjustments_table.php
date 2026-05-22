<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('superrich_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('denomination_id')->unique()->constrained('currency_denominations')->cascadeOnDelete();
            $table->decimal('adj_rate_buy', 10, 6)->default(0);
            $table->decimal('adj_rate_sell', 10, 6)->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('superrich_adjustments');
    }
};
