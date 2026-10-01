<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_entry_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sanction_entry_id')->constrained('sanction_entries')->cascadeOnDelete();

            // national_id | passport | company_reg
            $table->string('type', 20);

            $table->string('value_raw', 191);          // "Jordan 654781"
            $table->string('value_normalized', 100);   // "654781"
            $table->string('issuing_country', 100)->nullable();

            $table->timestamps();

            // index ที่ทำให้ exact match เป็น query เดียว
            $table->index(['type', 'value_normalized']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_entry_identifiers');
    }
};
