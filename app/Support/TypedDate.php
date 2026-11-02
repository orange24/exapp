<?php

namespace App\Support;

/**
 * วันที่ที่พนักงานพิมพ์เองหน้าเคาน์เตอร์ — แปลงเป็น Y-m-d ก่อนลงฐานข้อมูล
 *
 * ช่องวันหมดอายุเป็นช่องพิมพ์อิสระ คนไทยพิมพ์ 25/06/2070 หรือปี พ.ศ. 25/06/2570
 * ถ้าส่งตรงให้ Carbon จะ parse ไม่ได้แล้วบิลพังทั้งใบ (500) ทั้งที่ช่องนี้ไม่บังคับ
 * อ่านไม่ออกจริงๆ ให้คืน null — เสียวันที่ช่องเดียวดีกว่าบันทึกบิลไม่ได้
 */
class TypedDate
{
    public static function toIso(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            [$year, $month, $day] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } elseif (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{4})$#', $value, $m)) {
            [$day, $month, $year] = [(int) $m[1], (int) $m[2], (int) $m[3]];
        } else {
            return null;
        }

        // ปี พ.ศ. — บัตรประชาชนและคนไทยส่วนใหญ่ใช้
        if ($year > 2400) {
            $year -= 543;
        }

        return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
    }
}
