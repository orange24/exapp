@extends('layouts.app')

@section('title', 'จัดการสกุลเงิน (Currencies)')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">จัดการสกุลเงิน (Currency Management)</h2>
            <p class="text-sm text-gray-500">ข้อมูลหลักสกุลเงินและ denomination — อัตราแลกเปลี่ยนตั้งที่หน้า "ตั้งราคา" ต่อเคาน์เตอร์</p>
        </div>
        <a href="{{ route('admin.currencies.create') }}"
           class="bg-[#0e513a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
            + เพิ่มสกุลเงิน
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
                        <th class="px-4 py-3 text-left font-medium text-gray-600 w-12">ลำดับ</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600 w-12">ธง</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">รหัส</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ชื่อ (EN)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ชื่อ (TH)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ประเทศ</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">Denominations</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">สถานะ</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($currencies as $currency)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-gray-400">{{ $currency->seq ?? '-' }}</td>
                            <td class="px-4 py-2">
                                <img src="{{ asset('images/flags/' . strtolower($currency->currency_code) . '.png') }}"
                                     alt="{{ $currency->currency_code }}"
                                     class="w-8 h-auto rounded shadow-sm"
                                     onerror="this.style.display='none'">
                            </td>
                            <td class="px-4 py-3 font-mono font-bold text-gray-800">{{ $currency->currency_code }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $currency->currency_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $currency->currency_name_th ?? '-' }}</td>
                            <td class="px-4 py-3 text-gray-500 text-xs">{{ $currency->country ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @if ($currency->denominations_count > 0)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">
                                        {{ $currency->denominations_count }} denom
                                    </span>
                                @else
                                    <span class="text-gray-400 text-xs">-</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($currency->is_active)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">เปิด</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">ปิด</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('admin.currencies.edit', $currency) }}"
                                       class="text-blue-600 hover:text-blue-800 text-xs font-medium">แก้ไข</a>
                                    <form action="{{ route('admin.currencies.toggle-active', $currency) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit"
                                                class="text-xs font-medium {{ $currency->is_active ? 'text-red-600 hover:text-red-800' : 'text-green-600 hover:text-green-800' }}">
                                            {{ $currency->is_active ? 'ปิด' : 'เปิด' }}
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">ไม่พบข้อมูลสกุลเงิน</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($currencies->hasPages())
            <div class="px-4 py-3 border-t">
                {{ $currencies->links() }}
            </div>
        @endif
    </div>
@endsection
