<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no', 30)->unique();
            $table->enum('transfer_type', ['borrow', 'return', 'disbursement', 'intraday_return']);
            $table->unsignedBigInteger('from_counter_id');
            $table->unsignedBigInteger('to_counter_id');
            $table->string('currency_code', 10);
            $table->unsignedBigInteger('denomination_id');
            $table->decimal('amount', 15, 2);
            $table->decimal('unit_price', 12, 6)->default(0)->comment('avg cost at time of transfer');
            $table->enum('status', ['pending', 'completed', 'cancelled'])->default('completed');
            $table->unsignedBigInteger('related_transfer_id')->nullable()->comment('link borrow↔return pair');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('transferred_at')->useCurrent();
            $table->timestamps();

            $table->foreign('from_counter_id')->references('id')->on('counters');
            $table->foreign('to_counter_id')->references('id')->on('counters');
            $table->foreign('denomination_id')->references('id')->on('currency_denominations');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('approved_by')->references('id')->on('users');
            $table->foreign('related_transfer_id')->references('id')->on('stock_transfers');

            $table->index(['from_counter_id', 'transfer_type', 'transferred_at'], 'st_from_type_date_idx');
            $table->index(['to_counter_id', 'transfer_type', 'transferred_at'], 'st_to_type_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
