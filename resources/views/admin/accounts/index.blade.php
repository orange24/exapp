@extends('layouts.app')

@section('title', 'ผังบัญชี (Chart of Accounts)')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">ผังบัญชี (Chart of Accounts)</h2>
            <p class="text-sm text-gray-500">จัดการรหัสบัญชีแบบลำดับชั้น</p>
        </div>
        <a href="{{ route('admin.accounts.create') }}"
           class="bg-[#0e513a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
            + เพิ่มบัญชี (Add Account)
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    @php
        $typeLabels = [
            'asset'     => ['สินทรัพย์ (Assets)',       'bg-blue-50 border-blue-200 text-blue-800'],
            'liability' => ['หนี้สิน (Liabilities)',    'bg-red-50 border-red-200 text-red-800'],
            'equity'    => ['ส่วนของเจ้าของ (Equity)',  'bg-purple-50 border-purple-200 text-purple-800'],
            'revenue'   => ['รายได้ (Revenue)',          'bg-green-50 border-green-200 text-green-800'],
            'expense'   => ['ค่าใช้จ่าย (Expenses)',    'bg-yellow-50 border-yellow-200 text-yellow-800'],
        ];
    @endphp

    <div class="space-y-4">
        @foreach (['asset', 'liability', 'equity', 'revenue', 'expense'] as $type)
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-4 py-3 border-b {{ $typeLabels[$type][1] ?? 'bg-gray-50' }}">
                    <h3 class="text-sm font-bold">{{ $typeLabels[$type][0] ?? ucfirst($type) }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 border-b">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600 w-32">รหัส (Code)</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">ชื่อไทย (Thai Name)</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">ชื่ออังกฤษ (English Name)</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-600 w-20">สถานะ</th>
                                <th class="px-4 py-2 text-center font-medium text-gray-600 w-28">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($tree[$type] ?? [] as $parent)
                                {{-- Parent account --}}
                                <tr class="hover:bg-gray-50 font-medium">
                                    <td class="px-4 py-2 font-mono text-gray-800">{{ $parent->account_code }}</td>
                                    <td class="px-4 py-2 text-gray-800">{{ $parent->name_th }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $parent->name_en ?? '-' }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @if ($parent->is_active)
                                            <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                                        @else
                                            <span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="{{ route('admin.accounts.edit', $parent) }}"
                                               class="text-blue-600 hover:text-blue-800 text-xs">แก้ไข</a>
                                            <form action="{{ route('admin.accounts.toggle-active', $parent) }}" method="POST" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="text-xs {{ $parent->is_active ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' }}">
                                                    {{ $parent->is_active ? 'ปิด' : 'เปิด' }}
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                {{-- Child accounts --}}
                                @foreach ($parent->childAccounts ?? [] as $child)
                                    <tr class="hover:bg-gray-50 bg-gray-50/50">
                                        <td class="px-4 py-2 pl-10 font-mono text-gray-600">{{ $child->account_code }}</td>
                                        <td class="px-4 py-2 pl-10 text-gray-700">{{ $child->name_th }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $child->name_en ?? '-' }}</td>
                                        <td class="px-4 py-2 text-center">
                                            @if ($child->is_active)
                                                <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                                            @else
                                                <span class="inline-block w-2 h-2 rounded-full bg-red-500"></span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <a href="{{ route('admin.accounts.edit', $child) }}"
                                                   class="text-blue-600 hover:text-blue-800 text-xs">แก้ไข</a>
                                                <form action="{{ route('admin.accounts.toggle-active', $child) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                            class="text-xs {{ $child->is_active ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' }}">
                                                        {{ $child->is_active ? 'ปิด' : 'เปิด' }}
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-4 text-center text-gray-400">ไม่มีบัญชีในหมวดนี้</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    </div>
@endsection
