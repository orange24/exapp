<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_fp_clearances', function (Blueprint $table) {
            $table->id();

            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('sanction_entry_id')->constrained('sanction_entries');

            $table->foreignId('cleared_by')->constrained('users');
            $table->timestamp('cleared_at');
            $table->text('reason');

            // clearance หมดอายุอัตโนมัติเมื่อฝั่งใดฝั่งหนึ่งเปลี่ยน:
            // ปปง. แก้ข้อมูลรายชื่อ หรือ ลูกค้าแก้ชื่อ/เปลี่ยนพาสปอร์ต
            $table->string('entry_content_hash', 64);
            $table->string('customer_identity_hash', 64);

            $table->timestamps();

            $table->unique(['customer_id', 'sanction_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_fp_clearances');
    }
};
