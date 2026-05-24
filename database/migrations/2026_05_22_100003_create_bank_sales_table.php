<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_sales', function (Blueprint $table) {
            $table->id();
            $table->string('sale_no', 30)->unique();
            $table->string('bank_name', 100);
            $table->string('bank_account', 50)->nullable();
            $table->string('currency_code', 10);
            $table->foreignId('denomination_id')->nullable()->constrained('currency_denominations')->nullOnDelete();
            $table->decimal('total_amount', 15, 2);           // foreign amount to sell
            $table->decimal('bank_rate', 12, 6);               // sell rate to bank
            $table->decimal('total_thb', 15, 2)->default(0);   // total_amount × bank_rate
            $table->decimal('avg_cost_at_sale', 12, 6)->default(0); // weighted avg cost at time of sale
            $table->decimal('total_cost', 15, 2)->default(0);  // total_amount × avg_cost
            $table->decimal('profit_loss', 15, 2)->default(0); // total_thb - total_cost
            $table->string('settlement_method', 30)->default('bank_transfer'); // bank_transfer, cash, cheque
            $table->string('status', 20)->default('draft');    // draft, reserved, in_transit, delivered, completed, cancelled
            $table->text('notes')->nullable();
            $table->foreignId('journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at'], 'bs_status_idx');
            $table->index(['currency_code', 'created_at'], 'bs_currency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_sales');
    }
};
