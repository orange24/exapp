@extends('layouts.app')

@section('title', 'รายละเอียดลูกค้า (Customer Detail)')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.customers.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; กลับ (Back)</a>
        <h2 class="text-lg font-semibold text-gray-800 mt-1">รายละเอียดลูกค้า (Customer Detail)</h2>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Customer Info --}}
        <div class="lg:col-span-2 bg-white shadow rounded-lg p-6">
            <div class="flex items-start gap-4">
                @if ($customer->passport_photo)
                    <img src="{{ asset('storage/' . $customer->passport_photo) }}"
                         alt="Photo" class="w-24 h-24 rounded-lg object-cover">
                @else
                    <div class="w-24 h-24 rounded-lg bg-gray-200 flex items-center justify-center text-gray-400">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                @endif
                <div class="flex-1">
                    <h3 class="text-xl font-bold text-gray-800">{{ $customer->display_name }}</h3>
                    <p class="text-sm text-gray-500">{{ $customer->type === 'individual' ? 'บุคคลธรรมดา' : 'นิติบุคคล' }}</p>
                    <div class="mt-2">
                        @switch($customer->kyc_status)
                            @case('verified')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">KYC ยืนยันแล้ว</span>
                                @break
                            @case('rejected')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">KYC ไม่ผ่าน</span>
                                @break
                            @default
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">KYC รอตรวจสอบ</span>
                        @endswitch
                    </div>
                </div>
                <a href="{{ route('admin.customers.edit', $customer) }}"
                   class="bg-[#0e513a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
                    แก้ไข
                </a>
            </div>

            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="text-gray-500">ประเภทเอกสาร (ID Type)</p>
                    <p class="font-medium text-gray-800">{{ $customer->id_type }}</p>
                </div>
                <div>
                    <p class="text-gray-500">เลขเอกสาร (ID Number)</p>
                    <p class="font-medium font-mono text-gray-800">{{ $customer->id_number }}</p>
                </div>
                <div>
                    <p class="text-gray-500">ชื่อไทย (Thai Name)</p>
                    <p class="font-medium text-gray-800">{{ $customer->name_th ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-gray-500">ชื่ออังกฤษ (English Name)</p>
                    <p class="font-medium text-gray-800">{{ $customer->name_en ?? ($customer->first_name . ' ' . $customer->last_name) }}</p>
                </div>
                <div>
                    <p class="text-gray-500">สัญชาติ (Nationality)</p>
                    <p class="font-medium text-gray-800">{{ $customer->nationality ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-gray-500">วันเกิด (Date of Birth)</p>
                    <p class="font-medium text-gray-800">{{ $customer->date_of_birth?->format('d/m/Y') ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-gray-500">Passport หมดอายุ (Passport Expiry)</p>
                    <p class="font-medium text-gray-800">{{ $customer->passport_expiry?->format('d/m/Y') ?? '-' }}</p>
                </div>
                <div>
                    <p class="text-gray-500">เบอร์โทร (Phone)</p>
                    <p class="font-medium text-gray-800">{{ $customer->phone ?? '-' }}</p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-gray-500">ที่อยู่ (Address)</p>
                    <p class="font-medium text-gray-800">{{ $customer->address ?? '-' }}</p>
                </div>
            </div>
        </div>

        {{-- Documents --}}
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">เอกสาร (Documents)</h3>
            @if ($documents->isNotEmpty())
                <div class="space-y-3">
                    @foreach ($documents as $doc)
                        <div class="flex items-center gap-3 p-2 bg-gray-50 rounded">
                            <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-700 truncate">{{ $doc->original_name ?? $doc->doc_type }}</p>
                                <p class="text-xs text-gray-500">{{ $doc->doc_type }} &middot; {{ $doc->captured_at?->format('d/m/Y') }}</p>
                                @if ($doc->expiry_date)
                                    <p class="text-xs {{ $doc->expiry_date->isPast() ? 'text-red-600' : 'text-gray-500' }}">
                                        หมดอายุ: {{ $doc->expiry_date->format('d/m/Y') }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-sm text-gray-500">ไม่มีเอกสาร</p>
            @endif
        </div>
    </div>

    {{-- Transaction History --}}
    <div class="mt-4 bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b">
            <h3 class="text-sm font-semibold text-gray-800">ประวัติธุรกรรม (Transaction History)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b">
                    <tr>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">เลขที่ (TX #)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">ประเภท (Type)</th>
                        <th class="px-4 py-3 text-right font-medium text-gray-600">จำนวน (Amount)</th>
                        <th class="px-4 py-3 text-left font-medium text-gray-600">วันที่ (Date)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($customer->transactions as $tx)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 font-mono text-gray-800">{{ $tx->tx_number ?? $tx->id }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    {{ $tx->type === 'buy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $tx->type === 'buy' ? 'ซื้อ' : 'ขาย' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono">{{ number_format($tx->total_thb ?? 0, 2) }}</td>
                            <td class="px-4 py-3 text-gray-600">{{ $tx->created_at->format('d/m/Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-6 text-center text-gray-500">ไม่มีประวัติธุรกรรม</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
