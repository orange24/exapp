<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_id')->constrained('counters')->cascadeOnDelete();
            $table->foreignId('denomination_id')->constrained('currency_denominations')->cascadeOnDelete();
            $table->string('currency_code', 10);
            $table->decimal('old_rate_buy', 12, 6)->nullable();
            $table->decimal('old_rate_sell', 12, 6)->nullable();
            $table->decimal('new_rate_buy', 12, 6);
            $table->decimal('new_rate_sell', 12, 6);
            $table->string('change_source', 30); // manual, rate_setting, superrich
            $table->string('source_reference', 100)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['counter_id', 'denomination_id', 'changed_at'], 'rcl_counter_denom_idx');
            $table->index(['change_source', 'changed_at'], 'rcl_source_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_change_logs');
    }
};
