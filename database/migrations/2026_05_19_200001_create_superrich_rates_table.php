<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('superrich_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency_code', 10);
            $table->string('superrich_denom', 50);
            $table->foreignId('denomination_id')->nullable()->constrained('currency_denominations')->nullOnDelete();
            $table->decimal('rate_buy', 12, 6)->default(0);
            $table->decimal('rate_sell', 12, 6)->default(0);
            $table->timestamp('api_datetime')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->foreignId('fetched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['currency_code', 'denomination_id', 'fetched_at'], 'sr_rates_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('superrich_rates');
    }
};
