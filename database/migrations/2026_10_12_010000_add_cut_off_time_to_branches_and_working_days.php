<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * เวลาตัดรอบแยกตามสาขา
 *
 * branches.cut_off_time เป็น null ได้ หมายถึงสาขานั้นใช้ค่ากลาง (WORKING_CUT_OFF)
 * ตามเดิม การเพิ่มคอลัมน์นี้จึงไม่เปลี่ยนพฤติกรรมของสาขาใดจนกว่าจะมีคนตั้งค่า
 *
 * working_days.cut_off_time เก็บค่าที่มีผลตอนเปิดวันนั้น เพื่อให้รายงานย้อนหลัง
 * นิ่ง — ถ้าพรุ่งนี้สาขาเปลี่ยนเวลาตัดรอบ ยอดของวันที่ปิดไปแล้วต้องไม่ขยับ
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! $this->hasColumn('branches', 'cut_off_time')) {
            Schema::table('branches', function (Blueprint $table) {
                $table->time('cut_off_time')->nullable()->after('is_active');
            });
        }

        if (! $this->hasColumn('working_days', 'cut_off_time')) {
            Schema::table('working_days', function (Blueprint $table) {
                $table->time('cut_off_time')->nullable()->after('work_date');
            });
        }

        $this->stampExistingDays();
    }

    /**
     * เติมค่าให้วันทำการที่มีอยู่แล้ว
     *
     * ตอนนี้ยังไม่มีสาขาใดตั้งเวลาตัดรอบเอง ทุกวันในอดีตจึงใช้ค่ากลางแน่นอน
     * การเติมค่านี้ไม่ใช่การเดา แต่เป็นการบันทึกความจริงที่รู้อยู่ก่อนที่มันจะ
     * ถูกเขียนทับ ถ้าไม่เติม วันแรกที่มีคนตั้งค่าสาขา รายงานย้อนหลังของสาขา
     * นั้นทั้งหมดจะขยับตาม
     */
    private function stampExistingDays(): void
    {
        $central = DB::table('settings')->where('setting_key', 'WORKING_CUT_OFF')->value('setting_value')
            ?: '03:00:00';

        DB::table('working_days')->whereNull('cut_off_time')->update(['cut_off_time' => $central]);
    }

    public function down(): void
    {
        if ($this->hasColumn('branches', 'cut_off_time')) {
            Schema::table('branches', fn (Blueprint $t) => $t->dropColumn('cut_off_time'));
        }

        if ($this->hasColumn('working_days', 'cut_off_time')) {
            Schema::table('working_days', fn (Blueprint $t) => $t->dropColumn('cut_off_time'));
        }
    }

    /** MySQL เก่าของเซิร์ฟเวอร์นี้ทำให้ Schema::hasColumn() พัง — sqlite ตอนเทสใช้ปกติได้ */
    private function hasColumn(string $table, string $column): bool
    {
        if (DB::connection()->getDriverName() === 'mysql') {
            return ! empty(DB::select("SHOW COLUMNS FROM {$table} LIKE '{$column}'"));
        }

        return Schema::hasColumn($table, $column);
    }
};
