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
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('counter_id')->constrained('counters');
            $table->string('currency_code', 10);
            $table->enum('movement_type', ['buy', 'sell', 'borrow', 'return', 'disbursement', 'adjustment', 'transfer_in', 'transfer_out']);
            $table->decimal('amount', 15, 2);
            $table->string('reference_type', 50)->nullable();   // transaction, booking, transfer
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('moved_by')->nullable()->constrained('users');
            $table->timestamp('moved_at')->useCurrent();
            $table->timestamps();
            $table->index(['counter_id', 'currency_code', 'moved_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
