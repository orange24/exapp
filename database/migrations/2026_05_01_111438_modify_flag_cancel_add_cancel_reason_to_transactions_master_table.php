<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Change flag_cancel from boolean to VARCHAR(1) with 3 states:
     *   'N' = normal, 'R' = cancel request pending, 'Y' = cancelled/approved
     * Expand cancel_reason to VARCHAR(500).
     */
    public function up(): void
    {
        // First convert existing boolean values to the new char format
        // MySQL boolean is tinyint(1): 0 = false, 1 = true
        Schema::table('transactions_master', function (Blueprint $table) {
            $table->string('flag_cancel', 1)->default('N')->change();
            $table->string('cancel_reason', 500)->nullable()->change();
        });

        // Convert old boolean values: 0 -> 'N', 1 -> 'Y'
        DB::table('transactions_master')
            ->where('flag_cancel', '0')
            ->update(['flag_cancel' => 'N']);

        DB::table('transactions_master')
            ->where('flag_cancel', '1')
            ->update(['flag_cancel' => 'Y']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert back: 'N'/'R' -> 0, 'Y' -> 1
        DB::table('transactions_master')
            ->where('flag_cancel', 'Y')
            ->update(['flag_cancel' => '1']);

        DB::table('transactions_master')
            ->whereIn('flag_cancel', ['N', 'R'])
            ->update(['flag_cancel' => '0']);

        Schema::table('transactions_master', function (Blueprint $table) {
            $table->boolean('flag_cancel')->default(false)->change();
            $table->string('cancel_reason', 255)->nullable()->change();
        });
    }
};
