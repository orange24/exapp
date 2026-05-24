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
            {{ $showForm ? '✕ ปิดฟอร์ม' : '+ สร้างรายการขายธนาคาร' }}
        </button>
    </div>

    {{-- Create Form --}}
    @if ($showForm)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-bold mb-4" style="color:#0e513a;">ขายเงินตราให้ธนาคาร (Sell to Bank)</h3>
        <form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ธนาคารปลายทาง</label>
                    <select wire:model.live="bankName" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- เลือกธนาคาร --</option>
                        <option value="กสิกรไทย">กสิกรไทย (KBANK)</option>
                        <option value="กรุงเทพ">กรุงเทพ (BBL)</option>
                        <option value="ไทยพาณิชย์">ไทยพาณิชย์ (SCB)</option>
                        <option value="กรุงไทย">กรุงไทย (KTB)</option>
                        <option value="กรุงศรี">กรุงศรี (BAY)</option>
                        <option value="ทหารไทยธนชาต">ทหารไทยธนชาต (TTB)</option>
                        <option value="ซีไอเอ็มบี">ซีไอเอ็มบี (CIMB)</option>
                        <option value="ธนาคารอื่น">ธนาคารอื่น</option>
                    </select>
                    @error('bankName') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">สกุลเงิน</label>
                    <select wire:model.live="currencyCode" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- เลือกสกุลเงิน --</option>
                        @foreach ($this->currencies as $cur)
                            <option value="{{ $cur->currency_code }}">{{ $cur->currency_code }} - {{ $cur->currency_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">ธนบัตร</label>
                    <select wire:model.live="denominationId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- เลือกธนบัตร --</option>
                        @foreach ($this->denominations as $d)
                            <option value="{{ $d->id }}">{{ $d->display_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">จำนวนรวม</label>
                    <input type="number" step="0.01" wire:model.blur="totalAmount" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="80,000">
                    @error('totalAmount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">เรทขายธนาคาร</label>
                    <input type="number" step="0.0001" wire:model.blur="bankRate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="32.6500">
                    @error('bankRate') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">วิธีรับเงิน</label>
                    <select wire:model="settlementMethod" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="bank_transfer">โอนเข้าบัญชี</option>
                        <option value="cash">เงินสด</option>
                        <option value="cheque">เช็ค</option>
                    </select>
                </div>
            </div>

            {{-- Source Counters --}}
            @if ($this->counterStocks->isNotEmpty())
            <div class="mb-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <h4 class="text-sm font-bold text-blue-800 mb-3">แหล่งสต็อก (Source Branches) — ระบุจำนวนจากแต่ละเคาน์เตอร์</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-blue-200">
                                <th class="px-3 py-2 text-left font-semibold text-blue-900">เคาน์เตอร์</th>
                                <th class="px-3 py-2 text-left font-semibold text-blue-900">สาขา</th>
                                <th class="px-3 py-2 text-right font-semibold text-blue-900">Stock</th>
                                <th class="px-3 py-2 text-right font-semibold text-blue-900">Hold</th>
                                <th class="px-3 py-2 text-right font-semibold text-blue-900">Available</th>
                                <th class="px-3 py-2 text-right font-semibold text-blue-900">Avg Cost</th>
                                <th class="px-3 py-2 text-center font-semibold text-blue-900">จำนวนที่จัดสรร</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->counterStocks as $stock)
                            <tr class="border-b border-blue-100">
                                <td class="px-3 py-2 font-medium">{{ $stock->counter->counter_name }}</td>
                                <td class="px-3 py-2 text-xs text-gray-500">{{ $stock->counter->branch->branch_name ?? '' }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ number_format($stock->quantity, 2) }}</td>
                                <td class="px-3 py-2 text-right font-mono {{ $stock->hold_amount > 0 ? 'text-orange-600' : 'text-gray-400' }}">{{ number_format($stock->hold_amount, 2) }}</td>
                                <td class="px-3 py-2 text-right font-mono font-bold {{ $stock->available > 0 ? 'text-green-700' : 'text-red-600' }}">{{ number_format($stock->available, 2) }}</td>
                                <td class="px-3 py-2 text-right font-mono text-blue-700">{{ number_format($stock->avg_cost, 4) }}</td>
                                <td class="px-3 py-2 text-center">
                                    <input type="number" step="0.01" min="0" max="{{ $stock->available }}"
                                           wire:model.blur="sourceAmounts.{{ $stock->counter_id }}"
                                           class="w-28 border border-blue-300 rounded px-2 py-1 text-sm text-right font-mono"
                                           placeholder="0">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @error('sourceAmounts') <div class="mt-2 text-red-600 text-xs font-medium">{{ $message }}</div> @enderror

                {{-- P&L Preview --}}
                @php $pv = $this->profitPreview; @endphp
                @if ($pv['revenue'] > 0)
                <div class="mt-4 p-3 bg-white rounded-lg border border-blue-200">
                    <h4 class="text-sm font-bold text-gray-700 mb-2">P&L Preview</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div>
                            <span class="text-gray-500">จัดสรร</span>
                            <div class="font-mono font-bold {{ abs($pv['allocated'] - (float)$totalAmount) < 0.01 ? 'text-green-700' : 'text-red-600' }}">
                                {{ number_format($pv['allocated'], 2) }} / {{ number_format((float)$totalAmount, 2) }}
                                {{ abs($pv['allocated'] - (float)$totalAmount) < 0.01 ? '✓' : '✗' }}
                            </div>
                        </div>
                        <div>
                            <span class="text-gray-500">Revenue (THB)</span>
                            <div class="font-mono font-bold">{{ number_format($pv['revenue'], 2) }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Cost (THB)</span>
                            <div class="font-mono font-bold">{{ number_format($pv['cost'], 2) }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Profit/Loss</span>
                            <div class="font-mono font-bold {{ $pv['pl'] >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ $pv['pl'] >= 0 ? '+' : '' }}{{ number_format($pv['pl'], 2) }}
                            </div>
                        </div>
                    </div>
                </div>
                @endif
            </div>
            @endif

            <div class="flex items-center gap-3">
                <input type="text" wire:model.blur="notes" class="flex-1 border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="หมายเหตุ (ถ้ามี)">
                <button type="button" wire:click="$toggle('showForm')" class="px-4 py-2 text-sm text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200">ยกเลิก</button>
                <button type="submit" wire:confirm="สร้างรายการขายธนาคาร? สต็อกจะถูก Reserve ทันที"
                        class="px-6 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">
                    สร้างรายการ (Reserve Stock)
                </button>
            </div>
        </form>
    </div>
    @endif

    {{-- Filter --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
                <select wire:model.live="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">ทั้งหมด</option>
                    <option value="reserved">Reserved</option>
                    <option value="in_transit">In Transit</option>
                    <option value="delivered">Delivered</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Sales Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr style="background:#0e513a;" class="text-white">
                        <th class="px-3 py-2.5 text-left font-semibold">เลขที่</th>
                        <th class="px-3 py-2.5 text-left font-semibold">ธนาคาร</th>
                        <th class="px-3 py-2.5 text-left font-semibold">สกุลเงิน</th>
                        <th class="px-3 py-2.5 text-right font-semibold">จำนวน</th>
                        <th class="px-3 py-2.5 text-right font-semibold">เรท</th>
                        <th class="px-3 py-2.5 text-right font-semibold">THB</th>
                        <th class="px-3 py-2.5 text-right font-semibold">P&L</th>
                        <th class="px-3 py-2.5 text-center font-semibold">สถานะ</th>
                        <th class="px-3 py-2.5 text-left font-semibold">แหล่ง</th>
                        <th class="px-3 py-2.5 text-left font-semibold">สร้างโดย</th>
                        <th class="px-3 py-2.5 text-left font-semibold">วันที่</th>
                        <th class="px-3 py-2.5 text-center font-semibold">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->sales as $idx => $sale)
                        @php
                            $statusBadge = match($sale->status) {
                                'draft' => 'bg-gray-100 text-gray-700',
                                'reserved' => 'bg-yellow-100 text-yellow-800',
                                'in_transit' => 'bg-blue-100 text-blue-800',
                                'delivered' => 'bg-indigo-100 text-indigo-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-800',
                            };
                            $statusLabel = match($sale->status) {
                                'draft' => 'Draft',
                                'reserved' => 'Reserved',
                                'in_transit' => 'In Transit',
                                'delivered' => 'Delivered',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                                default => $sale->status,
                            };
                            $sourceList = $sale->sources->map(fn($s) => $s->counter->counter_name . ' (' . number_format($s->amount, 0) . ')')->implode(', ');
                        @endphp
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                            <td class="px-3 py-2 font-mono text-xs">{{ $sale->sale_no }}</td>
                            <td class="px-3 py-2 text-xs">{{ $sale->bank_name }}</td>
                            <td class="px-3 py-2 font-medium">{{ $sale->currency_code }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($sale->total_amount, 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($sale->bank_rate, 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono font-bold">{{ number_format($sale->total_thb, 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono {{ $sale->profit_loss >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ $sale->profit_loss >= 0 ? '+' : '' }}{{ number_format($sale->profit_loss, 2) }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500" style="max-width:200px;">{{ $sourceList }}</td>
                            <td class="px-3 py-2 text-xs">{{ $sale->createdByUser?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs whitespace-nowrap">{{ $sale->created_at?->format('d/m H:i') }}</td>
                            <td class="px-3 py-2 text-center">
                                <div class="flex flex-wrap gap-1 justify-center">
                                    @if ($sale->status === 'reserved')
                                        <button wire:click="markTransit({{ $sale->id }})" wire:confirm="เปลี่ยนสถานะเป็น In Transit?"
                                                class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100">ขนส่ง</button>
                                        <button wire:click="completeSale({{ $sale->id }})" wire:confirm="ยืนยันขายสำเร็จ? ตัด stock + สร้าง GL Journal"
                                                class="px-2 py-1 text-xs font-medium text-white rounded" style="background:#0e513a;">ยืนยัน</button>
                                        <button wire:click="cancelSale({{ $sale->id }})" wire:confirm="ยกเลิกรายการนี้? สต็อก Release กลับ"
                                                class="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 rounded hover:bg-red-100">ยกเลิก</button>
                                    @elseif ($sale->status === 'in_transit')
                                        <button wire:click="markDelivered({{ $sale->id }})" wire:confirm="ยืนยันส่งถึงแล้ว?"
                                                class="px-2 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 rounded hover:bg-indigo-100">ส่งถึง</button>
                                        <button wire:click="completeSale({{ $sale->id }})" wire:confirm="ยืนยันขายสำเร็จ?"
                                                class="px-2 py-1 text-xs font-medium text-white rounded" style="background:#0e513a;">ยืนยัน</button>
                                        <button wire:click="cancelSale({{ $sale->id }})" wire:confirm="ยกเลิก?"
                                                class="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 rounded hover:bg-red-100">ยกเลิก</button>
                                    @elseif ($sale->status === 'delivered')
                                        <button wire:click="completeSale({{ $sale->id }})" wire:confirm="ยืนยันขายสำเร็จ? ตัด stock + GL"
                                                class="px-2 py-1 text-xs font-medium text-white rounded" style="background:#0e513a;">ยืนยันขาย</button>
                                        <button wire:click="cancelSale({{ $sale->id }})" wire:confirm="ยกเลิก?"
                                                class="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 rounded hover:bg-red-100">ยกเลิก</button>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="px-4 py-8 text-center text-gray-400">ยังไม่มีรายการขายธนาคาร</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->sales->hasPages())
        <div class="p-4 border-t">{{ $this->sales->links() }}</div>
        @endif
    </div>
</div>
