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
        Schema::create('rate_setting_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('setting_master_id')->constrained('rate_setting_masters')->cascadeOnDelete();
            $table->foreignId('counter_id')->constrained('counters');
            $table->timestamps();
            $table->unique(['setting_master_id', 'counter_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rate_setting_counters');
    }
};
