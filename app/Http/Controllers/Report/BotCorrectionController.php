<?php

namespace App\Http\Controllers\Report;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\SanctionScreening;
use App\Models\TransactionMaster;
use App\Services\Bot\CountryCode;
use App\Services\Sanction\Dto\ScreeningInput;
use App\Services\Sanction\SanctionScreeningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * แก้บิลที่ส่งแบงค์ชาติไม่ได้ จากหน้าส่งข้อมูลให้แบงค์ชาติโดยตรง
 *
 * คนส่งรายงาน (มักเป็น auditor) ไม่มีสิทธิ์เข้าหน้าข้อมูลหลักลูกค้า และระบบไม่มี
 * หน้าผูกลูกค้าย้อนหลังเลย — ถ้าไม่แก้ที่นี่ บิลพวกนี้จะค้างไม่มีใครแก้ได้
 *
 * ทุกคำขออ้างบิล ไม่ได้อ้างลูกค้าลอยๆ เพื่อตรวจขอบเขตสาขาจากบิลได้
 * และทุกการแก้บันทึกค่าเดิมลง audit_logs
 */
class BotCorrectionController extends Controller
{
    /**
     * ค้นลูกค้าเดิมเพื่อผูกเข้าบิล — ชื่อหรือเลขบัตร
     */
    public function search(Request $request): JsonResponse
    {
        $this->authorizeCorrection($request);

        $q = trim((string) $request->input('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $customers = Customer::where(function ($w) use ($q) {
            $w->where('id_number', 'like', "%{$q}%")
                ->orWhere('name_th', 'like', "%{$q}%")
                ->orWhere('name_en', 'like', "%{$q}%")
                ->orWhere('first_name', 'like', "%{$q}%")
                ->orWhere('last_name', 'like', "%{$q}%");
        })
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        return response()->json($customers->map(fn (Customer $c) => [
            'id' => $c->id,
            'name' => trim($c->display_name),
            'id_type' => $c->id_type,
            'id_number' => $c->id_number,
            'nationality' => $c->nationality,
        ]));
    }

    /**
     * แก้ข้อมูลของลูกค้าที่ผูกกับบิลอยู่แล้ว — สัญชาติ ประเภทบัตร เลขบัตร
     */
    public function updateCustomer(Request $request, TransactionMaster $transaction): RedirectResponse
    {
        $this->authorizeCorrection($request, $transaction);

        $customer = Customer::find($transaction->customer_id);
        abort_if($customer === null, 422, 'บิลนี้ยังไม่ได้ผูกลูกค้า');

        $validated = $request->validate([
            'id_type' => ['required', Rule::in(['national_id', 'passport'])],
            'id_number' => ['required', 'string', 'max:50', Rule::unique('customers', 'id_number')->ignore($customer->id)],
            'nationality' => ['required', Rule::in(array_keys($this->countries()->choices()))],
        ], $this->messages());

        $old = $customer->only(['id_type', 'id_number', 'nationality']);

        DB::transaction(function () use ($customer, $validated, $old) {
            $customer->update($validated);
            AuditLog::log('bot_submission.customer_corrected', $customer, $old);
        });

        $this->screen($customer);

        return back()->with('success', "แก้ข้อมูลลูกค้าของบิล {$transaction->trns_no} แล้ว");
    }

    /**
     * ผูกลูกค้าเข้าบิลที่ออกโดยไม่ได้เลือกลูกค้า — เลือกคนเดิม หรือสร้างใหม่
     */
    public function linkCustomer(Request $request, TransactionMaster $transaction): RedirectResponse
    {
        $this->authorizeCorrection($request, $transaction);
        abort_if($transaction->customer_id !== null, 422, 'บิลนี้ผูกลูกค้าไว้แล้ว');

        if ($request->filled('customer_id')) {
            $request->validate(['customer_id' => 'integer|exists:customers,id']);
            $customer = Customer::findOrFail((int) $request->input('customer_id'));
            $created = false;
        } else {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'id_type' => ['required', Rule::in(['national_id', 'passport'])],
                'id_number' => 'required|string|max:50|unique:customers,id_number',
                'nationality' => ['required', Rule::in(array_keys($this->countries()->choices()))],
            ], $this->messages());

            $customer = new Customer([
                'type' => 'individual',
                'id_type' => $validated['id_type'],
                'id_number' => $validated['id_number'],
                'nationality' => $validated['nationality'],
                // ชื่อไทยกับอังกฤษเก็บคนละช่อง — หน้าอื่นอ่านจากช่องตามภาษา
                preg_match('/\p{Thai}/u', $validated['name']) ? 'name_th' : 'name_en' => $validated['name'],
            ]);
            $created = true;
        }

        DB::transaction(function () use ($transaction, $customer, $created) {
            if ($created) {
                $customer->save();
                AuditLog::log('bot_submission.customer_created', $customer);
            }

            $old = $transaction->only(['customer_id', 'cust_name']);
            $transaction->customer_id = $customer->id;
            if (blank($transaction->cust_name)) {
                $transaction->cust_name = trim($customer->display_name);
            }
            $transaction->save();

            AuditLog::log('bot_submission.customer_linked', $transaction, $old);
        });

        $this->screen($customer);

        return back()->with('success', "ผูกลูกค้ากับบิล {$transaction->trns_no} แล้ว");
    }

    /**
     * คนที่เปิดหน้านี้ได้แก้ได้ ยกเว้นบัญชีอ่านอย่างเดียว — และบิลต้องอยู่ในสาขาที่เขาเห็น
     */
    private function authorizeCorrection(Request $request, ?TransactionMaster $transaction = null): void
    {
        $user = $request->user();

        abort_unless($user->canCorrectBotSubmission(), 403, 'ไม่มีสิทธิ์แก้ข้อมูลในหน้านี้');

        if ($transaction !== null) {
            $branchId = DB::table('counters')->where('id', $transaction->counter_id)->value('branch_id');
            abort_unless(in_array((int) $branchId, array_map('intval', $user->getVisibleBranchIds()), true), 403, 'บิลนี้ไม่อยู่ในสาขาที่คุณดูแล');
        }
    }

    private function countries(): CountryCode
    {
        return new CountryCode((require resource_path('templates/bot/emc-dimension.php'))['countries']);
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'id_number.unique' => 'เลขบัตรนี้มีลูกค้าอยู่แล้ว — ค้นหาแล้วเลือกคนนั้นแทน',
            'id_number.required' => 'กรอกเลขบัตร',
            'nationality.in' => 'เลือกสัญชาติจากรายการ',
            'name.required' => 'กรอกชื่อลูกค้า',
        ];
    }

    /**
     * ตรวจรายชื่อ ปปง. ทุกครั้งที่ข้อมูลระบุตัวลูกค้าเปลี่ยน เหมือนหน้าข้อมูลหลัก
     */
    private function screen(Customer $customer): void
    {
        try {
            app(SanctionScreeningService::class)->screen(
                input: ScreeningInput::fromCustomer($customer),
                trigger: SanctionScreening::TRIGGER_CUSTOMER_CREATE,
                screenedBy: auth()->id(),
                customerId: $customer->id,
            );
        } catch (\Throwable $e) {
            Log::error('Sanction screening failed for customer', [
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
