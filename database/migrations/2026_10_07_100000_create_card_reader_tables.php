<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_reader_devices', function (Blueprint $table) {
            $table->id();
            $table->string('name');

            // ผูกกับเคาน์เตอร์ ไม่ใช่ผูกกับผู้ใช้ — เครื่องอ่านตั้งอยู่ที่เคาน์เตอร์นั้น
            // ทางกายภาพ ใครมานั่งเวรก็ไม่เปลี่ยนความจริงข้อนี้ และถ้าผูกกับ session
            // จะต้องให้เบราว์เซอร์บอก agent ว่าตัวเองเป็นใคร ซึ่งเป็นทิศที่ทำไม่ได้
            $table->unsignedBigInteger('counter_id');

            // เก็บแค่ลายนิ้วมือของ token ตัวจริงแสดงครั้งเดียวตอนสร้างแล้วหายไป
            $table->string('token_hash', 64)->unique();

            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_status', 20)->nullable();
            $table->string('last_error')->nullable();
            $table->string('agent_version', 40)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index('counter_id');
        });

        Schema::create('card_reader_reads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counter_id');
            $table->unsignedBigInteger('device_id');

            // เข้ารหัสด้วย APP_KEY — ในนี้คือชื่อ เลข 13 หลัก วันเกิด ที่อยู่ และรูป
            // ของประชาชน ไม่ควรนอนเป็น plaintext แม้จะอยู่แค่สองนาที
            $table->longText('payload');

            // dateTime ไม่ใช่ timestamp — MySQL 5.6 ของเซิร์ฟเวอร์นี้ยอมให้มีคอลัมน์
            // timestamp NOT NULL ที่ไม่มีค่าเริ่มต้นได้แค่คอลัมน์เดียวต่อตาราง
            // ตัวที่สองจะได้ค่าเริ่มต้นเป็น 0000-00-00 ซึ่งโหมด strict ปฏิเสธทันที
            $table->dateTime('read_at');
            $table->dateTime('consumed_at')->nullable();
            $table->dateTime('expires_at');
            $table->timestamps();

            // หน้าเคาน์เตอร์ถามด้วยสามคอลัมน์นี้ทุกวินาทีครึ่ง
            $table->index(['counter_id', 'consumed_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_reader_reads');
        Schema::dropIfExists('card_reader_devices');
    }
};
