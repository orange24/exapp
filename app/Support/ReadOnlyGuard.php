<?php

namespace App\Support;

use App\Exceptions\ReadOnlyViolation;
use Illuminate\Support\Facades\Auth;

/**
 * กันบัญชีอ่านอย่างเดียวไม่ให้เขียนฐานข้อมูล — ทุกหน้า ทุกเมนู รวมถึงหน้าที่ยังไม่ได้เขียน
 *
 * ดักที่ชั้นคำสั่ง SQL ก่อนส่งไปฐานข้อมูล ไม่ได้ดักที่ route หรือปุ่ม เพราะ:
 * - กั้น POST ทั้งหมดไม่ได้ — ออกไฟล์รายงานกับกรองตาราง Livewire ก็เป็น POST
 * - กั้นรายเมนูต้องมีคนจำใส่ทุกครั้งที่เพิ่มหน้าแก้ไข ลืมครั้งเดียวก็รั่ว
 *
 * ตารางใน SYSTEM_TABLES เป็นงานเบื้องหลังของการใช้ระบบ (session, cache,
 * ประวัติการเข้าใช้) ไม่ใช่ข้อมูลธุรกิจ จึงเขียนได้เสมอ ส่วนการแก้บัญชีตัวเอง
 * (ล็อกอิน/ออก, เปลี่ยนรหัสผ่าน) ให้ครอบด้วย allowing()
 */
class ReadOnlyGuard
{
    public const SYSTEM_TABLES = [
        'sessions', 'cache', 'cache_locks', 'user_sessions',
        'jobs', 'job_batches', 'failed_jobs',
    ];

    private static int $allowing = 0;

    /**
     * เรียกจาก DB::beforeExecuting ทุกคำสั่ง — คำสั่งอ่านผ่านไปทันที
     */
    public static function check(string $sql): void
    {
        if (self::$allowing > 0 || ! self::isWrite($sql)) {
            return;
        }

        // hasUser() ไม่ query — เรียก Auth::user() ตรงนี้จะโหลดผู้ใช้จากฐานข้อมูล
        // ระหว่างคำสั่งอื่นกำลังจะรัน
        if (! Auth::hasUser() || ! Auth::user()->isReadOnly()) {
            return;
        }

        $table = self::table($sql);

        if (! in_array($table, self::SYSTEM_TABLES, true)) {
            throw new ReadOnlyViolation((string) $table);
        }
    }

    /**
     * ให้บัญชีแก้ของตัวเองได้ชั่วคราว — ใช้เฉพาะจุดที่เป็นบัญชีของผู้ใช้เอง
     *
     * @template T
     * @param  callable(): T  $callback
     * @return T
     */
    public static function allowing(callable $callback): mixed
    {
        self::$allowing++;

        try {
            return $callback();
        } finally {
            self::$allowing--;
        }
    }

    private static function isWrite(string $sql): bool
    {
        return (bool) preg_match('/^\s*(insert|update|delete|replace|truncate|alter|drop|create|rename)\b/i', $sql);
    }

    private static function table(string $sql): ?string
    {
        if (preg_match('/^\s*(?:insert(?:\s+or\s+\w+)?\s+into|replace\s+into|update|delete\s+from|truncate(?:\s+table)?)\s+[`"\[]?(\w+)/i', $sql, $m)) {
            return strtolower($m[1]);
        }

        return null;
    }
}
