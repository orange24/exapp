<div>
    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4 text-red-800 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Controls --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เคาน์เตอร์</label>
                <select wire:model.live="counterId"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">-- เลือกเคาน์เตอร์ --</option>
                    @foreach ($this->counters as $c)
                        <option value="{{ $c->id }}">{{ $c->counter_name }} ({{ $c->branch->branch_name ?? '' }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ทำการ</label>
                <input type="date" wire:model.live="date"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>

            @if ($counterId)
                @php $wd = $this->workingDay; @endphp
                @if (!$wd)
                    {{-- Not opened yet --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ยอดบาทยกมา</label>
                        <div class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm font-mono font-semibold text-gray-800"
                             style="width: 180px;">
                            {{ number_format($this->thbSummary['opening'], 2) }}
                        </div>
                        <p class="mt-1 text-xs text-gray-500">คำนวณจากระบบ</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">เติมเงินทุนเพิ่ม</label>
                        <x-number-input model="topupThbCash" :value="$topupThbCash"
                               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                               placeholder="0.00"
                               style="width: 180px;" />
                        <p class="mt-1 text-xs text-gray-500">เว้นว่างได้ถ้าเงินยกมาพอแล้ว</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ</label>
                        <input type="text" wire:model.blur="note"
                               class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                               placeholder="หมายเหตุ (ถ้ามี)">
                    </div>
                    <button wire:click="openDay" wire:confirm="ยืนยันเปิดวันทำการ?"
                            class="px-6 py-2 text-sm font-semibold text-white rounded-lg bg-green-600 hover:bg-green-700">
                        เปิดวันทำการ (Open Day)
                    </button>
                @elseif ($wd->isOpen())
                    {{-- Opened, can close --}}
                    <div class="flex items-center gap-3">
                        <div class="inline-flex flex-col gap-1">
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                เปิดอยู่ — เปิดเมื่อ {{ $wd->opened_at?->format('H:i') }} โดย {{ $wd->openedByUser?->name ?? '-' }}
                            </span>
                            <span class="text-xs text-gray-600 px-3">
                                บาทยกมา: <span class="font-mono font-semibold">{{ number_format($this->thbSummary['opening'], 2) }}</span>
                                → คงเหลือ: <span class="font-mono font-semibold">{{ number_format($this->thbSummary['closing'], 2) }}</span> บาท
                            </span>
                        </div>
                        <button wire:click="prepareClosing"
                                class="px-6 py-2 text-sm font-semibold text-white rounded-lg bg-red-600 hover:bg-red-700">
                            ปิดวันทำการ (Close Day)
                        </button>
                    </div>
                @else
                    {{-- Already closed --}}
                    <div class="flex items-center gap-3">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
                            ปิดแล้ว — ปิดเมื่อ {{ $wd->closed_at?->format('H:i') }} โดย {{ $wd->closedByUser?->name ?? '-' }}
                        </span>
                        @if ($wd->closing_status === 'pending')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                รออนุมัติ
                            </span>
                        @elseif ($wd->closing_status === 'approved')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                อนุมัติแล้ว
                            </span>
                        @elseif ($wd->closing_status === 'rejected')
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                ปฏิเสธ
                            </span>
                        @endif
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- เงินบาทในลิ้นชัก --}}
    @if ($counterId)
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-4 py-3 border-b" style="background:#0e513a;">
            <h3 class="text-white font-semibold">เงินบาทในลิ้นชัก — {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</h3>
        </div>

        <div class="grid grid-cols-2 gap-4 p-4 md:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-3">
                <div class="text-xs text-gray-600">ยอดยกมา</div>
                <div class="font-mono text-lg font-semibold text-gray-800">{{ number_format($this->thbSummary['opening'], 2) }}</div>
            </div>
            <div class="rounded-lg bg-green-50 p-3">
                <div class="text-xs text-gray-600">รับเข้า</div>
                <div class="font-mono text-lg font-semibold text-green-700">{{ number_format($this->thbSummary['in'], 2) }}</div>
            </div>
            <div class="rounded-lg bg-red-50 p-3">
                <div class="text-xs text-gray-600">จ่ายออก</div>
                <div class="font-mono text-lg font-semibold text-red-700">{{ number_format($this->thbSummary['out'], 2) }}</div>
            </div>
            <div class="rounded-lg p-3 {{ $this->thbSummary['closing'] < 0 ? 'bg-amber-50' : 'bg-blue-50' }}">
                <div class="text-xs text-gray-600">คงเหลือ</div>
                <div class="font-mono text-lg font-bold {{ $this->thbSummary['closing'] < 0 ? 'text-amber-700' : 'text-blue-800' }}">
                    {{ number_format($this->thbSummary['closing'], 2) }}
                </div>
            </div>
        </div>

        <div class="border-t bg-gray-50 px-4 py-4">
            <div class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-gray-700">จำนวนเงิน (บาท)</label>
                    <x-number-input model="transferAmount" :value="$transferAmount"
                           class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                           placeholder="0.00"
                           style="width: 180px;" />
                </div>
                <div class="flex-1 min-w-[200px]">
                    <label class="mb-1 block text-sm font-medium text-gray-700">หมายเหตุ</label>
                    <input type="text" wire:model.blur="transferNote"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                           placeholder="หมายเหตุ (ถ้ามี)">
                </div>
                <button wire:click="topUpCash" wire:confirm="ยืนยันเติมเงินทุนเข้าลิ้นชัก?"
                        class="px-5 py-2 text-sm font-semibold text-white rounded-lg bg-green-600 hover:bg-green-700">
                    เติมเงินทุนเข้า
                </button>
                <button wire:click="withdrawCash" wire:confirm="ยืนยันนำเงินบาทออกจากลิ้นชัก?"
                        class="px-5 py-2 text-sm font-semibold text-white rounded-lg bg-orange-600 hover:bg-orange-700">
                    นำเงินออก
                </button>
            </div>
            <p class="mt-2 text-xs text-gray-500">
                เงินทุนมาจากบัญชีธนาคาร/เจ้าของ ซึ่งอยู่นอกบัญชีเงินสดเคาน์เตอร์ —
                บันทึกเฉพาะฝั่งลิ้นชักนี้ ไม่ตัดยอดที่ไหนอีก
            </p>
        </div>
    </div>
    @endif

    {{-- Closing Form Modal --}}
    @if ($showClosingForm)
    <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50" style="padding: 20px;">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b" style="background:#0e513a;">
                <h3 class="text-white font-semibold text-lg">ปิดวันทำการ - กรอกยอดเงินจริงที่ส่งมอบ</h3>
            </div>

            <div class="p-6">
                <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm text-blue-800">
                    <strong>📋 คำแนะนำ:</strong><br>
                    • นับเงินต่างประเทศแต่ละธนบัตรและกรอกจำนวนจริงที่ส่งมอบ<br>
                    • <strong class="text-red-700">กรอกเฉพาะสกุลเงินที่มีจริง</strong> (ไม่มี = ปล่อยเป็น 0)<br>
                    • ไม่มียอดเลยก็ปิดวันด้วยยอด 0 ได้
                </div>

                {{-- เงินบาท — แยกจากตารางเงินตราต่างประเทศเพราะไม่มี denomination --}}
                @php
                    $thbExpected = $this->thbSummary['closing'];
                    $thbVariance = $closingThbActual - $thbExpected;
                @endphp
                <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <h4 class="mb-3 font-semibold text-emerald-900">เงินบาทในลิ้นชัก</h4>
                    <div class="flex flex-wrap items-end gap-6">
                        <div>
                            <div class="mb-1 text-xs text-gray-600">ยอดตามระบบ</div>
                            <div class="font-mono text-lg font-semibold text-blue-700">
                                {{ number_format($thbExpected, 2) }}
                            </div>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-gray-600">ยอดที่นับได้จริง</label>
                            <x-number-input model="closingThbActual" :value="$closingThbActual"
                                   class="border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-green-500"
                                   style="width: 180px;" />
                        </div>
                        <div>
                            <div class="mb-1 text-xs text-gray-600">ผลต่าง</div>
                            <div class="font-mono text-lg font-semibold {{ abs($thbVariance) < 0.005 ? 'text-gray-400' : ($thbVariance > 0 ? 'text-green-700' : 'text-red-700') }}">
                                {{ $thbVariance > 0 ? '+' : '' }}{{ number_format($thbVariance, 2) }}
                            </div>
                        </div>
                    </div>
                    <p class="mt-3 text-xs text-emerald-800">
                        ผลต่างจะถูกปรับเข้าระบบเมื่อ Admin อนุมัติ แล้วยอดที่นับได้จริงจะกลายเป็นยอดยกมาของวันถัดไป
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="px-4 py-3 text-left font-semibold">สกุลเงิน</th>
                                <th class="px-4 py-3 text-right font-semibold text-blue-700">ยอดในระบบ</th>
                                <th class="px-4 py-3 text-right font-semibold text-green-700">ยอดจริงที่ส่งมอบ</th>
                                <th class="px-4 py-3 text-right font-semibold text-red-700">ส่วนต่าง</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($closingItems as $index => $item)
                                <tr class="border-b">
                                    <td class="px-4 py-3 font-medium">
                                        {{ $item['currency_code'] }} {{ $item['denom_label'] }}
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono text-blue-700">
                                        {{ number_format($item['expected'], 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <x-number-input model="closingItems.{{ $index }}.actual" :value="$item['actual'] ?? null"
                                               on-commit="$wire.updateClosingItem({{ $index }}, cleaned)"
                                               class="border border-gray-300 rounded px-3 py-1 w-32 focus:ring-2 focus:ring-green-500" />
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold {{ $item['variance'] != 0 ? 'text-red-700' : 'text-gray-400' }}">
                                        {{ $item['variance'] > 0 ? '+' : '' }}{{ number_format($item['variance'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="px-6 py-4 bg-gray-50 border-t">
                <div class="flex justify-end gap-3">
                    <button wire:click="showClosingForm = false" type="button"
                            class="px-6 py-2 text-sm font-semibold text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                        ยกเลิก
                    </button>
                    <button wire:click="saveClosingAmounts"
                            wire:confirm="ยืนยันบันทึกยอดปิด? ข้อมูลจะถูกส่งให้ Admin อนุมัติ"
                            class="px-6 py-2 text-sm font-semibold text-white rounded-lg bg-green-600 hover:bg-green-700 cursor-pointer">
                        บันทึกยอดปิด
                    </button>
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Inventory Snapshot for the day --}}
    @if ($counterId && $this->inventorySnapshot->isNotEmpty())
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-4 py-3 border-b" style="background:#0e513a;">
            <h3 class="text-white font-semibold">สรุปสต็อกประจำวัน {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">สกุลเงิน</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-700">ธนบัตร</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">ยอดยกมา</th>
                        <th class="px-4 py-3 text-right font-semibold text-green-700">ซื้อเข้า</th>
                        <th class="px-4 py-3 text-right font-semibold text-red-700">ขายออก</th>
                        <th class="px-4 py-3 text-right font-semibold text-blue-700">โอนเข้า</th>
                        <th class="px-4 py-3 text-right font-semibold text-pink-700">โอนออก</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">ยอดปิด</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">ต้นทุนเฉลี่ย</th>
                        <th class="px-4 py-3 text-right font-semibold text-gray-700">มูลค่า (THB)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->inventorySnapshot as $idx => $inv)
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                            <td class="px-4 py-2 font-medium">{{ $inv->currency_code }}</td>
                            <td class="px-4 py-2">{{ $inv->denomination?->denom_label ?? '-' }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($inv->opening_balance, 2) }}</td>
                            <td class="px-4 py-2 text-right text-green-600">{{ number_format($inv->buy_total, 2) }}</td>
                            <td class="px-4 py-2 text-right text-red-600">{{ number_format($inv->sell_total, 2) }}</td>
                            <td class="px-4 py-2 text-right text-blue-600">{{ number_format($inv->transfer_in, 2) }}</td>
                            <td class="px-4 py-2 text-right text-pink-600">{{ number_format($inv->transfer_out, 2) }}</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ number_format($inv->closing_balance, 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ $inv->avg_cost > 0 ? number_format($inv->avg_cost, 4) : '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($inv->total_value, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Admin Approval Section --}}
    @if ($counterId && $this->workingDay && $this->workingDay->isPendingApproval() && auth()->user()->isAdmin())
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-4 py-3 border-b bg-yellow-50 border-yellow-200">
            <h3 class="font-semibold text-yellow-800">รออนุมัติยอดปิดวัน {{ $this->workingDay->work_date?->format('d/m/Y') }}</h3>
        </div>
        <div class="p-6">
            <div class="mb-4 text-sm text-gray-600">
                <p><strong>ปิดโดย:</strong> {{ $this->workingDay->closedByUser?->name ?? '-' }} เมื่อ {{ $this->workingDay->closed_at?->format('d/m/Y H:i') }}</p>
            </div>

            <div class="overflow-x-auto mb-6">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-3 text-left font-semibold">สกุลเงิน</th>
                            <th class="px-4 py-3 text-right font-semibold text-blue-700">ยอดในระบบ</th>
                            <th class="px-4 py-3 text-right font-semibold text-green-700">ยอดจริงที่ส่งมอบ</th>
                            <th class="px-4 py-3 text-right font-semibold text-red-700">ส่วนต่าง</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->workingDay->closing_items ?? [] as $item)
                            <tr class="border-b {{ abs($item['variance'] ?? 0) > 0 ? 'bg-red-50' : '' }}">
                                <td class="px-4 py-3 font-medium">{{ $item['currency_code'] }} {{ $item['denom_label'] }}</td>
                                <td class="px-4 py-3 text-right font-mono text-blue-700">{{ number_format($item['expected'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-green-700 font-semibold">{{ number_format($item['actual'], 2) }}</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold {{ $item['variance'] != 0 ? 'text-red-700' : 'text-gray-400' }}">
                                    {{ $item['variance'] > 0 ? '+' : '' }}{{ number_format($item['variance'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex gap-3 justify-end" x-data="{ rejectReason: '' }">
                <button @click="if(confirm('ยืนยันอนุมัติยอดปิด? ระบบจะโอนสต็อกไปส่วนกลาง')) { $wire.approveClosing({{ $this->workingDay->id }}) }"
                        class="px-6 py-2 text-sm font-semibold text-white rounded-lg bg-green-600 hover:bg-green-700">
                    ✓ อนุมัติ
                </button>
                <button @click="rejectReason = prompt('ระบุเหตุผลที่ปฏิเสธ:'); if(rejectReason) { $wire.rejectClosing({{ $this->workingDay->id }}, rejectReason) }"
                        class="px-6 py-2 text-sm font-semibold text-white rounded-lg bg-red-600 hover:bg-red-700">
                    ✕ ปฏิเสธ
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Recent Working Days --}}
    @if ($counterId)
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50">
            <h3 class="font-semibold text-gray-700">ประวัติเปิด/ปิดวัน (15 วันล่าสุด)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr style="background:#0e513a;" class="text-white">
                        <th class="px-4 py-3 text-left font-semibold">วันที่</th>
                        <th class="px-4 py-3 text-center font-semibold">สถานะ</th>
                        <th class="px-4 py-3 text-center font-semibold">อนุมัติ</th>
                        <th class="px-4 py-3 text-right font-semibold">บาทยกมา</th>
                        <th class="px-4 py-3 text-right font-semibold">ปิดตามระบบ</th>
                        <th class="px-4 py-3 text-right font-semibold">นับได้จริง</th>
                        <th class="px-4 py-3 text-right font-semibold">ผลต่าง</th>
                        <th class="px-4 py-3 text-left font-semibold">เปิดเมื่อ</th>
                        <th class="px-4 py-3 text-left font-semibold">เปิดโดย</th>
                        <th class="px-4 py-3 text-left font-semibold">ปิดเมื่อ</th>
                        <th class="px-4 py-3 text-left font-semibold">ปิดโดย</th>
                        <th class="px-4 py-3 text-left font-semibold">หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->recentDays as $idx => $wd)
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                            <td class="px-4 py-2 font-medium">{{ $wd->work_date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-center">
                                @if ($wd->status === 'open')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">เปิดอยู่</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">ปิดแล้ว</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                @if ($wd->closing_status === 'pending')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">รออนุมัติ</span>
                                @elseif ($wd->closing_status === 'approved')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">อนุมัติ</span>
                                @elseif ($wd->closing_status === 'rejected')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">ปฏิเสธ</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right font-mono text-gray-700">
                                {{ number_format($wd->opening_thb_cash ?? 0, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right font-mono text-blue-700">
                                {{ $wd->closing_thb_expected === null ? '—' : number_format($wd->closing_thb_expected, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right font-mono font-semibold text-green-700">
                                {{ $wd->closing_thb_actual === null ? '—' : number_format($wd->closing_thb_actual, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right font-mono font-semibold {{ $wd->closing_thb_variance === null ? 'text-gray-400' : (abs($wd->closing_thb_variance) < 0.005 ? 'text-gray-400' : ($wd->closing_thb_variance > 0 ? 'text-green-700' : 'text-red-700')) }}">
                                @if ($wd->closing_thb_variance === null)
                                    —
                                @else
                                    {{ $wd->closing_thb_variance > 0 ? '+' : '' }}{{ number_format($wd->closing_thb_variance, 2) }}
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $wd->opened_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $wd->openedByUser?->name ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $wd->closed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $wd->closedByUser?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $wd->note ?? '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-4 py-8 text-center text-gray-400">ยังไม่มีประวัติ</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
