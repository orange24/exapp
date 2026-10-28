<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * สลับบริษัทที่กำลังทำงานอยู่
 *
 * รายการใน dropdown ไม่ใช่การควบคุมสิทธิ์ — ค่า company_id ที่ส่งมาแก้ได้จาก
 * ฝั่งผู้ใช้ จึงต้องตรวจกับสิทธิ์จริงทุกครั้ง
 */
class SwitchCompanyController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate(['company_id' => 'required|integer']);

        $companyId = (int) $request->input('company_id');

        abort_unless(
            in_array($companyId, $request->user()->accessibleCompanyIds(), true),
            403,
            'ไม่มีสิทธิ์เข้าถึงบริษัทนี้',
        );

        $request->session()->put('active_company_id', $companyId);

        /*
         * ล้างสาขา/เคาน์เตอร์ที่ลงเวรไว้
         *
         * ของเดิมเป็นของบริษัทที่เพิ่งสลับออกมา ถ้าปล่อยไว้ ผู้ใช้จะยังยิงบิล
         * เข้าเคาน์เตอร์ของบริษัทเก่าโดยที่หน้าจอบอกว่าอยู่บริษัทใหม่แล้ว
         */
        $request->session()->forget(['working_branch_id', 'working_counter_id', 'working_counter_name']);

        return back()->with('success', 'สลับบริษัทแล้ว');
    }
}
