@extends('layouts.app')

@section('title', $isEdit ? 'แก้ไขผู้ใช้ (Edit User)' : 'เพิ่มผู้ใช้ (Add User)')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.users.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; กลับ (Back)</a>
        <h2 class="text-lg font-semibold text-gray-800 mt-1">
            {{ $isEdit ? 'แก้ไขผู้ใช้ (Edit User)' : 'เพิ่มผู้ใช้ (Add User)' }}
        </h2>
    </div>

    <div class="max-w-2xl">
        <form action="{{ $isEdit ? route('admin.users.update', $user) : route('admin.users.store') }}"
              method="POST"
              class="bg-white shadow rounded-lg p-6 space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            {{-- Name --}}
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                    ชื่อ (Name) <span class="text-red-500">*</span>
                </label>
                <input type="text" id="name" name="name" value="{{ old('name', $user->name) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       required>
                @error('name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">
                    อีเมล (Email) <span class="text-red-500">*</span>
                </label>
                <input type="email" id="email" name="email" value="{{ old('email', $user->email) }}"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       required>
                @error('email') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                    รหัสผ่าน (Password) {{ $isEdit ? '' : '*' }}
                </label>
                <input type="password" id="password" name="password"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       {{ $isEdit ? '' : 'required' }}
                       minlength="8">
                @if ($isEdit)
                    <p class="mt-1 text-xs text-gray-500">เว้นว่างหากไม่ต้องการเปลี่ยน (Leave blank to keep current)</p>
                @endif
                @error('password') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Confirm Password --}}
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                    ยืนยันรหัสผ่าน (Confirm Password)
                </label>
                <input type="password" id="password_confirmation" name="password_confirmation"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            {{-- Branch --}}
            <div>
                <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">
                    สาขา (Branch) <span class="text-red-500">*</span>
                </label>
                <select id="branch_id" name="branch_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required>
                    <option value="">-- เลือกสาขา --</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}"
                                {{ old('branch_id', $user->branch_id) == $branch->id ? 'selected' : '' }}>
                            {{ $branch->branch_code }} — {{ $branch->branch_name }}
                        </option>
                    @endforeach
                </select>
                @error('branch_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Role --}}
            <div>
                <label for="role_id" class="block text-sm font-medium text-gray-700 mb-1">
                    บทบาท (Role) <span class="text-red-500">*</span>
                </label>
                <select id="role_id" name="role_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required>
                    <option value="">-- เลือกบทบาท --</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}"
                                {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                            {{ $role->display_name }} ({{ $role->name }})
                        </option>
                    @endforeach
                </select>
                @error('role_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            {{-- Active --}}
            <div class="flex items-center gap-3">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" id="is_active" name="is_active" value="1"
                       class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                       {{ old('is_active', $user->is_active ?? true) ? 'checked' : '' }}>
                <label for="is_active" class="text-sm font-medium text-gray-700">
                    เปิดใช้งาน (Active)
                </label>
            </div>

            {{-- Buttons --}}
            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-[#0e513a] text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
                    {{ $isEdit ? 'บันทึก (Save)' : 'สร้างผู้ใช้ (Create User)' }}
                </button>
                <a href="{{ route('admin.users.index') }}"
                   class="text-gray-600 hover:text-gray-800 text-sm font-medium">
                    ยกเลิก (Cancel)
                </a>
            </div>
        </form>
    </div>
@endsection
