<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_entry_names', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sanction_entry_id')->constrained('sanction_entries')->cascadeOnDelete();

            $table->string('name_raw', 191);
            $table->string('name_normalized', 191);

            // คำนวณด้วย soundex() ของ PHP ตอนเขียน — ห้ามเรียก SOUNDEX() ของ MySQL ใน query
            // ว่างสำหรับชื่อไทย (soundex เป็นอัลกอริทึม ASCII)
            $table->string('name_soundex', 100)->nullable();

            $table->string('script', 10);   // th | latin
            $table->boolean('is_primary')->default(false);

            $table->timestamps();

            $table->index('name_normalized');
            $table->index('name_soundex');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_entry_names');
    }
};
