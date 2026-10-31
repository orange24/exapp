<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ผูกผู้ใช้เดิมทุกคนเข้าบริษัทตั้งต้น YESSOUTH
 *
 * ผู้ใช้ที่ไม่มีแถวใน company_user ยังเข้าระบบได้ (ตกไปใช้บริษัทของสาขา
 * ตัวเอง) แต่ auditor/trader ที่ไม่มีสาขาจะไม่มีบริษัทเลย และฟอร์มผู้ใช้
 * ก็จะขึ้นว่างจนกว่าจะมีคนไปติ๊กให้ทีละคน
 *
 * ข้าม admin เพราะเข้าได้ทุกบริษัทอยู่แล้ว และข้ามคนที่ผูกบริษัทไว้แล้ว
 * จึงรันซ้ำได้โดยไม่ทับสิ่งที่ตั้งไว้ ระบบที่ไม่มีบริษัท YESSOUTH ไม่เปลี่ยนอะไร
 */
return new class extends Migration
{
    public function up(): void
    {
        $companyId = DB::table('companies')->where('company_code', 'YESSOUTH')->value('id');

        if ($companyId === null) {
            return;
        }

        $userIds = DB::table('users')
            ->leftJoin('roles', 'roles.id', '=', 'users.role_id')
            ->where(fn ($q) => $q->whereNull('roles.name')->orWhereNotIn('roles.name', ['admin', 'superadmin']))
            ->whereNotIn('users.id', DB::table('company_user')->select('user_id'))
            ->pluck('users.id');

        $now = now();

        DB::table('company_user')->insert($userIds->map(fn ($userId) => [
            'company_id' => $companyId,
            'user_id' => $userId,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all());
    }

    public function down(): void
    {
        // ไม่ถอยกลับ — หลังรันแล้วแยกไม่ออกว่าแถวไหนมาจาก migration นี้
        // แถวไหนผู้ดูแลติ๊กเองจากฟอร์มผู้ใช้
    }
};
