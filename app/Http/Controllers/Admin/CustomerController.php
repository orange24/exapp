<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerDocument;
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
        $customer->load(['transactions.details', 'transactions' => function ($q) {
            $q->orderBy('created_at', 'desc')->limit(50);
        }]);

        $documents = CustomerDocument::where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.customers.show', compact('customer', 'documents'));
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

        Customer::create($validated);

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

        return redirect()->route('admin.customers.index')
            ->with('success', 'แก้ไขข้อมูลลูกค้าเรียบร้อยแล้ว (Customer updated)');
    }
}
