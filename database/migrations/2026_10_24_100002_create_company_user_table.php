<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ผู้ใช้หนึ่งคนดูแลได้หลายบริษัท
 *
 * ตารางนี้ว่างได้ — ผู้ใช้ที่ไม่มีแถวที่นี่จะตกไปใช้บริษัทของสาขาตัวเอง
 * (ดู User::accessibleCompanyIds()) เพื่อให้ผู้ใช้เดิมทั้งระบบใช้งานต่อได้
 * ทันทีโดยไม่ต้องไล่ผูกบริษัทให้ทุกคนก่อนวัน deploy
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
