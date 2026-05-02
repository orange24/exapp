@extends('layouts.app')

@section('title', $isEdit ? 'แก้ไขบัญชี (Edit Account)' : 'เพิ่มบัญชี (Add Account)')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.accounts.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; กลับ (Back)</a>
        <h2 class="text-lg font-semibold text-gray-800 mt-1">
            {{ $isEdit ? 'แก้ไขบัญชี (Edit Account)' : 'เพิ่มบัญชี (Add Account)' }}
        </h2>
    </div>

    <div class="max-w-2xl">
        <form action="{{ $isEdit ? route('admin.accounts.update', $account) : route('admin.accounts.store') }}"
              method="POST"
              class="bg-white shadow rounded-lg p-6 space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Account Code --}}
                <div>
                    <label for="account_code" class="block text-sm font-medium text-gray-700 mb-1">
                        รหัสบัญชี (Account Code) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="account_code" name="account_code"
                           value="{{ old('account_code', $account->account_code) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           maxlength="20" required>
                    @error('account_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Type --}}
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">
                        ประเภท (Type) <span class="text-red-500">*</span>
                    </label>
                    <select id="type" name="type"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">-- เลือกประเภท --</option>
                        <option value="asset" {{ old('type', $account->type) === 'asset' ? 'selected' : '' }}>สินทรัพย์ (Asset)</option>
                        <option value="liability" {{ old('type', $account->type) === 'liability' ? 'selected' : '' }}>หนี้สิน (Liability)</option>
                        <option value="equity" {{ old('type', $account->type) === 'equity' ? 'selected' : '' }}>ส่วนของเจ้าของ (Equity)</option>
                        <option value="revenue" {{ old('type', $account->type) === 'revenue' ? 'selected' : '' }}>รายได้ (Revenue)</option>
                        <option value="expense" {{ old('type', $account->type) === 'expense' ? 'selected' : '' }}>ค่าใช้จ่าย (Expense)</option>
                    </select>
                    @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Name TH --}}
                <div>
                    <label for="name_th" class="block text-sm font-medium text-gray-700 mb-1">
                        ชื่อไทย (Thai Name) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="name_th" name="name_th"
                           value="{{ old('name_th', $account->name_th) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           required>
                    @error('name_th') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Name EN --}}
                <div>
                    <label for="name_en" class="block text-sm font-medium text-gray-700 mb-1">
                        ชื่ออังกฤษ (English Name)
                    </label>
                    <input type="text" id="name_en" name="name_en"
                           value="{{ old('name_en', $account->name_en) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @error('name_en') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Parent Code --}}
                <div>
                    <label for="parent_code" class="block text-sm font-medium text-gray-700 mb-1">
                        บัญชีแม่ (Parent Account)
                    </label>
                    <select id="parent_code" name="parent_code"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- ไม่มี (Top Level) --</option>
                        @foreach ($parentAccounts as $parent)
                            <option value="{{ $parent->account_code }}"
                                    {{ old('parent_code', $account->parent_code) === $parent->account_code ? 'selected' : '' }}>
                                {{ $parent->account_code }} — {{ $parent->name_th }}
                            </option>
                        @endforeach
                    </select>
                    @error('parent_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Active --}}
                <div class="flex items-end">
                    <div class="flex items-center gap-3 pb-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                               {{ old('is_active', $account->is_active ?? true) ? 'checked' : '' }}>
                        <label for="is_active" class="text-sm font-medium text-gray-700">เปิดใช้งาน (Active)</label>
                    </div>
                </div>
            </div>

            {{-- Buttons --}}
            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-[#0e513a] text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
                    {{ $isEdit ? 'บันทึก (Save)' : 'สร้างบัญชี (Create Account)' }}
                </button>
                <a href="{{ route('admin.accounts.index') }}"
                   class="text-gray-600 hover:text-gray-800 text-sm font-medium">ยกเลิก (Cancel)</a>
            </div>
        </form>
    </div>
@endsection
