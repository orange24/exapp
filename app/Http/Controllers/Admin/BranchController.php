<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Counter;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index()
    {
        $branches = Branch::withCount('counters', 'users')->orderBy('branch_code')->get();
        return view('admin.branches.index', compact('branches'));
    }

    public function create()
    {
        return view('admin.branches.form', ['branch' => new Branch(), 'isEdit' => false]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_code'  => 'required|string|max:20|unique:branches,branch_code',
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
        return view('admin.branches.form', ['branch' => $branch, 'isEdit' => true]);
    }

    public function update(Request $request, Branch $branch)
    {
        $validated = $request->validate([
            'branch_code'  => 'required|string|max:20|unique:branches,branch_code,' . $branch->id,
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