@extends('layouts.app')

@section('title', $isEdit ? 'แก้ไขสาขา — ' . $branch->branch_name : 'เพิ่มสาขา')

@section('content')
<div class="mb-4">
    <a href="{{ route('admin.branches.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; กลับ</a>
    <h2 class="text-lg font-semibold text-gray-800 mt-1">
        {{ $isEdit ? 'แก้ไขสาขา — ' . $branch->branch_name : 'เพิ่มสาขา' }}
    </h2>
</div>

@if (session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
        {{ session('success') }}
    </div>
@endif

<div class="max-w-3xl">
    {{-- Branch form --}}
    <form action="{{ $isEdit ? route('admin.branches.update', $branch) : route('admin.branches.store') }}"
          method="POST" class="bg-white shadow rounded-lg p-6 space-y-5">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">รหัสสาขา (Code) <span class="text-red-500">*</span></label>
                <input type="text" name="branch_code" value="{{ old('branch_code', $branch->branch_code) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase font-mono focus:ring-2 focus:ring-green-500"
                       required {{ $isEdit ? 'readonly' : '' }}>
                @error('branch_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อสาขา <span class="text-red-500">*</span></label>
                <input type="text" name="branch_name" value="{{ old('branch_name', $branch->branch_name) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500" required>
                @error('branch_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ประเภท</label>
                <select name="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="branch" {{ old('type', $branch->type) === 'branch' ? 'selected' : '' }}>สาขา</option>
                    <option value="hq" {{ old('type', $branch->type) === 'hq' ? 'selected' : '' }}>สำนักงานใหญ่ (HQ)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เมือง</label>
                <input type="text" name="city" value="{{ old('city', $branch->city) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">โทรศัพท์</label>
                <input type="text" name="phone" value="{{ old('phone', $branch->phone) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="flex items-end">
                <label class="flex items-center gap-3 pb-2 cursor-pointer">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="w-4 h-4 accent-green-600"
                           {{ old('is_active', $branch->is_active ?? true) ? 'checked' : '' }}>
                    <span class="text-sm font-medium text-gray-700">เปิดใช้งาน</span>
                </label>
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่</label>
                <input type="text" name="address" value="{{ old('address', $branch->address) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-[#0e513a] text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d]">
                {{ $isEdit ? 'บันทึก' : 'สร้างสาขา' }}
            </button>
            <a href="{{ route('admin.branches.index') }}" class="text-gray-600 hover:text-gray-800 text-sm">ยกเลิก</a>
        </div>
    </form>

    {{-- Counters (edit mode) --}}
    @if ($isEdit)
        <div class="mt-6 bg-white shadow rounded-lg p-6">
            <h3 class="text-md font-semibold text-gray-800 mb-4">เคาน์เตอร์ในสาขานี้</h3>

            @if ($branch->counters->count() > 0)
                <table class="w-full text-sm border-collapse mb-4">
                    <thead>
                        <tr class="bg-gray-50 border-b">
                            <th class="px-3 py-2 text-left font-medium text-gray-600">รหัส</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-600">ชื่อเคาน์เตอร์</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-600">สถานะ</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-600 w-40">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @foreach ($branch->counters as $counter)
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-mono text-gray-600">{{ $counter->counter_code }}</td>
                                <td class="px-3 py-2 font-semibold text-gray-800">{{ $counter->counter_name }}</td>
                                <td class="px-3 py-2 text-center">
                                    @if ($counter->is_active)
                                        <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700">เปิด</span>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded bg-red-100 text-red-700">ปิด</span>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <form action="{{ route('admin.branches.toggle-counter', $counter) }}" method="POST" class="inline">
                                        @csrf @method('PATCH')
                                        <button type="submit" class="text-xs font-medium {{ $counter->is_active ? 'text-red-600' : 'text-green-600' }} mr-2">
                                            {{ $counter->is_active ? 'ปิด' : 'เปิด' }}
                                        </button>
                                    </form>
                                    <form action="{{ route('admin.branches.destroy-counter', $counter) }}" method="POST" class="inline"
                                          onsubmit="return confirm('ลบ {{ $counter->counter_name }} ?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="text-xs font-medium text-red-600">ลบ</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="text-gray-400 text-sm mb-4">ยังไม่มีเคาน์เตอร์</p>
            @endif

            {{-- Add counter --}}
            <form action="{{ route('admin.branches.store-counter', $branch) }}" method="POST"
                  class="flex items-end gap-2 p-3 bg-blue-50 border border-blue-200 rounded">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">รหัสเคาน์เตอร์</label>
                    <input type="text" name="counter_code" class="w-32 border border-gray-300 rounded px-2 py-1.5 text-sm font-mono uppercase" required placeholder="BKK-C3">
                </div>
                <div class="flex-1">
                    <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อเคาน์เตอร์</label>
                    <input type="text" name="counter_name" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm" required placeholder="เคาน์เตอร์ 3 (BKK)">
                </div>
                <button type="submit" class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 font-medium">
                    + เพิ่ม
                </button>
            </form>
        </div>
    @endif
</div>
@endsection
