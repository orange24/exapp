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
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            เปิดอยู่ — เปิดเมื่อ {{ $wd->opened_at?->format('H:i') }} โดย {{ $wd->openedByUser?->name ?? '-' }}
                        </span>
                        <button wire:click="closeDay" wire:confirm="ยืนยันปิดวันทำการ? ระบบจะบันทึกยอดปิด"
                                class="px-6 py-2 text-sm font-semibold text-white rounded-lg bg-red-600 hover:bg-red-700">
                            ปิดวันทำการ (Close Day)
                        </button>
                    </div>
                @else
                    {{-- Already closed --}}
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-600">
                        ปิดแล้ว — ปิดเมื่อ {{ $wd->closed_at?->format('H:i') }} โดย {{ $wd->closedByUser?->name ?? '-' }}
                    </span>
                @endif
            @endif
        </div>
    </div>

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
                            <td class="px-4 py-2">{{ $wd->opened_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $wd->openedByUser?->name ?? '-' }}</td>
                            <td class="px-4 py-2">{{ $wd->closed_at?->format('d/m/Y H:i') ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-600">{{ $wd->closedByUser?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $wd->note ?? '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-400">ยังไม่มีประวัติ</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
