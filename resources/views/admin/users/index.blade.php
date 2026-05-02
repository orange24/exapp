@extends('layouts.app')

@section('title', 'จัดการผู้ใช้ (Users)')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">จัดการผู้ใช้ (User Management)</h2>
            <p class="text-sm text-gray-500">รายการผู้ใช้ทั้งหมดในระบบ</p>
        </div>
        <a href="{{ route('admin.users.create') }}"
           class="bg-[#0e513a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
            + เพิ่มผู้ใช้ (Add User)
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">#</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ชื่อ (Name)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">อีเมล (Email)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">สาขา (Branch)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">บทบาท (Role)</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">สถานะ (Status)</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">จัดการ (Actions)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($users as $user)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-500">{{ $user->id }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $user->branch?->branch_name ?? '-' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $user->role?->name === 'admin' ? 'bg-purple-100 text-purple-800' : '' }}
                                    {{ $user->role?->name === 'superadmin' ? 'bg-red-100 text-red-800' : '' }}
                                    {{ $user->role?->name === 'staff' ? 'bg-blue-100 text-blue-800' : '' }}
                                    {{ $user->role?->name === 'auditor' ? 'bg-yellow-100 text-yellow-800' : '' }}">
                                    {{ $user->role?->display_name ?? '-' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($user->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">
                                        เปิดใช้งาน
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">
                                        ปิดใช้งาน
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('admin.users.edit', $user) }}"
                                       class="text-blue-600 hover:text-blue-800 text-xs font-medium">
                                        แก้ไข
                                    </a>
                                    <form action="{{ route('admin.users.toggle-active', $user) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="text-xs font-medium {{ $user->is_active ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' }}">
                                            {{ $user->is_active ? 'ปิด' : 'เปิด' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">ไม่พบข้อมูลผู้ใช้</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($users->hasPages())
            <div class="px-4 py-3 border-t">
                {{ $users->links() }}
            </div>
        @endif
    </div>
@endsection
