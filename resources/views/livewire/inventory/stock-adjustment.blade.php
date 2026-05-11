<div>
    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4 text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    <div class="flex items-center justify-between mb-4">
        <div></div>
        <button wire:click="$toggle('showForm')" class="px-4 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">
            {{ $showForm ? '✕ ปิดฟอร์ม' : '+ ปรับปรุงสต็อก' }}
        </button>
    </div>

    {{-- Form --}}
    @if ($showForm)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-bold mb-4" style="color:#0e513a;">ปรับปรุงสต็อก (Stock Adjustment)</h3>
        <form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เคาน์เตอร์</label>
                    <select wire:model.live="counterId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- เลือกเคาน์เตอร์ --</option>
                        @foreach ($this->counters as $c)
                            <option value="{{ $c->id }}">{{ $c->counter_name }} ({{ $c->branch->branch_name ?? '' }})</option>
                        @endforeach
                    </select>
                    @error('counterId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">สกุลเงิน</label>
                    <select wire:model.live="currencyCode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- เลือกสกุลเงิน --</option>
                        @foreach ($this->currencies as $cur)
                            <option value="{{ $cur->currency_code }}">{{ $cur->currency_code }} - {{ $cur->currency_name }}</option>
                        @endforeach
                    </select>
                    @error('currencyCode') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ธนบัตร</label>
                    <select wire:model.live="denominationId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- เลือกธนบัตร --</option>
                        @foreach ($this->denominations as $d)
                            <option value="{{ $d->id }}">{{ $d->denom_label }}</option>
                        @endforeach
                    </select>
                    @error('denominationId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ประเภทการปรับ</label>
                    <select wire:model.live="adjustType" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="add">เพิ่ม (Add) — เงินเกิน/รับเพิ่ม</option>
                        <option value="subtract">ลด (Subtract) — เงินขาด/สูญหาย</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน</label>
                    <input type="number" step="0.01" wire:model.blur="amount" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="0.00">
                    @error('amount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                    @if ($counterId && $denominationId)
                        <div class="mt-1 text-xs text-gray-500">
                            สต็อกปัจจุบัน: <span class="font-semibold {{ $currentStock >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($currentStock, 2) }}</span>
                        </div>
                    @endif
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เหตุผล <span class="text-red-500">*</span></label>
                    <input type="text" wire:model.blur="note" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="ระบุเหตุผลการปรับปรุง">
                    @error('note') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            @if ($amount && $counterId && $denominationId)
            <div class="mt-4 p-3 rounded-lg {{ $adjustType === 'add' ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200' }}">
                <span class="text-sm font-medium {{ $adjustType === 'add' ? 'text-green-800' : 'text-red-800' }}">
                    ผลลัพธ์: {{ number_format($currentStock, 2) }}
                    {{ $adjustType === 'add' ? '+' : '-' }}
                    {{ number_format((float)$amount, 2) }}
                    = <strong>{{ number_format($adjustType === 'add' ? $currentStock + (float)$amount : $currentStock - (float)$amount, 2) }}</strong>
                </span>
            </div>
            @endif

            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="$toggle('showForm')" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">ยกเลิก</button>
                <button type="submit" wire:confirm="ยืนยันการปรับปรุงสต็อก?" class="px-6 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">บันทึก</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เคาน์เตอร์</label>
                <select wire:model.live="counterId" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">ทั้งหมด</option>
                    @foreach ($this->counters as $c)
                        <option value="{{ $c->id }}">{{ $c->counter_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ตั้งแต่</label>
                <input type="date" wire:model.live="filterDateFrom" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ถึง</label>
                <input type="date" wire:model.live="filterDateTo" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr style="background:#0e513a;" class="text-white">
                        <th class="px-4 py-3 text-left font-semibold">วันที่/เวลา</th>
                        <th class="px-4 py-3 text-left font-semibold">เคาน์เตอร์</th>
                        <th class="px-4 py-3 text-left font-semibold">สกุลเงิน</th>
                        <th class="px-4 py-3 text-left font-semibold">ธนบัตร</th>
                        <th class="px-4 py-3 text-right font-semibold">จำนวน</th>
                        <th class="px-4 py-3 text-left font-semibold">ผู้ทำรายการ</th>
                        <th class="px-4 py-3 text-left font-semibold">หมายเหตุ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->adjustments as $idx => $adj)
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                            <td class="px-4 py-2 whitespace-nowrap">{{ $adj->moved_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 text-xs">{{ $adj->counter->counter_name ?? '-' }}</td>
                            <td class="px-4 py-2 font-medium">{{ $adj->currency_code }}</td>
                            <td class="px-4 py-2">{{ $adj->denomination?->denom_label ?? '-' }}</td>
                            <td class="px-4 py-2 text-right font-medium {{ $adj->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $adj->amount >= 0 ? '+' : '' }}{{ number_format($adj->amount, 2) }}
                            </td>
                            <td class="px-4 py-2 text-gray-600 text-xs">{{ $adj->movedBy?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-500 text-xs">{{ $adj->note ?? '' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">ไม่มีรายการปรับปรุง</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->adjustments->hasPages())
        <div class="p-4 border-t border-gray-200">{{ $this->adjustments->links() }}</div>
        @endif
    </div>
</div>
