<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDocument;
use App\Models\SanctionScreening;
use App\Models\TransactionMaster;
use App\Services\Sanction\Dto\ScreeningInput;
use App\Services\Sanction\SanctionScreeningService;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    /**
     * Searchable list of customers.
     */
    public function index(Request $request)
    {
        $query = Customer::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name_th', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('id_number', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($idType = $request->input('id_type')) {
            $query->where('id_type', $idType);
        }

        if ($kycStatus = $request->input('kyc_status')) {
            $query->where('kyc_status', $kycStatus);
        }

        $customers = $query->orderBy('created_at', 'desc')->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    /**
     * Show full customer detail.
     */
    public function show(Customer $customer)
    {
        $transactions = TransactionMaster::where('customer_id', $customer->id)
            ->with('details')
            ->orderBy('trns_datetime', 'desc')
            ->limit(50)
            ->get();

        $documents = CustomerDocument::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.customers.show', compact('customer', 'transactions', 'documents'));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        return view('admin.customers.form', [
            'customer' => new Customer(),
            'isEdit'   => false,
        ]);
    }

    /**
     * Store new customer.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'type'            => 'required|in:individual,corporate',
            'id_type'         => 'required|string|max:50',
            'id_number'       => 'required|string|max:50|unique:customers,id_number',
            'name_th'         => 'nullable|string|max:255',
            'name_en'         => 'nullable|string|max:255',
            'first_name'      => 'nullable|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'nationality'     => 'nullable|string|max:100',
            'date_of_birth'   => 'nullable|date',
            'passport_expiry' => 'nullable|date',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:500',
            'kyc_status'      => 'nullable|in:pending,verified,rejected',
        ]);

        $customer = Customer::create($validated);

        $this->screenAgainstSanctionLists($customer);

        return redirect()->route('admin.customers.index')
            ->with('success', 'เพิ่มลูกค้าเรียบร้อยแล้ว (Customer created)');
    }

    /**
     * Show edit form.
     */
    public function edit(Customer $customer)
    {
        return view('admin.customers.form', [
            'customer' => $customer,
            'isEdit'   => true,
        ]);
    }

    /**
     * Update customer.
     */
    public function update(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'type'            => 'required|in:individual,corporate',
            'id_type'         => 'required|string|max:50',
            'id_number'       => 'required|string|max:50|unique:customers,id_number,' . $customer->id,
            'name_th'         => 'nullable|string|max:255',
            'name_en'         => 'nullable|string|max:255',
            'first_name'      => 'nullable|string|max:100',
            'last_name'       => 'nullable|string|max:100',
            'nationality'     => 'nullable|string|max:100',
            'date_of_birth'   => 'nullable|date',
            'passport_expiry' => 'nullable|date',
            'phone'           => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:500',
            'kyc_status'      => 'nullable|in:pending,verified,rejected',
        ]);

        $customer->update($validated);

        $this->screenAgainstSanctionLists($customer);

        return redirect()->route('admin.customers.index')
            ->with('success', 'แก้ไขข้อมูลลูกค้าเรียบร้อยแล้ว (Customer updated)');
    }

    /**
     * ตรวจรายชื่อลูกค้ากับบัญชีรายชื่อ (sanction lists)
     *
     * ต่างจากจุดอื่นในฟีเจอร์นี้: ตรงนี้ "ไม่บล็อก" การบันทึกลูกค้า
     * เพราะการบันทึกประวัติลูกค้ายังไม่ใช่การทำธุรกรรม ถ้าบล็อกที่นี่
     * พนักงานจะเลี่ยงไม่สร้างประวัติลูกค้า ซึ่งจะทำให้ร่องรอยหลักฐาน
     * ที่ฟีเจอร์นี้ต้องพึ่งพาหายไปทั้งหมด
     *
     * ถ้าเข้าข่าย ผลตรวจจะเข้าคิวให้สำนักงานใหญ่ตัดสินภายหลัง
     */
    /**
     * ตรวจรายชื่อหลังบันทึกลูกค้า — ไม่บล็อกการบันทึก
     *
     * ลูกค้าถูก commit ไปแล้วตอนมาถึงบรรทัดนี้ ถ้าปล่อยให้ exception หลุดขึ้นไป
     * พนักงานจะเห็นหน้า error ทั้งที่ข้อมูลเซฟเรียบร้อย แล้วจะพิมพ์ซ้ำเป็นข้อมูลซ้ำ
     *
     * แต่จะกลืนเงียบก็ไม่ได้ เพราะนั่นคือรูในหลักฐานที่ฟีเจอร์นี้มีไว้อุด
     * จึง log ไว้ให้ครบ + แจ้งพนักงานว่ายังตรวจไม่ได้ เพื่อให้มีคนตามต่อ
     */
    private function screenAgainstSanctionLists(Customer $customer): void
    {
        try {
            app(SanctionScreeningService::class)->screen(
                input: ScreeningInput::fromCustomer($customer),
                trigger: SanctionScreening::TRIGGER_CUSTOMER_CREATE,
                screenedBy: auth()->id(),
                customerId: $customer->id,
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Sanction screening failed for customer', [
                'customer_id' => $customer->id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            session()->flash(
                'error',
                'บันทึกข้อมูลลูกค้าเรียบร้อย แต่ยังตรวจรายชื่อบุคคลต้องห้ามไม่สำเร็จ '
                . '— โปรดแจ้งผู้ดูแลระบบให้ตรวจย้อนหลัง'
            );
        }
    }
}
