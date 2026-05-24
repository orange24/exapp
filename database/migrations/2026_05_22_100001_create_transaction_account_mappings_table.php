<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_account_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('trns_type', 30)->unique(); // BUYING, SELLING, SELLING_BANK, TRANSFER, ADJUST, VOID
            $table->string('description', 100)->nullable();
            $table->foreignId('debit_account_id')->constrained('accounts');
            $table->foreignId('credit_account_id')->constrained('accounts');
            $table->foreignId('pl_gain_account_id')->nullable()->constrained('accounts'); // FX Gain (credit)
            $table->foreignId('pl_loss_account_id')->nullable()->constrained('accounts'); // FX Loss (debit)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_account_mappings');
    }
};
