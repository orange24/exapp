<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('superrich_rate_batches', function (Blueprint $table) {
            $table->id();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected, distributed
            $table->longText('rates_data')->nullable();
            $table->longText('target_counter_ids')->nullable();
            $table->timestamp('source_fetched_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('distributed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'sr_batches_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('superrich_rate_batches');
    }
};
