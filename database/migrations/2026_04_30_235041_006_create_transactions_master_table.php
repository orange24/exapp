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
        Schema::create('transactions_master', function (Blueprint $table) {
            $table->id();
            $table->string('trns_no', 30)->unique();         // doc number: B2024001, S2024001
            $table->enum('trns_type', ['BUYING', 'SELLING'])->index();
            $table->foreignId('counter_id')->constrained('counters');
            $table->string('counter_name', 100)->nullable(); // denormalized for display
            $table->foreignId('customer_id')->nullable()->constrained('customers');
            $table->string('cust_name', 200)->nullable();    // denormalized for display
            $table->string('convert_currency_to', 10)->default('THB');
            $table->boolean('is_discount_booth')->default(false);
            $table->string('discount_booth_code', 20)->nullable();
            $table->boolean('flag_cancel')->default(false);
            $table->string('cancel_reason', 255)->nullable();
            $table->timestamp('trns_datetime')->useCurrent();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->index(['trns_datetime', 'counter_id'], 'idx_trns_date_counter');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions_master');
    }
};
