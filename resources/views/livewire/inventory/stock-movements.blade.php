<div>
    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เคาน์เตอร์</label>
                <select wire:model.live="counterId"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">ทั้งหมด</option>
                    @foreach ($this->counters as $c)
                        <option value="{{ $c->id }}">{{ $c->counter_name }} ({{ $c->branch->branch_name ?? '' }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">สกุลเงิน</label>
                <select wire:model.live="filterCurrency"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">ทั้งหมด</option>
                    @foreach ($this->currencies as $cur)
                        <option value="{{ $cur->currency_code }}">{{ $cur->currency_code }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ประเภท</label>
                <select wire:model.live="filterType"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">ทั้งหมด</option>
                    <option value="buy">ซื้อเข้า (Buy)</option>
                    <option value="sell">ขายออก (Sell)</option>
                    <option value="borrow">ยืม (Borrow)</option>
                    <option value="return">คืน (Return)</option>
                    <option value="adjustment">ปรับปรุง (Adjustment)</option>
                    <option value="disbursement">เบิกจ่าย (Disbursement)</option>
                    <option value="transfer_in">โอนเข้า (Transfer In)</option>
                    <option value="transfer_out">โอนออก (Transfer Out)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ตั้งแต่</label>
                <input type="date" wire:model.live="dateFrom"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ถึง</label>
                <input type="date" wire:model.live="dateTo"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
    </div>

    {{-- Movements table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr style="background:#0e513a;" class="text-white">
                        <th class="px-4 py-3 text-left font-semibold">วันที่/เวลา</th>
                        <th class="px-4 py-3 text-left font-semibold">สกุลเงิน</th>
                        <th class="px-4 py-3 text-left font-semibold">ธนบัตร</th>
                        <th class="px-4 py-3 text-center font-semibold">ประเภท</th>
                        <th class="px-4 py-3 text-right font-semibold">จำนวน</th>
                        <th class="px-4 py-3 text-right font-semibold">อัตรา</th>
                        <th class="px-4 py-3 text-left font-semibold">อ้างอิง</th>
                        <th class="px-4 py-3 text-left font-semibold">ผู้ทำรายการ</th>
                        <th class="px-4 py-3 text-left font-semibold">หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($movements as $idx => $mv)
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                            <td class="px-4 py-2 whitespace-nowrap">{{ $mv->moved_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 font-medium">{{ $mv->currency_code }}</td>
                            <td class="px-4 py-2">{{ $mv->denomination?->denom_label ?? '-' }}</td>
                            <td class="px-4 py-2 text-center">
                                @php
                                    $badges = [
                                        'buy' => 'bg-green-100 text-green-800',
                                        'sell' => 'bg-red-100 text-red-800',
                                        'adjustment' => 'bg-yellow-100 text-yellow-800',
                                        'borrow' => 'bg-purple-100 text-purple-800',
                                        'return' => 'bg-indigo-100 text-indigo-800',
                                        'disbursement' => 'bg-orange-100 text-orange-800',
                                        'transfer_in' => 'bg-blue-100 text-blue-800',
                                        'transfer_out' => 'bg-pink-100 text-pink-800',
                                    ];
                                    $labels = [
                                        'buy' => 'ซื้อ', 'sell' => 'ขาย', 'adjustment' => 'ปรับปรุง',
                                        'borrow' => 'ยืม', 'return' => 'คืน', 'disbursement' => 'เบิก',
                                        'transfer_in' => 'โอนเข้า', 'transfer_out' => 'โอนออก',
                                    ];
                                    $badgeClass = $badges[$mv->movement_type] ?? 'bg-gray-100 text-gray-800';
                                    $label = $labels[$mv->movement_type] ?? $mv->movement_type;
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $badgeClass }}">
                                    {{ $label }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right font-medium {{ $mv->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ number_format($mv->amount, 2) }}
                            </td>
                            <td class="px-4 py-2 text-right">
                                {{ $mv->unit_price > 0 ? number_format($mv->unit_price, 4) : '—' }}
                            </td>
                            <td class="px-4 py-2">
                                @if ($mv->reference_type === 'transaction' && $mv->reference_id)
                                    <a href="{{ route('transaction.detail', $mv->reference_id) }}"
                                       class="text-blue-600 hover:underline text-xs">
                                        TX#{{ $mv->reference_id }}
                                    </a>
                                @elseif ($mv->reference_type === 'transfer' && $mv->reference_id)
                                    @php
                                        $tfr = \App\Models\StockTransfer::with(['fromCounter', 'toCounter'])->find($mv->reference_id);
                                    @endphp
                                    @if ($tfr)
                                        <span class="text-xs">
                                            {{ $tfr->transfer_no }}<br>
                                            <span class="text-gray-400">{{ $tfr->fromCounter?->counter_name }}</span>
                                            →
                                            <span class="text-gray-400">{{ $tfr->toCounter?->counter_name }}</span>
                                        </span>
                                    @else
                                        {{ $mv->reference_type ?? '—' }}
                                    @endif
                                @else
                                    {{ $mv->reference_type ?? '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-600">{{ $mv->movedBy?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $mv->note ?? '' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-400">ไม่มีข้อมูลการเคลื่อนไหว</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($movements->hasPages())
        <div class="p-4 border-t border-gray-200">
            {{ $movements->links() }}
        </div>
        @endif
    </div>
</div>
