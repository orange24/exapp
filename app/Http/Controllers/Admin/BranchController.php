<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Counter;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount('counters', 'users')->orderBy('branch_code')->get();

        // จัดกลุ่มตามบริษัท สาขาที่ยังไม่มีบริษัทไปอยู่กลุ่มท้ายสุด
        $companies = Company::orderBy('company_code')->get();
        $looseBranches = $branches->whereNull('company_id')->values();
        $branches = $branches->groupBy('company_id');

        return view('admin.branches.index', compact('companies', 'branches', 'looseBranches'));
    }

    public function create(Request $request)
    {
        // กดปุ่มเพิ่มสาขาจากกลุ่มบริษัทไหน ตั้งต้นที่บริษัทนั้น ถ้าไม่ได้ระบุ
        // (หรือระบุบริษัทที่ปิด/ไม่มีอยู่) ใช้บริษัทที่เปิดอยู่ใน sidebar
        $companyId = Company::where('is_active', true)->whereKey($request->integer('company_id'))->value('id')
            ?? auth()->user()->activeCompanyId();

        $branch = new Branch(['company_id' => $companyId]);

        return view('admin.branches.form', ['branch' => $branch, 'isEdit' => false, 'companies' => $this->companies()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_code'  => 'required|string|max:20|unique:branches,branch_code',
            'company_id'   => $this->companyRule(),
            'branch_name'  => 'required|string|max:100',
            'company_name' => 'nullable|string|max:150',
            'type'         => 'required|in:hq,branch',
            'city'         => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:30',
            'address'      => 'nullable|string|max:255',
            'tax_id'       => 'nullable|string|max:30',
            'license_no'   => 'nullable|string|max:30',
            'cut_off_time' => ['nullable', 'date_format:H:i', 'before:12:00'],
            'is_active'    => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['cut_off_time'] = $this->normaliseCutOff($validated['cut_off_time'] ?? null);
        Branch::create($validated);
        return redirect()->route('admin.branches.index')->with('success', 'เพิ่มสาขาเรียบร้อย');
    }

    public function edit(Branch $branch)
    {
        $branch->load('counters');
        return view('admin.branches.form', ['branch' => $branch, 'isEdit' => true, 'companies' => $this->companies()]);
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'branch_code'  => 'required|string|max:20|unique:branches,branch_code,' . $branch->id,
            'company_id'   => $this->companyRule(),
            'branch_name'  => 'required|string|max:100',
            'company_name' => 'nullable|string|max:150',
            'type'         => 'required|in:hq,branch',
            'city'         => 'nullable|string|max:100',
            'phone'        => 'nullable|string|max:30',
            'address'      => 'nullable|string|max:255',
            'tax_id'       => 'nullable|string|max:30',
            'license_no'   => 'nullable|string|max:30',
            'cut_off_time' => ['nullable', 'date_format:H:i', 'before:12:00'],
            'is_active'    => 'boolean',
        ]);
        $validated['is_active'] = $request->boolean('is_active');
        $validated['cut_off_time'] = $this->normaliseCutOff($validated['cut_off_time'] ?? null);
        $branch->update($validated);
        return redirect()->route('admin.branches.index')->with('success', 'แก้ไขสาขาเรียบร้อย');
    }

    public function toggleActive(Branch $branch)
    {
        $branch->update(['is_active' => !$branch->is_active]);
        return redirect()->route('admin.branches.index')
            ->with('success', $branch->branch_name . ' — ' . ($branch->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน'));
    }

    // Counter management within branch
    public function storeCounter(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'counter_code' => 'required|string|max:20|unique:counters,counter_code',
            'counter_name' => 'required|string|max:100',
        ]);
        $branch->counters()->create(array_merge($validated, ['is_active' => true]));
        return redirect()->route('admin.branches.edit', $branch)->with('success', 'เพิ่มเคาน์เตอร์เรียบร้อย');
    }

    public function toggleCounter(Counter $counter)
    {
        $counter->update(['is_active' => !$counter->is_active]);
        return redirect()->route('admin.branches.edit', $counter->branch_id)
            ->with('success', $counter->counter_name . ' — ' . ($counter->is_active ? 'เปิด' : 'ปิด'));
    }

    public function destroyCounter(Counter $counter)
    {
        $branchId = $counter->branch_id;
        $name = $counter->counter_name;
        $counter->delete();
        return redirect()->route('admin.branches.edit', $branchId)->with('success', "ลบ {$name} เรียบร้อย");
    }
    /**
     * บริษัทให้เลือก — บริษัทที่ปิดอยู่ไม่ขึ้น ยกเว้นตอนแก้ไขสาขาที่ยังสังกัดอยู่
     * (ดู view) จึงส่งมาทั้งหมดแล้วให้ view กรอง
     */
    private function companies()
    {
        return Company::orderBy('company_code')->get();
    }

    /**
     * สาขาต้องสังกัดบริษัท ถ้าระบบมีบริษัทแล้ว — สาขาที่ไม่มีบริษัทหลุดจาก
     * ขอบเขตของทุกบริษัท ไม่มีใครเห็นในหน้าที่บีบตามบริษัท
     *
     * @return array<int, string>
     */
    private function companyRule(): array
    {
        return [Company::exists() ? 'required' : 'nullable', 'exists:companies,id'];
    }

    /**
     * ช่องเวลาใน html ส่งมาเป็น H:i เก็บให้เป็น H:i:s เสมอ
     *
     * ไม่ปล่อยให้ฐานข้อมูลเป็นคนเติมให้ เพราะ MySQL เติม sqlite ไม่เติม
     * แล้วพฤติกรรมจะต่างกันระหว่างเทสกับของจริง เว้นว่าง = ใช้ค่ากลางของระบบ
     */
    private function normaliseCutOff(?string $value): ?string
    {
        if ($value === null || trim($value) === "") {
            return null;
        }

        return \Carbon\Carbon::createFromTimeString($value)->format("H:i:s");
    }
}