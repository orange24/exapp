<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::withCount([
            'branches',
            'branches as active_branches_count' => fn ($q) => $q->where('is_active', true),
        ])->orderBy('company_code')->get();

        return view('admin.companies.index', compact('companies'));
    }

    public function create()
    {
        return view('admin.companies.form', ['company' => new Company()]);
    }

    public function store(Request $request)
    {
        Company::create($this->validated($request));

        return redirect()->route('admin.companies.index')->with('success', 'เพิ่มบริษัทแล้ว');
    }

    public function edit(Company $company)
    {
        return view('admin.companies.form', compact('company'));
    }

    public function update(Request $request, Company $company)
    {
        $company->update($this->validated($request, $company));

        return redirect()->route('admin.companies.index')->with('success', 'บันทึกการแก้ไขแล้ว');
    }

    /**
     * เปิด/ปิดบริษัท
     *
     * ปิดบริษัทที่ยังมีสาขาเปิดอยู่ = สาขาเหล่านั้นหายไปจากทุกหน้าทันที
     * เพราะ getVisibleBranchIds() กรองด้วยบริษัทที่เลือกอยู่ คนที่เจอจะเห็นแค่
     * "ไม่มีข้อมูล" โดยไม่มีอะไรบอกว่าทำไม จึงกันไว้ตรงนี้แทนที่จะปล่อยผ่าน
     */
    public function toggleActive(Company $company)
    {
        if ($company->is_active) {
            $open = $company->branches()->where('is_active', true)->count();

            if ($open > 0) {
                return back()->with(
                    'error',
                    "ปิดบริษัทนี้ไม่ได้ — ยังมีสาขาเปิดอยู่ {$open} สาขา ปิดสาขาให้หมดก่อน",
                );
            }
        }

        $company->update(['is_active' => ! $company->is_active]);

        return back()->with('success', $company->is_active ? 'เปิดใช้งานบริษัทแล้ว' : 'ปิดใช้งานบริษัทแล้ว');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Company $company = null): array
    {
        $data = $request->validate([
            'company_code' => [
                'required', 'string', 'max:20',
                Rule::unique('companies')->ignore($company?->id),
            ],
            'company_name' => 'required|string|max:191',
            'tax_id' => 'nullable|string|max:20',
            'license_no' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:191',
            'phone' => 'nullable|string|max:30',
            'is_active' => 'boolean',
        ], attributes: [
            'company_code' => 'รหัสบริษัท',
            'company_name' => 'ชื่อบริษัท',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
