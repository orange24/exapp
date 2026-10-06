<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ความถี่ของแต่ละคำในรายชื่อบุคคลที่ถูกกำหนด
     *
     * ใช้ถ่วงน้ำหนักว่าการ "ตรงชื่อ" ครั้งนั้นเป็นหลักฐานแค่ไหน — วัดจากข้อมูลจริง
     * ของ ปปง.: คำว่า AL ปรากฏใน 157 รายชื่อ, MOHAMMAD 114, ABDUL 98 ส่วน 51%
     * ของคำทั้งหมดปรากฏเพียงครั้งเดียว
     *
     * ลูกค้าที่ชื่อมีคำว่า MOHAMED จึงไม่ควรได้คะแนนเท่ากับลูกค้าที่ชื่อตรงกับคำ
     * ที่มีอยู่ชื่อเดียวในลิสต์ทั้งหมด
     *
     * สร้างใหม่ทุกครั้งที่ sync รายชื่อสำเร็จและมีการเปลี่ยนแปลง
     */
    public function up(): void
    {
        Schema::create('sanction_name_tokens', function (Blueprint $table) {
            $table->id();
            // ต้องเทียบแบบ byte ตรง ๆ — collation ปกติของ MySQL มองข้ามวรรณยุกต์ไทย
            // ทำให้ ดอเลาะ/ดอเล๊าะ · สาและ/สาแล๊ะ · เจ๊ะแม/เจะแม ถูกนับรวมเป็นคำเดียว
            // ทั้งที่เป็นคนละคำ (เจอ 12 กลุ่มจากคำไทย 611 คำในรายชื่อจริง)
            //
            // sqlite ไม่รู้จัก collation นี้และไม่ต้องรู้ เพราะเทียบแบบ byte อยู่แล้ว
            $table->string('token', 191)
                ->collation(DB::getDriverName() === 'mysql' ? 'utf8mb4_bin' : null);
            $table->integer('document_frequency');   // ปรากฏในกี่ชื่อ
            $table->timestamps();

            $table->unique('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanction_name_tokens');
    }
};
