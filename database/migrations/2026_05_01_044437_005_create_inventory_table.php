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
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches');
            $table->string('currency_code', 10);
            $table->date('date');
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('buy_total', 15, 2)->default(0);
            $table->decimal('sell_total', 15, 2)->default(0);
            $table->decimal('transfer_in', 15, 2)->default(0);
            $table->decimal('transfer_out', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->default(0);
            $table->timestamps();
            $table->unique(['branch_id', 'currency_code', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
