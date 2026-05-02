@extends('layouts.app')

@section('title', 'ทะเบียนลูกค้า (Customer Registry)')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">ทะเบียนลูกค้า (Customer Registry)</h2>
            <p class="text-sm text-gray-500">จัดการข้อมูลลูกค้าและ KYC</p>
        </div>
        <a href="{{ route('admin.customers.create') }}"
           class="bg-[#0e513a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
            + เพิ่มลูกค้า (Add Customer)
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Search / Filter --}}
    <div class="bg-white shadow rounded-lg p-4 mb-4">
        <form action="{{ route('admin.customers.index') }}" method="GET" class="flex flex-wrap items-end gap-3">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs font-medium text-gray-600 mb-1">ค้นหา (Search)</label>
                <input type="text" name="search" value="{{ request('search') }}"
                       placeholder="ชื่อ, เลขบัตร, เบอร์โทร..."
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">ประเภทเอกสาร (ID Type)</label>
                <select name="id_type"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">ทั้งหมด</option>
                    <option value="passport" {{ request('id_type') === 'passport' ? 'selected' : '' }}>Passport</option>
                    <option value="national_id" {{ request('id_type') === 'national_id' ? 'selected' : '' }}>บัตรประชาชน</option>
                    <option value="corporate_id" {{ request('id_type') === 'corporate_id' ? 'selected' : '' }}>เลขนิติบุคคล</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">KYC</label>
                <select name="kyc_status"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">ทั้งหมด</option>
                    <option value="pending" {{ request('kyc_status') === 'pending' ? 'selected' : '' }}>รอตรวจสอบ</option>
                    <option value="verified" {{ request('kyc_status') === 'verified' ? 'selected' : '' }}>ยืนยันแล้ว</option>
                    <option value="rejected" {{ request('kyc_status') === 'rejected' ? 'selected' : '' }}>ไม่ผ่าน</option>
                </select>
            </div>
            <button type="submit"
                    class="bg-[#0e513a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
                ค้นหา
            </button>
            <a href="{{ route('admin.customers.index') }}" class="text-sm text-gray-500 hover:text-gray-700">ล้าง</a>
        </form>
    </div>

    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">รูปภาพ</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ประเภทเอกสาร (ID Type)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">เลขเอกสาร (ID Number)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ชื่อ (Name)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">สัญชาติ (Nationality)</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">KYC</th>
                        <th class="px-4 py-3 text-center font-medium text-gray-600">จัดการ (Actions)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($customers as $customer)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3">
                                @if ($customer->passport_photo)
                                    <img src="{{ asset('storage/' . $customer->passport_photo) }}"
                                         alt="Photo" class="w-10 h-10 rounded object-cover">
                                @else
                                    <div class="w-10 h-10 rounded bg-gray-200 flex items-center justify-center text-gray-400 text-xs">N/A</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600">
                                @switch($customer->id_type)
                                    @case('passport') Passport @break
                                    @case('national_id') บัตรประชาชน @break
                                    @case('corporate_id') เลขนิติบุคคล @break
                                    @default {{ $customer->id_type }}
                                @endswitch
                            </td>
                            <td class="px-4 py-3 font-mono text-gray-800">{{ $customer->id_number }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $customer->display_name }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $customer->nationality ?? '-' }}</td>
                            <td class="px-4 py-3 text-center">
                                @switch($customer->kyc_status)
                                    @case('verified')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">ยืนยันแล้ว</span>
                                        @break
                                    @case('rejected')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">ไม่ผ่าน</span>
                                        @break
                                    @default
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">รอตรวจสอบ</span>
                                @endswitch
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <a href="{{ route('admin.customers.show', $customer) }}"
                                       class="text-gray-600 hover:text-gray-800 text-xs font-medium">ดู</a>
                                    <a href="{{ route('admin.customers.edit', $customer) }}"
                                       class="text-blue-600 hover:text-blue-800 text-xs font-medium">แก้ไข</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">ไม่พบข้อมูลลูกค้า</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="px-4 py-3 border-t">
                {{ $customers->links() }}
            </div>
        @endif
    </div>
@endsection
