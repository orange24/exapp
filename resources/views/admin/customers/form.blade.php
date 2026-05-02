@extends('layouts.app')

@section('title', $isEdit ? 'แก้ไขลูกค้า (Edit Customer)' : 'เพิ่มลูกค้า (Add Customer)')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.customers.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; กลับ (Back)</a>
        <h2 class="text-lg font-semibold text-gray-800 mt-1">
            {{ $isEdit ? 'แก้ไขลูกค้า (Edit Customer)' : 'เพิ่มลูกค้า (Add Customer)' }}
        </h2>
    </div>

    <div class="max-w-3xl">
        <form action="{{ $isEdit ? route('admin.customers.update', $customer) : route('admin.customers.store') }}"
              method="POST"
              class="bg-white shadow rounded-lg p-6 space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                {{-- Type --}}
                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700 mb-1">
                        ประเภท (Type) <span class="text-red-500">*</span>
                    </label>
                    <select id="type" name="type"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="individual" {{ old('type', $customer->type) === 'individual' ? 'selected' : '' }}>บุคคลธรรมดา (Individual)</option>
                        <option value="corporate" {{ old('type', $customer->type) === 'corporate' ? 'selected' : '' }}>นิติบุคคล (Corporate)</option>
                    </select>
                    @error('type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- ID Type --}}
                <div>
                    <label for="id_type" class="block text-sm font-medium text-gray-700 mb-1">
                        ประเภทเอกสาร (ID Type) <span class="text-red-500">*</span>
                    </label>
                    <select id="id_type" name="id_type"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">-- เลือก --</option>
                        <option value="passport" {{ old('id_type', $customer->id_type) === 'passport' ? 'selected' : '' }}>Passport</option>
                        <option value="national_id" {{ old('id_type', $customer->id_type) === 'national_id' ? 'selected' : '' }}>บัตรประชาชน (National ID)</option>
                        <option value="corporate_id" {{ old('id_type', $customer->id_type) === 'corporate_id' ? 'selected' : '' }}>เลขนิติบุคคล (Corporate ID)</option>
                    </select>
                    @error('id_type') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- ID Number --}}
                <div>
                    <label for="id_number" class="block text-sm font-medium text-gray-700 mb-1">
                        เลขเอกสาร (ID Number) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="id_number" name="id_number"
                           value="{{ old('id_number', $customer->id_number) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    @error('id_number') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Nationality --}}
                <div>
                    <label for="nationality" class="block text-sm font-medium text-gray-700 mb-1">สัญชาติ (Nationality)</label>
                    <input type="text" id="nationality" name="nationality"
                           value="{{ old('nationality', $customer->nationality) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @error('nationality') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Name TH --}}
                <div>
                    <label for="name_th" class="block text-sm font-medium text-gray-700 mb-1">ชื่อไทย (Thai Name)</label>
                    <input type="text" id="name_th" name="name_th"
                           value="{{ old('name_th', $customer->name_th) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @error('name_th') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Name EN --}}
                <div>
                    <label for="name_en" class="block text-sm font-medium text-gray-700 mb-1">ชื่ออังกฤษ (English Name)</label>
                    <input type="text" id="name_en" name="name_en"
                           value="{{ old('name_en', $customer->name_en) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @error('name_en') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- First Name --}}
                <div>
                    <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">ชื่อจริง (First Name)</label>
                    <input type="text" id="first_name" name="first_name"
                           value="{{ old('first_name', $customer->first_name) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @error('first_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Last Name --}}
                <div>
                    <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">นามสกุล (Last Name)</label>
                    <input type="text" id="last_name" name="last_name"
                           value="{{ old('last_name', $customer->last_name) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @error('last_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Date of Birth --}}
                <div>
                    <label for="date_of_birth" class="block text-sm font-medium text-gray-700 mb-1">วันเกิด (Date of Birth)</label>
                    <input type="date" id="date_of_birth" name="date_of_birth"
                           value="{{ old('date_of_birth', $customer->date_of_birth?->format('Y-m-d')) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @error('date_of_birth') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Passport Expiry --}}
                <div>
                    <label for="passport_expiry" class="block text-sm font-medium text-gray-700 mb-1">Passport หมดอายุ (Expiry)</label>
                    <input type="date" id="passport_expiry" name="passport_expiry"
                           value="{{ old('passport_expiry', $customer->passport_expiry?->format('Y-m-d')) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @error('passport_expiry') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- Phone --}}
                <div>
                    <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">เบอร์โทร (Phone)</label>
                    <input type="text" id="phone" name="phone"
                           value="{{ old('phone', $customer->phone) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    @error('phone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                {{-- KYC Status --}}
                <div>
                    <label for="kyc_status" class="block text-sm font-medium text-gray-700 mb-1">สถานะ KYC</label>
                    <select id="kyc_status" name="kyc_status"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="pending" {{ old('kyc_status', $customer->kyc_status) === 'pending' ? 'selected' : '' }}>รอตรวจสอบ (Pending)</option>
                        <option value="verified" {{ old('kyc_status', $customer->kyc_status) === 'verified' ? 'selected' : '' }}>ยืนยันแล้ว (Verified)</option>
                        <option value="rejected" {{ old('kyc_status', $customer->kyc_status) === 'rejected' ? 'selected' : '' }}>ไม่ผ่าน (Rejected)</option>
                    </select>
                    @error('kyc_status') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Address --}}
            <div>
                <label for="address" class="block text-sm font-medium text-gray-700 mb-1">ที่อยู่ (Address)</label>
                <textarea id="address" name="address" rows="3"
                          class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">{{ old('address', $customer->address) }}</textarea>
                @error('address') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Buttons --}}
            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-[#0e513a] text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
                    {{ $isEdit ? 'บันทึก (Save)' : 'สร้างลูกค้า (Create Customer)' }}
                </button>
                <a href="{{ route('admin.customers.index') }}"
                   class="text-gray-600 hover:text-gray-800 text-sm font-medium">ยกเลิก (Cancel)</a>
            </div>
        </form>
    </div>
@endsection
