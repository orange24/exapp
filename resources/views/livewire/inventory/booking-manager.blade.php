<div>
    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4 text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div></div>
        <button wire:click="$toggle('showForm')" class="px-4 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">
            {{ $showForm ? '✕ ปิดฟอร์ม' : '+ สร้าง Booking' }}
        </button>
    </div>

    {{-- Form --}}
    @if ($showForm)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-bold mb-4" style="color:#0e513a;">สร้าง Booking ใหม่</h3>
        <form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ประเภท</label>
                    <select wire:model.live="type" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="buy">ซื้อ (Buy) — ลูกค้าจะนำเงินมาขาย</option>
                        <option value="sell">ขาย (Sell) — ลูกค้าจะมาซื้อเงิน</option>
                    </select>
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
                    <label class="block text-sm font-medium text-gray-700 mb-1">ธนบัตร (Denomination)</label>
                    <select wire:model.live="denominationId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- เลือกธนบัตร --</option>
                        @foreach ($this->denominations as $d)
                            <option value="{{ $d->id }}">{{ $d->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">จำนวนเงิน</label>
                    <input type="number" step="0.01" wire:model.blur="amount" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="0.00">
                    @error('amount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">อัตราแลกเปลี่ยน</label>
                    <input type="number" step="0.0001" wire:model.blur="rate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="0.0000">
                    @error('rate') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">หมดอายุใน (นาที)</label>
                    <input type="number" wire:model.blur="expiresIn" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="60">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อลูกค้า (ถ้ามี)</label>
                    <input type="text" wire:model.blur="customerName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="ชื่อลูกค้า">
                </div>
            </div>
            <div class="mt-4 flex justify-end gap-2">
                <button type="button" wire:click="$toggle('showForm')" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">ยกเลิก</button>
                <button type="submit" class="px-6 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">บันทึก</button>
            </div>
        </form>
    </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
                <select wire:model.live="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">ทั้งหมด</option>
                    <option value="pending">รอดำเนินการ</option>
                    <option value="confirmed">ยืนยันแล้ว</option>
                    <option value="cancelled">ยกเลิก</option>
                    <option value="expired">หมดอายุ</option>
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
                        <th class="px-4 py-3 text-left font-semibold">#</th>
                        <th class="px-4 py-3 text-center font-semibold">ประเภท</th>
                        <th class="px-4 py-3 text-left font-semibold">สกุลเงิน</th>
                        <th class="px-4 py-3 text-right font-semibold">จำนวน</th>
                        <th class="px-4 py-3 text-right font-semibold">อัตรา</th>
                        <th class="px-4 py-3 text-right font-semibold">มูลค่า THB</th>
                        <th class="px-4 py-3 text-center font-semibold">สถานะ</th>
                        <th class="px-4 py-3 text-left font-semibold">หมดอายุ</th>
                        <th class="px-4 py-3 text-left font-semibold">ลูกค้า</th>
                        <th class="px-4 py-3 text-left font-semibold">ผู้สร้าง</th>
                        <th class="px-4 py-3 text-center font-semibold">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->bookings as $idx => $bk)
                        @php
                            $isExpired = $bk->status === 'pending' && $bk->expires_at && $bk->expires_at->isPast();
                        @endphp
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                            <td class="px-4 py-2">{{ $bk->id }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $bk->type === 'buy' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $bk->type === 'buy' ? 'ซื้อ' : 'ขาย' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 font-medium">{{ $bk->currency_code }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($bk->amount, 2) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($bk->rate, 4) }}</td>
                            <td class="px-4 py-2 text-right">{{ number_format($bk->amount * $bk->rate, 2) }}</td>
                            <td class="px-4 py-2 text-center">
                                @if ($isExpired)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600">หมดอายุ</span>
                                @elseif ($bk->status === 'pending')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">รอดำเนินการ</span>
                                @elseif ($bk->status === 'confirmed')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">ยืนยันแล้ว</span>
                                @elseif ($bk->status === 'cancelled')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">ยกเลิก</span>
                                @elseif ($bk->status === 'expired')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-200 text-gray-600">หมดอายุ</span>
                                @endif
                                @if ($bk->transaction_id)
                                    <div class="mt-1"><span class="text-xs text-blue-600 font-mono">{{ $bk->transaction?->trns_no }}</span></div>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs {{ $isExpired ? 'text-red-500 font-semibold' : '' }}">
                                {{ $bk->expires_at?->format('d/m H:i') ?? '—' }}
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-600">{{ $bk->customer?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs text-gray-600">{{ $bk->createdBy?->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-center">
                                @if ($bk->status === 'pending' && !$isExpired)
                                    <div class="flex gap-1 justify-center">
                                        <button wire:click="confirmBooking({{ $bk->id }})" wire:confirm="ยืนยัน Booking นี้?"
                                                class="px-2 py-1 text-xs text-white bg-green-600 rounded hover:bg-green-700">ยืนยัน</button>
                                        <button wire:click="cancelBooking({{ $bk->id }})" wire:confirm="ยกเลิก Booking นี้?"
                                                class="px-2 py-1 text-xs text-white bg-red-600 rounded hover:bg-red-700">ยกเลิก</button>
                                    </div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="px-4 py-8 text-center text-gray-400">ไม่มีรายการ Booking</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->bookings->hasPages())
        <div class="p-4 border-t border-gray-200">{{ $this->bookings->links() }}</div>
        @endif
    </div>
</div>
