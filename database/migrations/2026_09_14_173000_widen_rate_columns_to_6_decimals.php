<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * ALTER ... MODIFY เป็น syntax ของ MySQL เท่านั้น ส่วน sqlite (ที่ phpunit.xml
     * บังคับใช้) ไม่รู้จักและไม่จำเป็นต้องรู้ เพราะมันเก็บตัวเลขแบบ dynamic
     * typing อยู่แล้ว ความละเอียดทศนิยมไม่ได้ถูกจำกัดที่ระดับคอลัมน์
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

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
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

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
