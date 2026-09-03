{{-- ฟอร์มซื้อจากธนาคาร — ธนาคารส่งมาครั้งเดียวได้หลายธนบัตร จึงกรอกเป็นแถวเหมือนหน้ารับซื้อของ staff --}}
@php
    $central = $this->centralCounter;
    $centralStocks = $this->centralStocks;
    $rowAmountF = (float) $rowAmount;
    $rowRateF = (float) $rowRate;
@endphp
<div class="flex-1 min-w-0 bg-white rounded-lg shadow p-6">
    <h3 class="text-lg font-bold mb-4" style="color:#0e513a;">ซื้อเงินตราจากธนาคาร (Buy from Bank)</h3>

    <form wire:submit="save">
        {{-- คู่ค้า + passport — ชุดเดียวกับหน้ารับซื้อของ staff (ไม่มีกล้อง/OCR) --}}
        <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded">
            <div class="flex flex-wrap gap-3 items-start">
                <div class="flex-1 min-w-48 relative" x-data="{ showNameDrop: false }">
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อลูกค้า / คู่ค้า</label>
                    <input type="text" wire:model.blur="custName"
                           x-on:input.debounce.300ms="$wire.searchCustomers($event.target.value); showNameDrop = true"
                           x-on:focus="if($event.target.value.length >= 2) { $wire.searchCustomers($event.target.value); showNameDrop = true }"
                           x-on:click.away="showNameDrop = false"
                           placeholder="ชื่อลูกค้า / ชื่อธนาคาร"
                           autocomplete="off"
                           class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    @if ($showSuggestions && count($customerSuggestions) > 0)
                    <div x-show="showNameDrop" x-cloak
                         style="position:absolute; z-index:50; left:0; right:0; top:100%; margin-top:2px; max-height:240px; overflow-y:auto; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                        @foreach ($customerSuggestions as $sug)
                        <button type="button"
                                wire:click="selectCustomer({{ $sug['id'] }})"
                                x-on:click="showNameDrop = false"
                                class="w-full text-left px-3 py-2 hover:bg-blue-50 border-b border-gray-100 last:border-0 transition-colors"
                                style="display:block;">
                            <div class="font-semibold text-sm text-gray-800">{{ $sug['name'] }}</div>
                            <div class="text-xs text-gray-500">{{ $sug['id_number'] }} &middot; {{ $sug['nationality'] }}</div>
                        </button>
                        @endforeach
                    </div>
                    @endif
                    @error('custName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div class="w-56">
                    <label class="block text-sm font-medium text-gray-700 mb-1">วิธีจ่ายเงิน</label>
                    <select wire:model="settlementMethod" class="w-full border border-gray-300 rounded px-3 py-2 text-sm">
                        <option value="bank_transfer">โอนเข้าบัญชี</option>
                        <option value="cash">เงินสด</option>
                        <option value="cheque">เช็ค</option>
                        <option value="pending">ระบุภายหลัง</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">เลือก "ระบุภายหลัง" ได้ แต่ต้องมาระบุก่อนกดยืนยัน</p>
                </div>
            </div>

            {{-- Passport info (แสดงเสมอ แก้ไขได้) --}}
            <div class="mt-3 p-2 bg-green-50 border border-green-200 rounded text-sm" x-data="{ showDropdown: false }">
                <div class="grid grid-cols-3 gap-2">
                    <div class="relative">
                        <label class="text-gray-500 text-xs">Passport No</label>
                        <input type="text" wire:model.blur="custPassportNo"
                               x-on:input.debounce.300ms="$wire.searchCustomers($event.target.value); showDropdown = true"
                               x-on:focus="if($event.target.value.length >= 2) { $wire.searchCustomers($event.target.value); showDropdown = true }"
                               x-on:click.away="showDropdown = false"
                               placeholder="เลขพาสปอร์ต / ค้นหา"
                               autocomplete="off"
                               class="w-full border border-green-300 rounded px-2 py-1 text-sm font-semibold focus:ring-2 focus:ring-green-400 bg-white">
                        @if ($showSuggestions && count($customerSuggestions) > 0)
                        <div x-show="showDropdown" x-cloak
                             style="position:absolute; z-index:50; left:0; right:0; top:100%; margin-top:2px; max-height:240px; overflow-y:auto; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                            @foreach ($customerSuggestions as $sug)
                            <button type="button"
                                    wire:click="selectCustomer({{ $sug['id'] }})"
                                    x-on:click="showDropdown = false"
                                    class="w-full text-left px-3 py-2 hover:bg-green-50 border-b border-gray-100 last:border-0 transition-colors"
                                    style="display:block;">
                                <div class="font-semibold text-sm text-gray-800">{{ $sug['id_number'] }}</div>
                                <div class="text-xs text-gray-500">{{ $sug['name'] }} &middot; {{ $sug['nationality'] }}</div>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    <div>
                        <label class="text-gray-500 text-xs">Nationality</label>
                        <input type="text" wire:model.blur="custNationality"
                               placeholder="สัญชาติ"
                               class="w-full border border-green-300 rounded px-2 py-1 text-sm font-semibold focus:ring-2 focus:ring-green-400 bg-white">
                    </div>
                    <div>
                        <label class="text-gray-500 text-xs">Expiry</label>
                        <input type="text" wire:model.blur="custExpiry"
                               placeholder="วันหมดอายุ"
                               class="w-full border border-green-300 rounded px-2 py-1 text-sm font-semibold focus:ring-2 focus:ring-green-400 bg-white">
                    </div>
                </div>
                <p class="text-xs text-gray-400 mt-2">กรอกเลขพาสปอร์ตแล้วระบบจะบันทึกเข้าทะเบียนลูกค้าให้ ครั้งหน้าค้นเจอทันที</p>
            </div>
        </div>

        {{-- แถวรายการ --}}
        <div class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-lg">
            <h4 class="text-sm font-bold text-gray-700 mb-3">รายการธนบัตร</h4>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                <div class="md:col-span-4">
                    <label class="block text-xs font-medium text-gray-600 mb-1">ธนบัตร</label>
                    <select wire:model.live="rowDenominationId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- เลือกธนบัตร --</option>
                        @php $prevCur = ''; @endphp
                        @foreach ($this->denominationOptions as $denom)
                            @if ($prevCur !== $denom->currency_code)
                                @if ($prevCur !== '')</optgroup>@endif
                                <optgroup label="{{ $denom->currency_code }} — {{ $denom->currency?->currency_name ?? '' }}">
                                @php $prevCur = $denom->currency_code; @endphp
                            @endif
                            <option value="{{ $denom->id }}">{{ $denom->display_name }}</option>
                        @endforeach
                        @if ($prevCur !== '')</optgroup>@endif
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-xs font-medium text-gray-600 mb-1">จำนวน</label>
                    <x-number-input model="rowAmount" :value="$rowAmount" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="80,000" />
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">เรทซื้อ</label>
                    {{-- เรทตั้งต้นมาจากค่าเฉลี่ยทุกสาขาที่ trader ดูแล (updatedRowDenominationId) แก้ทับได้ --}}
                    <input type="number" step="0.0001" wire:model.blur="rowRate"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="32.6500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-xs font-medium text-gray-600 mb-1">เป็นเงิน (THB)</label>
                    <div class="px-3 py-2 text-sm font-mono font-bold bg-white border border-gray-200 rounded-lg text-right">
                        {{ $rowAmountF > 0 && $rowRateF > 0 ? number_format($rowAmountF * $rowRateF, 2) : '—' }}
                    </div>
                </div>
                <div class="md:col-span-1">
                    <button type="button" wire:click="addRow"
                            class="w-full px-3 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">เพิ่ม</button>
                </div>
            </div>

            @error('rows')
                <div class="mt-3 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm font-medium">{{ $message }}</div>
            @enderror

            @if (! empty($rows))
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-300">
                            <th class="px-3 py-2 text-left font-semibold text-gray-700">ธนบัตร</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700">จำนวน</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700">เรท</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700">THB</th>
                            <th class="px-3 py-2 text-center font-semibold text-gray-700">ลบ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $i => $row)
                        <tr class="border-b border-gray-100 bg-white">
                            <td class="px-3 py-2 font-medium">{{ $row['denomination_label'] }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($row['amount'], 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($row['bank_rate'], 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono font-bold">{{ number_format($row['total_thb'], 2) }}</td>
                            <td class="px-3 py-2 text-center">
                                <button type="button" wire:click="removeRow({{ $i }})"
                                        class="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 rounded hover:bg-red-100">✕</button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-300">
                            <td colspan="3" class="px-3 py-2 text-right font-semibold text-gray-700">รวมที่ต้องจ่าย (THB)</td>
                            <td class="px-3 py-2 text-right font-mono font-bold text-base" style="color:#0e513a;">{{ number_format($this->rowsTotalThb, 2) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endif
        </div>

        {{-- ซื้อเข้ากองกลางที่เดียว — กระจายออกสาขาทีหลังผ่านเมนู ยืม/คืน/โอน --}}
        <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
            <h4 class="text-sm font-bold text-green-800 mb-3">รับเข้ากองกลาง</h4>

            @if (! $central)
                <div class="text-sm text-red-700">
                    ไม่พบเคาน์เตอร์กองกลาง — บัญชีนี้ยังไม่ได้ผูกกับสำนักงานใหญ่ กรุณาติดต่อผู้ดูแลระบบ
                </div>
            @else
                <div class="text-sm mb-3">
                    <span class="text-gray-500">ปลายทาง</span>
                    <span class="font-semibold ml-2">{{ $central->counter_name }}</span>
                    <span class="text-xs text-gray-500 ml-2">{{ $central->branch->branch_name ?? '' }}</span>
                </div>

                @if (empty($rows))
                    <p class="text-sm text-gray-500">เพิ่มรายการธนบัตรก่อน เพื่อดูผลกระทบต่อ Avg Cost ของกองกลาง</p>
                @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-green-200">
                                <th class="px-3 py-2 text-left font-semibold text-green-900">ธนบัตร</th>
                                <th class="px-3 py-2 text-right font-semibold text-green-900">Stock ปัจจุบัน</th>
                                <th class="px-3 py-2 text-right font-semibold text-green-900">Avg Cost เดิม</th>
                                <th class="px-3 py-2 text-right font-semibold text-green-900">รับเข้า</th>
                                <th class="px-3 py-2 text-right font-semibold text-green-900">Avg Cost ใหม่</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                            @php
                                $stock = $centralStocks->get($row['denomination_id']);
                                $qty = (float) ($stock->quantity ?? 0);
                                $avg = (float) ($stock->avg_cost ?? 0);
                            @endphp
                            <tr class="border-b border-green-100">
                                <td class="px-3 py-2 font-medium">{{ $row['denomination_label'] }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format($qty, 2) }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ $avg > 0 ? number_format($avg, 4) : '—' }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format($row['amount'], 2) }}</td>
                                <td class="px-3 py-2 text-right font-mono font-bold text-green-700">
                                    {{ number_format($this->projectedAvgCost($qty, $avg, (float) $row['amount'], (float) $row['bank_rate']), 4) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="text-xs text-gray-400 mt-2">ซื้อเข้าไม่มีกำไร/ขาดทุน — เงินที่จ่ายคือต้นทุนของสต็อกที่รับเข้ามา</p>
                @endif
            @endif
        </div>

        <div class="flex items-center gap-3">
            <input type="text" wire:model.blur="notes" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="หมายเหตุ (ถ้ามี)">
            <button type="button" wire:click="$toggle('showForm')" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">ยกเลิก</button>
            <button type="submit" wire:confirm="สร้างรายการซื้อจากธนาคาร? สต็อกจะเข้าเมื่อกดยืนยันรับของ"
                    class="px-6 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">
                สร้างรายการ (รอรับของ)
            </button>
        </div>
    </form>
</div>
