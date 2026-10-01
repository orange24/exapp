<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanction_entries', function (Blueprint $table) {
            $table->id();

            // freeze_04_un | freeze_05_th | hr_02 | hr_08
            // hr_02/hr_08 เผื่อไว้สำหรับตอนเชื่อม APS API — ยังไม่ใช้ในแผนนี้
            $table->string('list_code', 20);

            // id จากต้นทาง เช่น 17178 — ใช้เป็น natural key ตอน upsert
            $table->string('source_ref', 50);

            $table->string('reference_number', 50)->nullable();   // QDi.400 (UN)
            $table->string('notification_number', 50)->nullable(); // 001/2556 (TH)
            $table->string('section', 30)->nullable();             // "7" | "6,15"

            $table->string('name_th')->nullable();   // UN list ไม่มีชื่อไทย
            $table->string('name_en')->nullable();
            $table->longText('aka')->nullable();     // MySQL เก่าไม่รองรับ json

            // string ไม่ใช่ date — ต้นทางมี "na" และบางรายมีแต่ปี
            $table->string('date_of_birth', 50)->nullable();

            $table->string('nationality', 100)->nullable();
            $table->text('address_1')->nullable();
            $table->text('address_2')->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('company_registration_number', 50)->nullable();
            $table->string('group_name', 100)->nullable();
            $table->string('status', 50)->nullable();

            $table->date('listed_on')->nullable();   // UN เท่านั้น
            $table->date('as_of_date')->nullable();

            // hash ของ "เนื้อหาหน้า detail" — ใช้ตรวจว่าข้อมูลคนนี้เปลี่ยนจริงไหม
            // และใช้ทำให้ false-positive clearance หมดอายุ (แผนที่ 2)
            $table->string('content_hash', 64)->nullable();

            // hash ของ "แถวบนหน้า list" — ใช้ตัดสินว่าต้องไปดึงหน้า detail ซ้ำไหม
            // ต้องแยกจาก content_hash เพราะมาจากคนละหน้า คนละชุดฟิลด์
            // ถ้าใช้ตัวเดียวกันจะไม่มีวันตรงกัน แล้วจะดึง detail ครบทุกใบทุกคืน
            $table->string('source_row_hash', 64)->nullable();

            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('delisted_at')->nullable();

            $table->timestamps();

            $table->unique(['list_code', 'source_ref']);
            $table->index('delisted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_entries');
    }
};
