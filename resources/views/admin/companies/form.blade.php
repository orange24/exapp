@extends('layouts.app')

@section('title', $company->exists ? 'แก้ไขบริษัท' : 'เพิ่มบริษัท')

@section('content')
<div class="max-w-2xl">
    <h1 class="text-2xl font-bold mb-1" style="color:#0e513a">
        {{ $company->exists ? 'แก้ไขบริษัท' : 'เพิ่มบริษัท' }}
    </h1>
    <p class="text-sm text-gray-500 mb-5">
        ข้อมูลนี้ใช้บนเอกสารและรายงานที่ยื่นหน่วยงาน — ชื่อและเลขใบอนุญาตต้องตรงกับที่จดทะเบียนไว้
    </p>

    <form method="POST"
          action="{{ $company->exists ? route('admin.companies.update', $company) : route('admin.companies.store') }}"
          class="bg-white rounded-xl shadow border border-gray-200 p-5">
        @csrf
        @if ($company->exists)
            @method('PUT')
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    รหัสบริษัท <span class="text-red-600">*</span>
                </label>
                <input type="text" name="company_code" maxlength="20"
                       value="{{ old('company_code', $company->company_code) }}" required
                       class="w-full border rounded px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-gray-500">ตัวย่อสั้น ๆ ใช้อ้างอิงภายใน เช่น YESSOUTH</p>
                @error('company_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    ชื่อบริษัท <span class="text-red-600">*</span>
                </label>
                <input type="text" name="company_name" maxlength="191"
                       value="{{ old('company_name', $company->company_name) }}" required
                       class="w-full border rounded px-3 py-2 text-sm">
                @error('company_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เลขประจำตัวผู้เสียภาษี</label>
                <input type="text" name="tax_id" maxlength="20"
                       value="{{ old('tax_id', $company->tax_id) }}"
                       class="w-full border rounded px-3 py-2 text-sm">
                @error('tax_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เลขใบอนุญาต</label>
                <input type="text" name="license_no" maxlength="50"
                       value="{{ old('license_no', $company->license_no) }}"
                       class="w-full border rounded px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-gray-500">ใบอนุญาตแลกเปลี่ยนเงินตราต่างประเทศ ใช้ในรายงาน ธปท.</p>
                @error('license_no') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">โทรศัพท์</label>
                <input type="text" name="phone" maxlength="30"
                       value="{{ old('phone', $company->phone) }}"
                       class="w-full border rounded px-3 py-2 text-sm">
                @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่</label>
                <input type="text" name="address" maxlength="191"
                       value="{{ old('address', $company->address) }}"
                       class="w-full border rounded px-3 py-2 text-sm">
                @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <label class="flex items-center gap-2 mt-5 text-sm text-gray-700">
            <input type="checkbox" name="is_active" value="1"
                   {{ old('is_active', $company->exists ? $company->is_active : true) ? 'checked' : '' }}>
            เปิดใช้งาน
        </label>

        <div class="flex gap-2 mt-6">
            <button type="submit" class="px-5 py-2 rounded text-white text-sm font-medium" style="background-color:#0e513a">
                {{ $company->exists ? 'บันทึกการแก้ไข' : 'เพิ่มบริษัท' }}
            </button>
            <a href="{{ route('admin.companies.index') }}"
               class="px-5 py-2 rounded text-sm border border-gray-300 text-gray-700">ยกเลิก</a>
        </div>
    </form>
</div>
@endsection
