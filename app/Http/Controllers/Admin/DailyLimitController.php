<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CustomerDailyLimit;
use Illuminate\Http\Request;

/**
 * ตั้งวงเงินต่อคนต่อวันของธุรกรรมหน้าเคาน์เตอร์ — รับซื้อกับจ่ายขายแยกกัน
 * แต่ละฝั่งเปิด/ปิดได้ (ดู CustomerDailyLimit)
 */
class DailyLimitController extends Controller
{
    public function edit()
    {
        return view('admin.daily-limit', [
            'buy' => CustomerDailyLimit::config(CustomerDailyLimit::BUYING),
            'sell' => CustomerDailyLimit::config(CustomerDailyLimit::SELLING),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'buy_amount' => 'required|numeric|min:0|max:999999999999',
            'sell_amount' => 'required|numeric|min:0|max:999999999999',
        ], [], [
            'buy_amount' => 'วงเงินรับซื้อ',
            'sell_amount' => 'วงเงินจ่ายขาย',
        ]);

        CustomerDailyLimit::save(CustomerDailyLimit::BUYING, $request->boolean('buy_enabled'), (float) $request->input('buy_amount'));
        CustomerDailyLimit::save(CustomerDailyLimit::SELLING, $request->boolean('sell_enabled'), (float) $request->input('sell_amount'));

        return redirect()->route('admin.daily-limit.edit')->with('success', 'บันทึกวงเงินต่อคนต่อวันแล้ว');
    }
}
