<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * บัญชีอ่านอย่างเดียวพยายามเขียนข้อมูล — ตอบ 403 พร้อมข้อความที่คนอ่านเข้าใจ
 * ไม่ใช่ 500 ที่ดูเหมือนระบบพัง
 */
class ReadOnlyViolation extends HttpException
{
    public function __construct(public readonly string $table)
    {
        parent::__construct(403, 'บัญชีนี้อ่านได้อย่างเดียว แก้ไขข้อมูลไม่ได้');
    }
}
