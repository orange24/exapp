<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('card_reader_passport_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('counter_id');

            // เลขพาสปอร์ต + วันเกิด + วันหมดอายุ คือกุญแจที่เปิดชิปได้จริง
            // การเก็บค่านี้เท่ากับเก็บกุญแจของเล่มนั้น จึงเข้ารหัสและอายุสั้นเท่าข้อมูลบัตร
            $table->longText('mrz');

            $table->string('status', 20)->default('pending');
            $table->string('progress')->nullable();
            $table->string('error')->nullable();
            $table->unsignedBigInteger('requested_by')->nullable();

            // dateTime ไม่ใช่ timestamp — MySQL 5.6 ยอมให้มีคอลัมน์ timestamp NOT NULL
            // ที่ไม่มีค่าเริ่มต้นได้แค่คอลัมน์เดียวต่อตาราง
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['counter_id', 'status', 'expires_at']);
        });

        Schema::table('card_reader_reads', function (Blueprint $table) {
            // ใช้ตารางเดิมร่วมกัน เพราะกฎเรื่องอายุข้อมูลและการส่งมอบเหมือนกันทุกประการ
            $table->string('kind', 20)->default('national_id')->after('device_id');
        });
    }

    public function down(): void
    {
        Schema::table('card_reader_reads', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::dropIfExists('card_reader_passport_requests');
    }
};
