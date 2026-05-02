@extends('layouts.app')

@section('title', $isEdit ? 'แก้ไขกลุ่มตั้งค่า (Edit Setting Group)' : 'เพิ่มกลุ่มตั้งค่า (Add Setting Group)')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.rate-settings.index') }}" class="text-sm text-blue-600 hover:text-blue-800">&larr; กลับ (Back)</a>
        <h2 class="text-lg font-semibold text-gray-800 mt-1">
            {{ $isEdit ? 'แก้ไขกลุ่มตั้งค่า (Edit Setting Group)' : 'เพิ่มกลุ่มตั้งค่า (Add Setting Group)' }}
        </h2>
    </div>

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Section 1: Copy from another group (edit mode only) --}}
    @if ($isEdit && $allSettings->isNotEmpty())
        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
            <h3 class="text-sm font-semibold text-yellow-800 mb-2">คัดลอกค่าจากกลุ่มอื่น (Copy from another group)</h3>
            <form action="{{ route('admin.rate-settings.copy-from', $rateSetting) }}" method="POST" class="flex items-end gap-3">
                @csrf
                <div class="flex-1">
                    <select name="source_setting_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">-- เลือกกลุ่มต้นทาง --</option>
                        @foreach ($allSettings as $s)
                            <option value="{{ $s->id }}">{{ $s->group_name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit"
                        class="bg-yellow-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-yellow-700 transition-colors">
                    คัดลอก
                </button>
            </form>
        </div>
    @endif

    {{-- Main form --}}
    <form action="{{ $isEdit ? route('admin.rate-settings.update', $rateSetting) : route('admin.rate-settings.store') }}"
          method="POST" class="space-y-5">
        @csrf
        @if ($isEdit) @method('PUT') @endif

        {{-- Section 2: Basic info --}}
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">ข้อมูลพื้นฐาน (Basic Info)</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <div>
                    <label for="group_name" class="block text-sm font-medium text-gray-700 mb-1">
                        ชื่อกลุ่ม (Group Name) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="group_name" name="group_name"
                           value="{{ old('group_name', $rateSetting->group_name) }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           maxlength="100" required>
                    @error('group_name') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="main_counter_id" class="block text-sm font-medium text-gray-700 mb-1">
                        เคาน์เตอร์หลัก (Main Counter) <span class="text-red-500">*</span>
                    </label>
                    <select id="main_counter_id" name="main_counter_id"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">-- เลือกเคาน์เตอร์หลัก --</option>
                        @foreach ($counters as $counter)
                            <option value="{{ $counter->id }}"
                                    {{ old('main_counter_id', $rateSetting->main_counter_id) == $counter->id ? 'selected' : '' }}>
                                {{ $counter->counter_name }} ({{ $counter->branch?->branch_name ?? '-' }})
                            </option>
                        @endforeach
                    </select>
                    @error('main_counter_id') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="flex items-end">
                    <div class="flex items-center gap-3 pb-2">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" id="is_active" name="is_active" value="1"
                               class="w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                               {{ old('is_active', $rateSetting->is_active ?? true) ? 'checked' : '' }}>
                        <label for="is_active" class="text-sm font-medium text-gray-700">เปิดใช้งาน (Active)</label>
                    </div>
                </div>
            </div>
        </div>

        {{-- Section 3: Target counters --}}
        <div class="bg-white shadow rounded-lg p-6" x-data="counterSelector()">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700">เคาน์เตอร์ที่ใช้ตั้งค่านี้ (Target Counters)</h3>
                <div class="flex gap-2">
                    <button type="button" @click="selectAll()" class="text-xs text-blue-600 hover:text-blue-800 font-medium">เลือกทั้งหมด</button>
                    <span class="text-gray-300">|</span>
                    <button type="button" @click="deselectAll()" class="text-xs text-red-600 hover:text-red-800 font-medium">ยกเลิกทั้งหมด</button>
                </div>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                @php
                    $selectedCounterIds = old('counter_ids', $rateSetting->counters->pluck('id')->toArray());
                @endphp
                @foreach ($counters as $counter)
                    <label class="flex items-center gap-2 p-2 rounded border border-gray-200 hover:bg-gray-50 cursor-pointer text-sm">
                        <input type="checkbox" name="counter_ids[]" value="{{ $counter->id }}"
                               class="counter-checkbox w-4 h-4 text-blue-600 rounded border-gray-300 focus:ring-blue-500"
                               {{ in_array($counter->id, $selectedCounterIds) ? 'checked' : '' }}>
                        <span>{{ $counter->counter_name }}</span>
                        <span class="text-xs text-gray-400">({{ $counter->branch?->branch_name ?? '-' }})</span>
                    </label>
                @endforeach
            </div>
        </div>

        {{-- Section 4: Rate adjustment table --}}
        <div class="bg-white shadow rounded-lg p-6">
            <h3 class="text-sm font-semibold text-gray-700 mb-4">ค่าปรับราคาต่อสกุลเงิน (Rate Adjustments per Denomination)</h3>

            @if ($denominations->isEmpty())
                <p class="text-sm text-gray-500">ยังไม่มีข้อมูล denomination — กรุณาเพิ่มในสกุลเงินก่อน</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 border-b">
                                <th class="text-left px-3 py-2 font-medium text-gray-600 w-12">ธง</th>
                                <th class="text-left px-3 py-2 font-medium text-gray-600">ประเทศ</th>
                                <th class="text-left px-3 py-2 font-medium text-gray-600">สกุลเงิน</th>
                                <th class="text-center px-3 py-2 font-medium text-gray-600">ปรับราคาซื้อ (Buy Adj.)</th>
                                <th class="text-center px-3 py-2 font-medium text-gray-600">ปรับราคาขาย (Sell Adj.)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @php
                                $existingAdj = $rateSetting->adjustments?->keyBy('denomination_id') ?? collect();
                            @endphp
                            @foreach ($denominations as $denom)
                                @php
                                    $adj = $existingAdj->get($denom->id);
                                    $buyVal  = old("adjustments.{$denom->id}.cal_rate_buy", $adj?->cal_rate_buy ?? 0);
                                    $sellVal = old("adjustments.{$denom->id}.cal_rate_sell", $adj?->cal_rate_sell ?? 0);
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-3 py-2">
                                        @if ($denom->currency && $denom->currency->country_flag)
                                            <img src="{{ asset('images/flags/' . $denom->currency->country_flag) }}"
                                                 alt="{{ $denom->currency->currency_code }}"
                                                 class="w-6 h-4 object-cover rounded shadow-sm">
                                        @else
                                            <span class="text-gray-300">--</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ $denom->currency->country ?? '-' }}</td>
                                    <td class="px-3 py-2 font-medium text-gray-800">
                                        {{ $denom->display_name }}
                                        <span class="text-xs text-gray-400">({{ $denom->currency->currency_code ?? '' }})</span>
                                    </td>
                                    <td class="px-3 py-2 text-center" x-data>
                                        <input type="number"
                                               name="adjustments[{{ $denom->id }}][cal_rate_buy]"
                                               value="{{ $buyVal }}"
                                               step="0.000001"
                                               x-bind:class="$el.value > 0 ? 'text-green-700' : ($el.value < 0 ? 'text-red-700' : '')"
                                               x-on:input="$el.classList.toggle('text-green-700', $el.value > 0); $el.classList.toggle('text-red-700', $el.value < 0);"
                                               class="w-32 border border-gray-300 rounded px-2 py-1 text-sm text-center font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               style="{{ $buyVal > 0 ? 'color: #15803d;' : ($buyVal < 0 ? 'color: #b91c1c;' : '') }}">
                                    </td>
                                    <td class="px-3 py-2 text-center" x-data>
                                        <input type="number"
                                               name="adjustments[{{ $denom->id }}][cal_rate_sell]"
                                               value="{{ $sellVal }}"
                                               step="0.000001"
                                               x-bind:class="$el.value > 0 ? 'text-green-700' : ($el.value < 0 ? 'text-red-700' : '')"
                                               x-on:input="$el.classList.toggle('text-green-700', $el.value > 0); $el.classList.toggle('text-red-700', $el.value < 0);"
                                               class="w-32 border border-gray-300 rounded px-2 py-1 text-sm text-center font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                               style="{{ $sellVal > 0 ? 'color: #15803d;' : ($sellVal < 0 ? 'color: #b91c1c;' : '') }}">
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- Save button --}}
        <div class="flex items-center gap-3">
            <button type="submit"
                    class="bg-[#0e513a] text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
                {{ $isEdit ? 'บันทึก (Save)' : 'สร้างกลุ่ม (Create Group)' }}
            </button>
            <a href="{{ route('admin.rate-settings.index') }}"
               class="text-gray-600 hover:text-gray-800 text-sm font-medium">ยกเลิก (Cancel)</a>
        </div>
    </form>

    <script>
        function counterSelector() {
            return {
                selectAll() {
                    document.querySelectorAll('.counter-checkbox').forEach(cb => cb.checked = true);
                },
                deselectAll() {
                    document.querySelectorAll('.counter-checkbox').forEach(cb => cb.checked = false);
                }
            }
        }
    </script>
@endsection
