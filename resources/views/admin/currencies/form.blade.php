@extends('layouts.app')

@section('title', $isEdit ? 'แก้ไขสกุลเงิน — ' . $currency->currency_code : 'เพิ่มสกุลเงิน')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.currencies.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; กลับ</a>
        <h2 class="text-lg font-semibold text-gray-800 mt-1">
            {{ $isEdit ? 'แก้ไขสกุลเงิน — ' . $currency->currency_code : 'เพิ่มสกุลเงิน' }}
        </h2>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    <div class="max-w-3xl">
        {{-- ส่วนที่ 1: ข้อมูลสกุลเงิน --}}
        <form action="{{ $isEdit ? route('admin.currencies.update', $currency) : route('admin.currencies.store') }}"
              method="POST"
              class="bg-white shadow rounded-lg p-6 space-y-5">
            @csrf
            @if ($isEdit) @method('PUT') @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label for="currency_code" class="block text-sm font-medium text-gray-700 mb-1">
                        รหัสสกุลเงิน (Code) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="currency_code" name="currency_code"
                           value="{{ old('currency_code', $currency->currency_code) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm uppercase font-mono font-bold focus:ring-2 focus:ring-blue-500"
                           maxlength="10" required {{ $isEdit ? 'readonly' : '' }}>
                    @if ($isEdit)
                        <p class="mt-1 text-xs text-gray-400">รหัสไม่สามารถเปลี่ยนได้</p>
                    @endif
                    @error('currency_code') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700 mb-1">ประเทศ (Country)</label>
                    <input type="text" id="country" name="country"
                           value="{{ old('country', $currency->country) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="currency_name" class="block text-sm font-medium text-gray-700 mb-1">
                        ชื่อภาษาอังกฤษ (Name EN) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="currency_name" name="currency_name"
                           value="{{ old('currency_name', $currency->currency_name) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                           required>
                    @error('currency_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="currency_name_th" class="block text-sm font-medium text-gray-700 mb-1">ชื่อภาษาไทย (Name TH)</label>
                    <input type="text" id="currency_name_th" name="currency_name_th"
                           value="{{ old('currency_name_th', $currency->currency_name_th) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="seq" class="block text-sm font-medium text-gray-700 mb-1">ลำดับการแสดง (Sort Order)</label>
                    <input type="number" id="seq" name="seq"
                           value="{{ old('seq', $currency->seq) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                           min="0">
                </div>

                <div class="flex items-end">
                    <div class="flex items-center gap-3 pb-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               class="w-4 h-4 text-blue-600 rounded border-gray-300"
                               {{ old('is_active', $currency->is_active ?? true) ? 'checked' : '' }}>
                        <label for="is_active" class="text-sm font-medium text-gray-700">เปิดใช้งาน (Active)</label>
                    </div>
                </div>
            </div>

            @if ($isEdit)
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded border border-gray-200">
                    <img src="{{ asset('images/flags/' . strtolower($currency->currency_code) . '.png') }}"
                         alt="{{ $currency->currency_code }}"
                         class="w-10 h-auto rounded shadow-sm"
                         onerror="this.nextElementSibling.style.display='block'; this.style.display='none';">
                    <span class="text-sm text-gray-500 hidden">ไม่มีรูปธงชาติ</span>
                    <span class="text-sm text-gray-500">ธงชาติ: <code class="font-mono text-xs bg-gray-200 px-1 rounded">public/images/flags/{{ strtolower($currency->currency_code) }}.png</code></span>
                </div>
            @endif

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="bg-[#0e513a] text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d]">
                    {{ $isEdit ? 'บันทึก' : 'สร้างสกุลเงิน' }}
                </button>
                <a href="{{ route('admin.currencies.index') }}" class="text-gray-600 hover:text-gray-800 text-sm">ยกเลิก</a>
            </div>
        </form>

        {{-- ส่วนที่ 2: จัดการ Denominations (เฉพาะตอน edit) --}}
        @if ($isEdit)
            <div class="mt-6 bg-white shadow rounded-lg p-6" x-data="{ editingId: null }">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-md font-semibold text-gray-800">
                        Denominations ของ {{ $currency->currency_code }}
                    </h3>
                    <span class="text-xs text-gray-400">เช่น 100-50, 20-10, 5, 2-1</span>
                </div>

                {{-- Denomination list --}}
                @php $denoms = $currency->denominations; @endphp

                @if ($denoms->count() > 0)
                    <table class="w-full text-sm border-collapse mb-4">
                        <thead>
                            <tr class="bg-gray-50 border-b">
                                <th class="px-3 py-2 text-left font-medium text-gray-600 w-12">ลำดับ</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Denomination</th>
                                <th class="px-3 py-2 text-left font-medium text-gray-600">Display</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-600 w-16">สถานะ</th>
                                <th class="px-3 py-2 text-center font-medium text-gray-600 w-40">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($denoms as $denom)
                                <tr class="hover:bg-gray-50">
                                    {{-- View mode --}}
                                    <template x-if="editingId !== {{ $denom->id }}">
                                        <td class="px-3 py-2 text-gray-400" colspan="0">{{ $denom->seq }}</td>
                                    </template>
                                    <template x-if="editingId !== {{ $denom->id }}">
                                        <td class="px-3 py-2 font-semibold text-gray-800">{{ $denom->denom_label }}</td>
                                    </template>
                                    <template x-if="editingId !== {{ $denom->id }}">
                                        <td class="px-3 py-2 text-gray-600">{{ $denom->display_name }}</td>
                                    </template>
                                    <template x-if="editingId !== {{ $denom->id }}">
                                        <td class="px-3 py-2 text-center">
                                            @if ($denom->is_active)
                                                <span class="text-xs px-2 py-0.5 rounded bg-green-100 text-green-700">เปิด</span>
                                            @else
                                                <span class="text-xs px-2 py-0.5 rounded bg-red-100 text-red-700">ปิด</span>
                                            @endif
                                        </td>
                                    </template>
                                    <template x-if="editingId !== {{ $denom->id }}">
                                        <td class="px-3 py-2 text-center">
                                            <button @click="editingId = {{ $denom->id }}"
                                                    type="button"
                                                    class="text-blue-600 hover:text-blue-800 text-xs font-medium mr-2">แก้ไข</button>
                                            <form action="{{ route('admin.denominations.destroy', $denom) }}" method="POST" class="inline"
                                                  onsubmit="return confirm('ลบ {{ $denom->display_name }} ?')">
                                                @csrf @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-800 text-xs font-medium">ลบ</button>
                                            </form>
                                        </td>
                                    </template>

                                    {{-- Edit mode --}}
                                    <template x-if="editingId === {{ $denom->id }}">
                                        <td colspan="5" class="px-3 py-2">
                                            <form action="{{ route('admin.denominations.update', $denom) }}" method="POST"
                                                  class="flex items-center gap-2 flex-wrap">
                                                @csrf @method('PUT')
                                                <input type="number" name="seq" value="{{ $denom->seq }}"
                                                       class="w-16 border border-gray-300 rounded px-2 py-1 text-sm" min="0" placeholder="ลำดับ">
                                                <input type="text" name="denom_label" value="{{ $denom->denom_label }}"
                                                       class="w-32 border border-gray-300 rounded px-2 py-1 text-sm font-mono" required placeholder="เช่น 100-50">
                                                <label class="flex items-center gap-1 text-sm">
                                                    <input type="hidden" name="is_active" value="0">
                                                    <input type="checkbox" name="is_active" value="1"
                                                           class="w-4 h-4" {{ $denom->is_active ? 'checked' : '' }}>
                                                    เปิด
                                                </label>
                                                <button type="submit" class="px-3 py-1 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">บันทึก</button>
                                                <button @click="editingId = null" type="button" class="px-3 py-1 bg-gray-200 text-gray-700 text-xs rounded hover:bg-gray-300">ยกเลิก</button>
                                            </form>
                                        </td>
                                    </template>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-gray-400 text-sm mb-4">ยังไม่มี denomination</p>
                @endif

                {{-- Add new denomination --}}
                <form action="{{ route('admin.denominations.store', $currency) }}" method="POST"
                      class="flex items-end gap-2 p-3 bg-blue-50 border border-blue-200 rounded">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Denomination ใหม่</label>
                        <input type="text" name="denom_label"
                               class="w-40 border border-gray-300 rounded px-2 py-1.5 text-sm font-mono"
                               placeholder="เช่น 100-50" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">ลำดับ</label>
                        <input type="number" name="seq" value="{{ ($denoms->max('seq') ?? 0) + 1 }}"
                               class="w-20 border border-gray-300 rounded px-2 py-1.5 text-sm" min="0">
                    </div>
                    <button type="submit"
                            class="px-4 py-1.5 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 font-medium">
                        + เพิ่ม
                    </button>
                </form>
            </div>
        @endif
    </div>
@endsection
