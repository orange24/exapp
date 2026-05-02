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
        Schema::create('amla_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions_master');
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->string('threshold_flag', 50);        // thb_2m, usd_50k, suspicious
            $table->decimal('amount_thb', 15, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['pending', 'reported', 'cleared'])->default('pending');
            $table->timestamp('reported_at')->nullable();
            $table->foreignId('reported_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('amla_reports');
    }
};
