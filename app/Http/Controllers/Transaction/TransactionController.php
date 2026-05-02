<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\TransactionMaster;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    public function buy(): \Illuminate\View\View
    {
        return view('transaction.buy');
    }

    public function sell(): \Illuminate\View\View
    {
        return view('transaction.sell');
    }

    public function printSlip(TransactionMaster $transaction): \Illuminate\View\View
    {
        $transaction->load(['counter.branch', 'details', 'customer', 'createdBy']);
        return view('transaction.print-slip', compact('transaction'));
    }

    /**
     * My Transactions page (staff view).
     */
    public function myTransactions(): \Illuminate\View\View
    {
        return view('transaction.my-transactions');
    }

    /**
     * All Transactions page (admin/accounting view).
     */
    public function allTransactions(): \Illuminate\View\View
    {
        return view('transaction.all-transactions');
    }

    /**
     * Staff requests cancellation of a transaction.
     * Sets flag_cancel = 'R' and stores the reason.
     */
    public function requestCancel(Request $request, TransactionMaster $transaction): JsonResponse
    {
        $request->validate([
            'cancel_reason' => 'required|string|max:500',
        ], [
            'cancel_reason.required' => 'กรุณาระบุเหตุผลในการยกเลิก',
            'cancel_reason.max' => 'เหตุผลต้องไม่เกิน 500 ตัวอักษร',
        ]);

        if ($transaction->flag_cancel !== 'N') {
            return response()->json([
                'success' => false,
                'message' => 'ไม่สามารถขอยกเลิกรายการนี้ได้ (สถานะไม่ใช่ปกติ)',
            ], 422);
        }

        $transaction->update([
            'flag_cancel' => 'R',
            'cancel_reason' => $request->cancel_reason,
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'ส่งคำขอยกเลิกเรียบร้อยแล้ว (Cancel request submitted)',
        ]);
    }

    /**
     * Admin approves a cancellation request.
     * Sets flag_cancel = 'Y'.
     */
    public function approveCancel(TransactionMaster $transaction): JsonResponse
    {
        if ($transaction->flag_cancel !== 'R') {
            return response()->json([
                'success' => false,
                'message' => 'ไม่มีคำขอยกเลิกสำหรับรายการนี้',
            ], 422);
        }

        $transaction->update([
            'flag_cancel' => 'Y',
            'updated_by' => Auth::id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'อนุมัติยกเลิกเรียบร้อยแล้ว (Cancellation approved)',
        ]);
    }

    /**
     * JSON response with transaction detail lines (for modal).
     */
    public function detail(TransactionMaster $transaction): JsonResponse
    {
        $transaction->load(['details', 'customer', 'counter', 'createdBy']);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $transaction->id,
                'trns_no' => $transaction->trns_no,
                'trns_type' => $transaction->trns_type,
                'trns_datetime' => $transaction->trns_datetime->format('d/m/Y H:i'),
                'counter_name' => $transaction->counter_name,
                'cust_name' => $transaction->cust_name,
                'flag_cancel' => $transaction->flag_cancel,
                'cancel_reason' => $transaction->cancel_reason,
                'created_by' => $transaction->createdBy?->name,
                'details' => $transaction->details->map(fn ($d) => [
                    'currency_code' => $d->currency_code,
                    'currency_name' => $d->currency_name,
                    'amount' => $d->amount,
                    'unit_price' => $d->unit_price,
                    'total' => $d->total,
                ]),
                'total_thb' => $transaction->details->sum('total'),
            ],
        ]);
    }
}
