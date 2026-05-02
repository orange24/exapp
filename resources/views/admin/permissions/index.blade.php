@extends('layouts.app')

@section('title', 'จัดการสิทธิ์ (Permission Matrix)')

@section('content')
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">จัดการสิทธิ์ (Permission Matrix)</h2>
        <p class="text-sm text-gray-500">กำหนดสิทธิ์การเข้าถึงแต่ละโมดูลตามบทบาท</p>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    <form action="{{ route('admin.permissions.update') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-[#0e513a] text-white">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium" rowspan="2">โมดูล (Module)</th>
                            <th class="px-4 py-3 text-left font-medium" rowspan="2">สิทธิ์ (Action)</th>
                            @foreach ($roles as $role)
                                <th class="px-4 py-3 text-center font-medium">{{ $role->display_name }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @php
                            $moduleLabels = [
                                'module1' => 'จัดการผู้ใช้ (User Management)',
                                'module2' => 'ข้อมูลหลัก (Master Data)',
                                'module3' => 'ธุรกรรม (Transactions)',
                                'module4' => 'บัญชี (Accounting)',
                                'module5' => 'แดชบอร์ด (Dashboard)',
                                'module6' => 'รายงาน (Reports)',
                            ];
                            $actionLabels = [
                                'read'   => 'อ่าน (Read)',
                                'write'  => 'เขียน (Write)',
                                'print'  => 'พิมพ์ (Print)',
                                'export' => 'ส่งออก (Export)',
                                'delete' => 'ลบ (Delete)',
                            ];
                        @endphp
                        @foreach ($modules as $module => $perms)
                            @foreach ($actions as $idx => $action)
                                @php
                                    $perm = $perms->firstWhere('action', $action);
                                @endphp
                                @if ($perm)
                                    <tr class="hover:bg-gray-50 {{ $idx === 0 ? 'border-t-2 border-gray-300' : '' }}">
                                        @if ($idx === 0)
                                            <td class="px-4 py-2 font-medium text-gray-800 align-top" rowspan="{{ count($actions) }}">
                                                {{ $moduleLabels[$module] ?? $module }}
                                            </td>
                                        @endif
                                        <td class="px-4 py-2 text-gray-600">
                                            {{ $actionLabels[$action] ?? $action }}
                                        </td>
                                        @foreach ($roles as $role)
                                            <td class="px-4 py-2 text-center">
                                                <input type="checkbox"
                                                       name="role[{{ $role->id }}][]"
                                                       value="{{ $perm->id }}"
                                                       class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                                                       {{ isset($assigned[$role->id][$perm->id]) ? 'checked' : '' }}>
                                            </td>
                                        @endforeach
                                    </tr>
                                @endif
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <button type="submit"
                    class="bg-[#0e513a] text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
                บันทึกสิทธิ์ (Save Permissions)
            </button>
        </div>
    </form>
@endsection
