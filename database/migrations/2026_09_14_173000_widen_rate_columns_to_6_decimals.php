<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE currencies MODIFY rate_buy DECIMAL(12,6) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE currencies MODIFY rate_sell DECIMAL(12,6) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE currencies MODIFY sell_discount_rate DECIMAL(12,6) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE counter_rates MODIFY rate_buy DECIMAL(12,6) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE counter_rates MODIFY rate_sell DECIMAL(12,6) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE counter_rates MODIFY sell_discount_rate DECIMAL(12,6) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE transactions_detail MODIFY unit_price DECIMAL(12,6) NOT NULL');
        DB::statement('ALTER TABLE transactions_detail MODIFY discount_rate_sell DECIMAL(12,6) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE bookings MODIFY rate DECIMAL(12,6) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE currencies MODIFY rate_buy DECIMAL(10,4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE currencies MODIFY rate_sell DECIMAL(10,4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE currencies MODIFY sell_discount_rate DECIMAL(10,4) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE counter_rates MODIFY rate_buy DECIMAL(10,4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE counter_rates MODIFY rate_sell DECIMAL(10,4) NOT NULL DEFAULT 0');
        DB::statement('ALTER TABLE counter_rates MODIFY sell_discount_rate DECIMAL(10,4) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE transactions_detail MODIFY unit_price DECIMAL(10,4) NOT NULL');
        DB::statement('ALTER TABLE transactions_detail MODIFY discount_rate_sell DECIMAL(10,4) NOT NULL DEFAULT 0');

        DB::statement('ALTER TABLE bookings MODIFY rate DECIMAL(10,4) NOT NULL');
    }
};
