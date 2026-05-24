<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('denomination_id')->nullable()->after('currency_code')
                  ->constrained('currency_denominations')->nullOnDelete();
            $table->foreignId('transaction_id')->nullable()->after('hold_amount')
                  ->constrained('transactions_master')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('denomination_id');
            $table->dropConstrainedForeignId('transaction_id');
        });
    }
};
