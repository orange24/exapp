<div class="p-4">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div>
            <h2 class="text-xl font-bold text-gray-800">ตั้งราคาแลกเปลี่ยน</h2>
            <p class="text-sm text-gray-500">เคาน์เตอร์: <span class="font-semibold">{{ $counterName }}</span> ({{ $counterCode }})</p>
        </div>
        <div class="text-sm text-gray-500">วันที่: {{ now()->format('d/m/Y') }}</div>
    </div>

    @if ($saved)
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 3000)"
             class="mb-4 p-3 bg-green-100 border border-green-400 text-green-800 rounded">
            บันทึกอัตราแลกเปลี่ยนเรียบร้อยแล้ว
        </div>
    @endif

    {{-- ผลของปุ่ม คัดลอกราคา / ดึงราคาตั้งต้น --}}
    @if ($notice)
        @php
            $noticeClass = match ($noticeType) {
                'success' => 'bg-green-50 border-green-300 text-green-800',
                'warn'    => 'bg-amber-50 border-amber-300 text-amber-800',
                default   => 'bg-blue-50 border-blue-300 text-blue-800',
            };
        @endphp
        <div class="mb-4 p-3 border rounded text-sm {{ $noticeClass }}">
            {{ $notice }}
        </div>
    @endif

    {{-- Copy from / Auto Set --}}
    <div class="flex flex-wrap gap-3 mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
        <div class="flex items-center gap-2">
            <label class="text-sm font-medium text-gray-700">คัดลอกจากเคาน์เตอร์:</label>
            <select wire:model="copyFromCode" class="text-sm border border-gray-300 rounded px-2 py-1">
                <option value="">-- เลือกเคาน์เตอร์ --</option>
                @foreach ($this->allCounters as $counter)
                    <option value="{{ $counter->counter_code }}">{{ $counter->counter_name }}</option>
                @endforeach
            </select>
            <button wire:click="copyRates" class="px-3 py-1 bg-blue-600 text-white text-sm rounded hover:bg-blue-700">
                คัดลอกราคา
            </button>
        </div>
        <button wire:click="autoSetRates" class="px-3 py-1 bg-orange-500 text-white text-sm rounded hover:bg-orange-600">
            ดึงราคาตั้งต้น
        </button>
    </div>

    {{-- Rate Table --}}
    <div class="overflow-x-auto">
        <table class="w-full border-collapse text-sm">
            <thead>
                <tr class="bg-[#0e513a] text-white">
                    <th class="px-3 py-2 text-left w-8">#</th>
                    <th class="px-3 py-2 text-left w-16">ธง</th>
                    <th class="px-3 py-2 text-left">ประเทศ</th>
                    <th class="px-3 py-2 text-left">สกุลเงิน</th>
                    <th class="px-3 py-2 text-right w-36">อัตราซื้อ (Buy)</th>
                    <th class="px-3 py-2 text-right w-36">อัตราขาย (Sell)</th>
                    <th class="px-3 py-2 text-right w-36">ส่วนลด Booth</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rates as $denomId => $row)
                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="px-3 py-2 text-gray-400 text-xs">{{ $loop->iteration }}</td>
                        <td class="px-2 py-2">
                            <img src="{{ asset('images/flags/' . $row['flag']) }}"
                                 alt="{{ $row['currency_code'] }}"
                                 class="w-8 h-auto rounded shadow-sm"
                                 onerror="this.style.display='none'">
                        </td>
                        <td class="px-3 py-2 text-gray-600 text-xs">{{ $row['country'] }}</td>
                        <td class="px-3 py-2">
                            <span class="font-bold text-gray-800">{{ $row['display_name'] }}</span>
                        </td>
                        <td class="px-3 py-2">
                            <input type="number"
                                   wire:model="rates.{{ $denomId }}.buy"
                                   value="{{ $row['buy'] }}"
                                   step="0.000001" min="0"
                                   class="w-full text-right border border-gray-300 rounded px-2 py-1 text-green-700 font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <input type="number"
                                   wire:model="rates.{{ $denomId }}.sell"
                                   value="{{ $row['sell'] }}"
                                   step="0.000001" min="0"
                                   class="w-full text-right border border-gray-300 rounded px-2 py-1 text-red-700 font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </td>
                        <td class="px-3 py-2">
                            <input type="number"
                                   wire:model="rates.{{ $denomId }}.discount"
                                   value="{{ $row['discount'] }}"
                                   step="0.000001" min="0"
                                   class="w-full text-right border border-gray-300 rounded px-2 py-1 text-gray-600 font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                            />
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- Save Button --}}
    <div class="mt-4 flex justify-end">
        <button wire:click="save" wire:loading.attr="disabled"
                class="px-6 py-2 bg-[#0e513a] text-white rounded font-medium hover:bg-[#0a3d2d] disabled:opacity-60">
            <span wire:loading.remove>บันทึกอัตราแลกเปลี่ยน</span>
            <span wire:loading>กำลังบันทึก...</span>
        </button>
    </div>
</div>
