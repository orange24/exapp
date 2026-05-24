<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_sale_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bank_sale_id')->constrained('bank_sales')->cascadeOnDelete();
            $table->foreignId('counter_id')->constrained('counters');
            $table->decimal('amount', 15, 2);                  // foreign amount from this counter
            $table->decimal('avg_cost', 12, 6)->default(0);    // avg cost of this counter at time of reserve
            $table->string('status', 20)->default('reserved'); // reserved, in_transit, delivered, released
            $table->timestamp('transit_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['bank_sale_id', 'status'], 'bss_sale_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_sale_sources');
    }
};
