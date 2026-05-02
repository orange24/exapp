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
        Schema::create('denomination_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_id')->constrained('counters');
            $table->string('currency_code', 10);
            $table->decimal('denomination', 10, 2);  // e.g. 100, 50, 20, 10, 5, 1
            $table->integer('quantity');
            $table->decimal('subtotal', 15, 2);
            $table->enum('type', ['adjustment', 'count', 'disbursement'])->default('count');
            $table->foreignId('transaction_id')->nullable()->constrained('transactions_master');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('denomination_logs');
    }
};
