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
              x-data="userForm(@js((string) old('role_id', $user->role_id)), @js($roles->pluck('name', 'id')))"
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

            {{-- Role --}}
            <div>
                <label for="role_id" class="block text-sm font-medium text-gray-700 mb-1">
                    บทบาท (Role) <span class="text-red-500">*</span>
                </label>
                <select id="role_id" name="role_id" x-model="roleId"
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

            {{-- Companies + Branches --}}
            {{-- staff/branch_manager ประจำสาขาจึงต้องเลือกสาขา ส่วน auditor/trader
                 เลือกแค่บริษัทแล้วเห็นทุกสาขาของบริษัทนั้น admin เข้าได้ทุกบริษัท --}}
            @php
                $checkedCompanies = array_map('intval', old('company_ids', $companyIds));
                $checkedBranches = array_map('intval', old('branch_ids', $branchIds));
            @endphp
            <div>
                <div class="block text-sm font-medium text-gray-700 mb-1">
                    บริษัทและสาขา (Companies &amp; Branches)
                    <span x-show="needsCompanies() || needsBranches()" class="text-red-500">*</span>
                </div>

                <p x-show="isAdmin()" x-cloak class="text-sm text-gray-500 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                    ผู้ดูแลระบบเข้าได้ทุกบริษัทและทุกสาขาอยู่แล้ว ไม่ต้องเลือก
                </p>

                <div x-show="!isAdmin()" class="space-y-3">
                    <p class="text-xs text-gray-500" x-show="needsBranches()">
                        เลือกบริษัท แล้วเลือกสาขาที่ลงเวรได้ใต้บริษัทนั้น (เลือกได้หลายสาขา)
                    </p>
                    <p class="text-xs text-gray-500" x-show="!needsBranches()" x-cloak>
                        บทบาทนี้เห็นทุกสาขาของบริษัทที่เลือก ไม่ต้องเลือกสาขา
                    </p>

                    @foreach ($companies as $company)
                        <div class="border border-gray-200 rounded-lg p-3" data-company="{{ $company->id }}">
                            <label class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                                <input type="checkbox" name="company_ids[]" value="{{ $company->id }}"
                                       class="w-4 h-4 rounded border-gray-300 company-box"
                                       @change="companyToggled($event.target)"
                                       {{ in_array($company->id, $checkedCompanies, true) ? 'checked' : '' }}>
                                {{ $company->company_name }}
                                <span class="text-xs font-normal text-gray-400">{{ $company->company_code }}</span>
                            </label>

                            <div x-show="needsBranches()" class="mt-2 ml-6 grid grid-cols-1 sm:grid-cols-2 gap-1">
                                @forelse ($company->branches as $branch)
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}"
                                               class="w-4 h-4 rounded border-gray-300 branch-box"
                                               @change="branchToggled($event.target)"
                                               {{ in_array($branch->id, $checkedBranches, true) ? 'checked' : '' }}>
                                        {{ $branch->branch_code }} — {{ $branch->branch_name }}
                                    </label>
                                @empty
                                    <p class="text-xs text-gray-400">ยังไม่มีสาขาในบริษัทนี้</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach

                    @if ($looseBranches->isNotEmpty())
                        <div x-show="needsBranches()" class="border border-dashed border-gray-300 rounded-lg p-3">
                            <div class="text-sm font-semibold text-gray-500 mb-2">สาขาที่ยังไม่ได้ผูกบริษัท</div>
                            <div class="ml-6 grid grid-cols-1 sm:grid-cols-2 gap-1">
                                @foreach ($looseBranches as $branch)
                                    <label class="flex items-center gap-2 text-sm text-gray-700">
                                        <input type="checkbox" name="branch_ids[]" value="{{ $branch->id }}"
                                               class="w-4 h-4 rounded border-gray-300"
                                               {{ in_array($branch->id, $checkedBranches, true) ? 'checked' : '' }}>
                                        {{ $branch->branch_code }} — {{ $branch->branch_name }}
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                @error('company_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                @error('branch_ids') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
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

    <script>
        function userForm(roleId, roleNames) {
            return {
                roleId: roleId,
                roleName() { return roleNames[this.roleId] ?? ''; },
                isAdmin() { return ['admin', 'superadmin'].includes(this.roleName()); },
                // ต้องตรงกับ UserController::needsBranches()
                needsBranches() { return this.roleId !== '' && !this.isAdmin() && !['auditor', 'trader'].includes(this.roleName()); },
                needsCompanies() { return this.roleId !== '' && !this.isAdmin(); },

                // ติ๊กสาขาแล้วติ๊กบริษัทของสาขานั้นให้ด้วย
                branchToggled(box) {
                    if (box.checked) {
                        box.closest('[data-company]').querySelector('.company-box').checked = true;
                    }
                },

                // เอาบริษัทออกแล้วเอาสาขาของบริษัทนั้นออกด้วย
                companyToggled(box) {
                    if (!box.checked) {
                        box.closest('[data-company]').querySelectorAll('.branch-box').forEach(b => b.checked = false);
                    }
                },
            };
        }
    </script>
@endsection
