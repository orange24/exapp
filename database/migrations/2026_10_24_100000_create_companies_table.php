<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * บริษัทคือเจ้าของสต็อก บิล และบัญชี — สาขาเป็นแค่สถานที่ของบริษัทนั้น
 *
 * เดิมข้อมูลบริษัท (company_name, tax_id, license_no) ถูกเก็บซ้ำอยู่บนแถว
 * ของแต่ละสาขา ซึ่งใช้ได้ตอนมีบริษัทเดียว แต่พอมีหลายบริษัทจะไม่มีที่ให้
 * ตอบคำถามว่า "บริษัทนี้มีสาขาอะไรบ้าง" โดยไม่ต้องเดาจากชื่อที่พิมพ์ตรงกัน
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('company_code', 20)->unique();
            $table->string('company_name', 191);
            $table->string('tax_id', 20)->nullable();
            $table->string('license_no', 50)->nullable();
            $table->string('address', 191)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
