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
        Schema::create('transactions_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained('transactions_master')->onDelete('cascade');
            $table->string('currency_code', 10);
            $table->string('currency_name', 100)->nullable();
            $table->decimal('unit_price', 10, 4);            // exchange rate
            $table->decimal('amount', 15, 2);                // foreign currency amount
            $table->decimal('total', 15, 2);                 // THB total
            $table->decimal('discount_rate_sell', 10, 4)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['transaction_id', 'currency_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions_detail');
    }
};
