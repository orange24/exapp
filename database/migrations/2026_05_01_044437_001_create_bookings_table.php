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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->foreignId('counter_id')->constrained('counters');
            $table->string('currency_code', 10);
            $table->decimal('amount', 15, 2);
            $table->decimal('rate', 10, 4);
            $table->enum('type', ['buy', 'sell']);
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'expired'])->default('pending');
            $table->decimal('hold_amount', 15, 2)->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['status', 'expires_at']);
            $table->index(['counter_id', 'currency_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
