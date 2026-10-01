<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_screening_matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('screening_id')->constrained('sanction_screenings')->cascadeOnDelete();

            // ไม่ cascade delete — sanction_entries ไม่เคยถูกลบอยู่แล้ว (ใช้ delisted_at)
            // และหลักฐานย้อนหลังต้องชี้ไปหาแถวเดิมได้เสมอ
            $table->foreignId('sanction_entry_id')->constrained('sanction_entries');

            // exact_national_id | exact_passport | name_exact
            // | token_containment | name_fuzzy | soundex
            $table->string('match_type', 30);

            $table->decimal('score', 5, 2);
            $table->string('matched_on')->nullable();

            $table->timestamps();

            $table->index('screening_id');
            $table->index('sanction_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_screening_matches');
    }
};
