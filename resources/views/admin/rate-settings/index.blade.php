@extends('layouts.app')

@section('title', 'ตั้งค่าการคำนวณอัตราแลกเปลี่ยน (Rate Calculation Setting)')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-800">ตั้งค่าการคำนวณอัตราแลกเปลี่ยน (Rate Calculation Setting)</h2>
        <a href="{{ route('admin.rate-settings.create') }}"
           class="bg-[#0e513a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
            + เพิ่มกลุ่ม
        </a>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-300 text-red-700 rounded text-sm">
            {{ session('error') }}
        </div>
    @endif

    @if ($settings->isEmpty())
        <div class="bg-white rounded-lg shadow p-8 text-center text-gray-500">
            ยังไม่มีกลุ่มตั้งค่า — กด "+ เพิ่มกลุ่ม" เพื่อเริ่มต้น
        </div>
    @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($settings as $setting)
                <div class="bg-white rounded-lg shadow p-5 flex flex-col">
                    <div class="flex items-start justify-between mb-3">
                        <div>
                            <h3 class="font-semibold text-gray-800 text-base">{{ $setting->group_name }}</h3>
                            <p class="text-sm text-gray-500 mt-1">
                                เคาน์เตอร์หลัก: <span class="font-medium text-gray-700">{{ $setting->mainCounter?->counter_name ?? '-' }}</span>
                                @if ($setting->mainCounter?->branch)
                                    <span class="text-xs text-gray-400">({{ $setting->mainCounter->branch->branch_name }})</span>
                                @endif
                            </p>
                        </div>
                        @if ($setting->is_active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">Inactive</span>
                        @endif
                    </div>

                    <p class="text-sm text-gray-600 mb-4">
                        เคาน์เตอร์ที่เชื่อม: <span class="font-medium">{{ $setting->counters->count() }}</span> เคาน์เตอร์
                    </p>

                    <div class="mt-auto flex items-center gap-2 pt-3 border-t border-gray-100">
                        <a href="{{ route('admin.rate-settings.edit', $setting) }}"
                           class="text-sm text-blue-600 hover:text-blue-800 font-medium">แก้ไข</a>

                        <form action="{{ route('admin.rate-settings.apply', $setting) }}" method="POST"
                              onsubmit="return confirm('คำนวณราคาและอัปเดตเคาน์เตอร์ทั้งหมดในกลุ่มนี้?')">
                            @csrf
                            <button type="submit" class="text-sm text-green-600 hover:text-green-800 font-medium">
                                คำนวณราคา
                            </button>
                        </form>

                        <form action="{{ route('admin.rate-settings.destroy', $setting) }}" method="POST"
                              onsubmit="return confirm('ลบกลุ่มตั้งค่านี้?')" class="ml-auto">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-sm text-red-600 hover:text-red-800 font-medium">ลบ</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
