<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanction_screening_matches', function (Blueprint $table) {
            // matched_on เป็นข้อความให้คนอ่าน ('ชื่อ "AMAN"') เอาไปคำนวณต่อไม่ได้
            // คอลัมน์นี้เก็บชื่อเปล่าที่ตรงกันจริง ให้คำอธิบายความหายากคิดจากชื่อ
            // ไม่ใช่จากป้ายกำกับ — null เมื่อ match ด้วยเลขเอกสาร
            $table->string('matched_name')->nullable()->after('matched_on');
        });
    }

    public function down(): void
    {
        Schema::table('sanction_screening_matches', function (Blueprint $table) {
            $table->dropColumn('matched_name');
        });
    }
};
