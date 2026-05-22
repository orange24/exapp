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

    {{-- Header + Add button --}}
    <div class="flex items-center justify-between mb-4">
        <div></div>
        <button wire:click="$toggle('showForm')"
                class="px-4 py-2 text-sm font-semibold text-white rounded-lg"
                style="background:#0e513a;">
            @if ($showForm)
                ✕ ปิดฟอร์ม
            @else
                @php
                    $btnLabel = match($transferType) {
                        'borrow' => '+ ยืมสินค้า',
                        'return' => '+ คืนสินค้า',
                        'disbursement' => '+ เบิกจ่าย',
                        'intraday_return' => '+ คืนระหว่างวัน',
                        default => '+ เพิ่มรายการ',
                    };
                @endphp
                {{ $btnLabel }}
            @endif
        </button>
    </div>

    {{-- Transfer Form --}}
    @if ($showForm)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-bold mb-4" style="color:#0e513a;">
            @php
                $formTitle = match($transferType) {
                    'borrow' => 'ยืมสินค้าระหว่างเคาน์เตอร์ (Borrow)',
                    'return' => 'คืนสินค้าระหว่างเคาน์เตอร์ (Return)',
                    'disbursement' => 'เบิกจ่ายเงินให้เคาน์เตอร์ (Disbursement)',
                    'intraday_return' => 'คืนสินค้าระหว่างวัน (Intra-day Return)',
                    default => 'โอนสินค้า',
                };
            @endphp
            {{ $formTitle }}
        </h3>

        <form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                {{-- From Counter --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        @if (in_array($transferType, ['borrow']))
                            เคาน์เตอร์ต้นทาง (ให้ยืม)
                        @elseif ($transferType === 'disbursement')
                            เคาน์เตอร์ต้นทาง (เบิกจาก)
                        @else
                            เคาน์เตอร์ต้นทาง (คืนจาก)
                        @endif
                    </label>
                    <select wire:model.live="fromCounterId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- เลือกเคาน์เตอร์ --</option>
                        @foreach ($this->counters as $c)
                            <option value="{{ $c->id }}">{{ $c->counter_name }} ({{ $c->branch->branch_name ?? '' }})</option>
                        @endforeach
                    </select>
                    @error('fromCounterId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- To Counter --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        @if (in_array($transferType, ['borrow']))
                            เคาน์เตอร์ปลายทาง (รับยืม)
                        @elseif ($transferType === 'disbursement')
                            เคาน์เตอร์ปลายทาง (รับเบิก)
                        @else
                            เคาน์เตอร์ปลายทาง (รับคืน)
                        @endif
                    </label>
                    <select wire:model.live="toCounterId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- เลือกเคาน์เตอร์ --</option>
                        @foreach ($this->counters as $c)
                            <option value="{{ $c->id }}">{{ $c->counter_name }} ({{ $c->branch->branch_name ?? '' }})</option>
                        @endforeach
                    </select>
                    @error('toCounterId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Currency --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">สกุลเงิน (Currency)</label>
                    <select wire:model.live="currencyCode"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- เลือกสกุลเงิน --</option>
                        @foreach ($this->currencies as $cur)
                            <option value="{{ $cur->currency_code }}">{{ $cur->currency_code }} - {{ $cur->currency_name }}</option>
                        @endforeach
                    </select>
                    @error('currencyCode') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Denomination --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ธนบัตร (Denomination)</label>
                    <select wire:model.live="denominationId"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                        <option value="">-- เลือกธนบัตร --</option>
                        @foreach ($this->denominations as $d)
                            <option value="{{ $d->id }}">{{ $d->denom_label }}</option>
                        @endforeach
                    </select>
                    @error('denominationId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>

                {{-- Amount --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน (Amount)</label>
                    <input type="number" step="0.01" wire:model.blur="amount"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                           placeholder="0.00">
                    @error('amount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    @if ($availableStock > 0 || ($fromCounterId && $denominationId))
                        <div class="mt-1 text-xs text-gray-500">
                            สต็อกต้นทางคงเหลือ: <span class="font-semibold {{ $availableStock > 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($availableStock, 2) }}</span>
                        </div>
                    @endif
                </div>

                {{-- Note --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">หมายเหตุ (Note)</label>
                    <input type="text" wire:model.blur="note"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500"
                           placeholder="หมายเหตุ (ถ้ามี)">
                </div>
            </div>

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="$toggle('showForm')"
                        class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">
                    ยกเลิก
                </button>
                <button type="submit"
                        class="px-6 py-2 text-sm font-semibold text-white rounded-lg"
                        style="background:#0e513a;">
                    บันทึก
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Filter --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ประเภท</label>
                <select wire:model.live="filterType"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">ทั้งหมด</option>
                    <option value="borrow">ยืม (Borrow)</option>
                    <option value="return">คืน (Return)</option>
                    <option value="disbursement">เบิกจ่าย (Disbursement)</option>
                    <option value="intraday_return">คืนระหว่างวัน (Intra-day)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ตั้งแต่</label>
                <input type="date" wire:model.live="filterDateFrom"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ถึง</label>
                <input type="date" wire:model.live="filterDateTo"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
    </div>

    {{-- Transfers Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr style="background:#0e513a;" class="text-white">
                        <th class="px-4 py-3 text-left font-semibold">เลขที่</th>
                        <th class="px-4 py-3 text-center font-semibold">ประเภท</th>
                        <th class="px-4 py-3 text-left font-semibold">ต้นทาง</th>
                        <th class="px-4 py-3 text-left font-semibold">ปลายทาง</th>
                        <th class="px-4 py-3 text-left font-semibold">สกุลเงิน</th>
                        <th class="px-4 py-3 text-left font-semibold">ธนบัตร</th>
                        <th class="px-4 py-3 text-right font-semibold">จำนวน</th>
                        <th class="px-4 py-3 text-right font-semibold">ต้นทุน</th>
                        <th class="px-4 py-3 text-left font-semibold">ผู้ทำ</th>
                        <th class="px-4 py-3 text-left font-semibold">วันที่/เวลา</th>
                        <th class="px-4 py-3 text-center font-semibold">สถานะ</th>
                        <th class="px-4 py-3 text-left font-semibold">หมายเหตุ</th>
                        <th class="px-4 py-3 text-center font-semibold">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->transfers as $idx => $tf)
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ $tf->transfer_no }}</td>
                            <td class="px-4 py-2 text-center">
                                @php
                                    $typeBadges = [
                                        'borrow' => 'bg-purple-100 text-purple-800',
                                        'return' => 'bg-indigo-100 text-indigo-800',
                                        'disbursement' => 'bg-orange-100 text-orange-800',
                                        'intraday_return' => 'bg-blue-100 text-blue-800',
                                    ];
                                    $typeLabels = [
                                        'borrow' => 'ยืม',
                                        'return' => 'คืน',
                                        'disbursement' => 'เบิกจ่าย',
                                        'intraday_return' => 'คืนระหว่างวัน',
                                    ];
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $typeBadges[$tf->transfer_type] ?? 'bg-gray-100 text-gray-800' }}">
                                    {{ $typeLabels[$tf->transfer_type] ?? $tf->transfer_type }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-xs">{{ $tf->fromCounter->counter_name ?? '-' }}</td>
                            <td class="px-4 py-2 text-xs">{{ $tf->toCounter->counter_name ?? '-' }}</td>
                            <td class="px-4 py-2 font-medium">{{ $tf->currency_code }}</td>
                            <td class="px-4 py-2">{{ $tf->denomination->denom_label ?? '-' }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($tf->amount, 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ $tf->unit_price > 0 ? number_format($tf->unit_price, 4) : '—' }}</td>
                            <td class="px-4 py-2 text-gray-600 text-xs">{{ $tf->createdByUser->name ?? '—' }}</td>
                            <td class="px-4 py-2 whitespace-nowrap text-xs">{{ $tf->transferred_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 text-center">
                                @if ($tf->status === 'cancelled')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">ยกเลิก</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">สำเร็จ</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $tf->note ?? '' }}</td>
                            <td class="px-4 py-2 text-center">
                                @if ($tf->status === 'completed')
                                    <button type="button"
                                            wire:click="cancelTransfer({{ $tf->id }})"
                                            wire:confirm="ยืนยันยกเลิกรายการ {{ $tf->transfer_no }}? สต็อกจะถูกคืนกลับ"
                                            class="text-red-600 hover:text-red-800 text-xs font-medium">
                                        ยกเลิก
                                    </button>
                                @else
                                    <span class="text-gray-400 text-xs">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="13" class="px-4 py-8 text-center text-gray-400">ไม่มีรายการ</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($this->transfers->hasPages())
        <div class="p-4 border-t border-gray-200">
            {{ $this->transfers->links() }}
        </div>
        @endif
    </div>
</div>
