@php $isBuy = $this->isBuy(); @endphp
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
            {{ $showForm ? '✕ ปิดฟอร์ม' : ($isBuy ? '+ สร้างรายการซื้อจากธนาคาร' : '+ สร้างรายการขายธนาคาร') }}
        </button>
    </div>

    {{-- Create Form + Stock Info side panel --}}
    @if ($showForm)
    <div class="flex gap-4 mb-6">
    <div class="flex-1 min-w-0 bg-white rounded-lg shadow p-6">
        <h3 class="text-lg font-bold mb-4" style="color:#0e513a;">
            {{ $isBuy ? 'ซื้อเงินตราจากธนาคาร (Buy from Bank)' : 'ขายเงินตราให้ธนาคาร (Sell to Bank)' }}
        </h3>
        <form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $isBuy ? 'ธนาคารต้นทาง' : 'ธนาคารปลายทาง' }}</label>
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
                    <x-number-input model="totalAmount" :value="$totalAmount" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="80,000" />
                    @error('totalAmount') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $isBuy ? 'เรทซื้อจากธนาคาร' : 'เรทขายธนาคาร' }}</label>
                    <input type="number" step="0.0001" wire:model.blur="bankRate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="32.6500">
                    @error('bankRate') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">{{ $isBuy ? 'วิธีจ่ายเงิน' : 'วิธีรับเงิน' }}</label>
                    <select wire:model="settlementMethod" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="bank_transfer">โอนเข้าบัญชี</option>
                        <option value="cash">เงินสด</option>
                        <option value="cheque">เช็ค</option>
                        <option value="pending">ระบุภายหลัง</option>
                    </select>
                    <p class="text-xs text-gray-400 mt-1">เลือก "ระบุภายหลัง" ได้ แต่ต้องมาระบุก่อนกดยืนยัน</p>
                </div>
            </div>

            {{-- ซื้อเข้ากองกลางที่เดียว — ไม่มีตารางปลายทางให้เลือก
                 กระจายออกสาขาทีหลังผ่านเมนู ยืม/คืน/โอน --}}
            @if ($isBuy)
            @php $central = $this->centralCounter; $cStock = $this->centralStock; @endphp
            <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg">
                <h4 class="text-sm font-bold text-green-800 mb-3">รับเข้ากองกลาง</h4>

                @if (! $central)
                    <div class="text-sm text-red-700">
                        ไม่พบเคาน์เตอร์กองกลาง — บัญชีนี้ยังไม่ได้ผูกกับสำนักงานใหญ่ กรุณาติดต่อผู้ดูแลระบบ
                    </div>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                        <div>
                            <span class="text-gray-500">ปลายทาง</span>
                            <div class="font-semibold">{{ $central->counter_name }}</div>
                            <div class="text-xs text-gray-500">{{ $central->branch->branch_name ?? '' }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Stock ปัจจุบัน</span>
                            <div class="font-mono font-bold">{{ number_format($cStock->quantity ?? 0, 2) }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Avg Cost เดิม</span>
                            <div class="font-mono">{{ ($cStock->avg_cost ?? 0) > 0 ? number_format($cStock->avg_cost, 4) : '—' }}</div>
                        </div>
                        <div>
                            <span class="text-gray-500">Avg Cost ใหม่</span>
                            <div class="font-mono font-bold text-green-700">
                                {{ (float) $totalAmount > 0 && (float) $bankRate > 0
                                    ? number_format($this->projectedAvgCost((float) ($cStock->quantity ?? 0), (float) ($cStock->avg_cost ?? 0), (float) $totalAmount), 4)
                                    : '—' }}
                            </div>
                        </div>
                    </div>

                    @php $pv = $this->profitPreview; @endphp
                    @if ($pv['revenue'] > 0)
                    <div class="mt-4 p-3 bg-white rounded-lg border border-green-200">
                        <h4 class="text-sm font-bold text-gray-700 mb-2">สรุปต้นทุน</h4>
                        <div class="grid grid-cols-2 gap-3 text-sm">
                            <div>
                                <span class="text-gray-500">THB ที่ต้องจ่าย</span>
                                <div class="font-mono font-bold">{{ number_format($pv['revenue'], 2) }}</div>
                            </div>
                            <div>
                                <span class="text-gray-500">ต้นทุนต่อหน่วย</span>
                                <div class="font-mono font-bold">{{ number_format((float) $bankRate, 4) }}</div>
                            </div>
                        </div>
                        <p class="text-xs text-gray-400 mt-2">ซื้อเข้าไม่มีกำไร/ขาดทุน — เงินที่จ่ายคือต้นทุนของสต็อกที่รับเข้ามา</p>
                    </div>
                    @endif
                @endif
            </div>
            @endif

            {{-- ไม่มีอะไรให้จัดสรร: ต้องบอกให้ชัด ไม่งั้นกดปุ่มแล้วเงียบ
                 เพราะ error ของ sourceAmounts เคยซ่อนอยู่ในบล็อกที่ไม่ถูก render --}}
            @if (! $isBuy && $denominationId && $this->counterStocks->isEmpty())
            <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
                <strong>ไม่มีสต็อกให้ขาย</strong> — ยังไม่มีเคาน์เตอร์ไหนในสาขาที่คุณดูแลถือธนบัตรนี้อยู่
                <div class="text-xs mt-1 text-amber-700">
                    เติมสต็อกก่อนได้จากเมนู <strong>ซื้อจากธนาคาร</strong> หรือรับซื้อจากลูกค้าที่หน้าเคาน์เตอร์
                </div>
            </div>
            @elseif (! $isBuy && ! $denominationId)
            <div class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
                เลือกสกุลเงินและธนบัตรก่อน เพื่อดูว่ามีสต็อกที่สาขาไหนบ้าง
            </div>
            @endif

            {{-- error ของการจัดสรรต้องอยู่นอกบล็อกตาราง ไม่งั้นหายไปพร้อมตาราง --}}
            @error('sourceAmounts')
                <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm font-medium">{{ $message }}</div>
            @enderror

            {{-- Source Counters --}}
            @if (! $isBuy && $this->counterStocks->isNotEmpty())
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
                                    <x-number-input model="sourceAmounts.{{ $stock->counter_id }}" :value="$sourceAmounts[$stock->counter_id] ?? null"
                                           class="w-28 border border-blue-300 rounded px-2 py-1 text-sm"
                                           placeholder="0" />
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

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
                <button type="submit"
                        wire:confirm="{{ $isBuy ? 'สร้างรายการซื้อจากธนาคาร? สต็อกจะเข้าเมื่อกดยืนยันรับของ' : 'สร้างรายการขายธนาคาร? สต็อกจะถูก Reserve ทันที' }}"
                        class="px-6 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">
                    {{ $isBuy ? 'สร้างรายการ (รอรับของ)' : 'สร้างรายการ (Reserve Stock)' }}
                </button>
            </div>
        </form>
    </div>

    {{-- Side Panel: Stock Info — รวมทุกสาขาที่ trader รับผิดชอบ --}}
    <div class="w-72 flex-shrink-0 hidden lg:block">
        <div class="sticky top-4">
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                <div class="px-4 py-3" style="background:#0e513a;">
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        Stock Info
                    </h3>
                </div>

                <div class="p-3">
                    <div class="text-center mb-3 pb-2 border-b" style="border-color:#0e513a;">
                        <div class="text-sm font-bold" style="color:#0e513a;">เรทเฉลี่ย + คงเหลือรวม</div>
                        <div class="text-xs text-gray-500 mt-1">ทุกสาขาที่รับผิดชอบ</div>
                    </div>

                    @if (empty($this->stockInfo))
                        <div class="py-6 text-center text-xs text-gray-400">
                            ยังไม่มีข้อมูลสต็อก/เรทของสาขาที่รับผิดชอบ
                        </div>
                    @else
                    <div class="grid grid-cols-3 gap-2 px-2 pb-1 text-[10px] font-semibold text-gray-400 uppercase">
                        <div class="text-right">ซื้อ</div>
                        <div class="text-right">ขาย</div>
                        <div class="text-right">คงเหลือ</div>
                    </div>
                    <div class="overflow-y-auto" style="max-height: 450px;">
                        @php $prevCurrency = ''; @endphp
                        @foreach ($this->stockInfo as $info)
                            @if ($prevCurrency !== $info['currency_code'])
                                @if ($prevCurrency !== '')
                                    <div class="mt-2"></div>
                                @endif
                                <div class="px-2 py-1.5 text-xs font-semibold text-white border-b border-gray-300" style="background:#0e513a; position: sticky; top: 0; z-index: 10;">
                                    {{ $info['currency_code'] }} — {{ $info['currency_name'] }}
                                </div>
                                @php $prevCurrency = $info['currency_code']; @endphp
                            @endif

                            <div class="px-2 py-1.5 border-b border-gray-100 hover:bg-gray-50 text-xs">
                                <div class="text-gray-600 font-semibold mb-1">{{ $info['denomination_label'] }}</div>
                                <div class="grid grid-cols-3 gap-2">
                                    <div class="font-mono text-green-700 text-right">
                                        {{ $info['buy_rate'] > 0 ? number_format($info['buy_rate'], 4) : '-' }}
                                    </div>
                                    <div class="font-mono text-red-700 text-right">
                                        {{ $info['sell_rate'] > 0 ? number_format($info['sell_rate'], 4) : '-' }}
                                    </div>
                                    <div class="font-mono text-right font-bold {{ $info['available'] > 0 ? 'text-gray-800' : 'text-gray-300' }}">
                                        {{ number_format($info['available'], 0) }}
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    </div>
    @endif

    {{-- Filter --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
                <select wire:model.live="filterStatus" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">ทั้งหมด</option>
                    @if ($isBuy)
                        <option value="ordered">รอรับของ</option>
                    @else
                        <option value="reserved">Reserved</option>
                        <option value="in_transit">In Transit</option>
                        <option value="delivered">Delivered</option>
                    @endif
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
                        @unless ($isBuy)<th class="px-3 py-2.5 text-right font-semibold">P&L</th>@endunless
                        <th class="px-3 py-2.5 text-center font-semibold">สถานะ</th>
                        <th class="px-3 py-2.5 text-center font-semibold">{{ $isBuy ? 'วิธีจ่ายเงิน' : 'วิธีรับเงิน' }}</th>
                        <th class="px-3 py-2.5 text-left font-semibold">{{ $isBuy ? 'ปลายทาง' : 'แหล่ง' }}</th>
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
                                'ordered' => 'bg-amber-100 text-amber-800',
                                'reserved' => 'bg-yellow-100 text-yellow-800',
                                'in_transit' => 'bg-blue-100 text-blue-800',
                                'delivered' => 'bg-indigo-100 text-indigo-800',
                                'completed' => 'bg-green-100 text-green-800',
                                'cancelled' => 'bg-red-100 text-red-800',
                                default => 'bg-gray-100 text-gray-800',
                            };
                            $statusLabel = match($sale->status) {
                                'draft' => 'Draft',
                                'ordered' => 'รอรับของ',
                                'reserved' => 'Reserved',
                                'in_transit' => 'In Transit',
                                'delivered' => 'Delivered',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                                default => $sale->status,
                            };
                            $sourceList = $sale->sources->map(fn($s) => $s->counter->counter_name . ' (' . number_format($s->amount, 0) . ')')->implode(', ');
                            $isClosed = in_array($sale->status, ['completed', 'cancelled'], true);
                            // ปิดรายการไม่ได้ถ้ายังไม่ระบุวิธีรับ/จ่ายเงิน — service ก็ throw ซ้ำอีกชั้น
                            $needsSettlement = $sale->settlement_method === 'pending';
                        @endphp
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                            <td class="px-3 py-2 font-mono text-xs">{{ $sale->sale_no }}</td>
                            <td class="px-3 py-2 text-xs">{{ $sale->bank_name }}</td>
                            <td class="px-3 py-2 font-medium">{{ $sale->currency_code }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($sale->total_amount, 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($sale->bank_rate, 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono font-bold">{{ number_format($sale->total_thb, 2) }}</td>
                            @unless ($isBuy)
                            <td class="px-3 py-2 text-right font-mono {{ $sale->profit_loss >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ $sale->profit_loss >= 0 ? '+' : '' }}{{ number_format($sale->profit_loss, 2) }}
                            </td>
                            @endunless
                            <td class="px-3 py-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge }}">{{ $statusLabel }}</span>
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if ($isClosed)
                                    <span class="text-xs text-gray-500">
                                        {{ ['bank_transfer' => 'โอนเข้าบัญชี', 'cash' => 'เงินสด', 'cheque' => 'เช็ค', 'pending' => 'ระบุภายหลัง'][$sale->settlement_method] ?? $sale->settlement_method }}
                                    </span>
                                @else
                                    <select wire:change="updateSettlementMethod({{ $sale->id }}, $event.target.value)"
                                            class="border rounded px-2 py-1 text-xs {{ $needsSettlement ? 'border-orange-400 bg-orange-50 text-orange-800' : 'border-gray-300' }}">
                                        <option value="bank_transfer" @selected($sale->settlement_method === 'bank_transfer')>โอนเข้าบัญชี</option>
                                        <option value="cash" @selected($sale->settlement_method === 'cash')>เงินสด</option>
                                        <option value="cheque" @selected($sale->settlement_method === 'cheque')>เช็ค</option>
                                        <option value="pending" @selected($sale->settlement_method === 'pending')>ระบุภายหลัง</option>
                                    </select>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs text-gray-500" style="max-width:200px;">{{ $sourceList }}</td>
                            <td class="px-3 py-2 text-xs">{{ $sale->createdByUser?->name ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs whitespace-nowrap">{{ $sale->created_at?->format('d/m H:i') }}</td>
                            <td class="px-3 py-2 text-center">
                                <div class="flex flex-wrap gap-1 justify-center">
                                    @if ($sale->status === 'ordered')
                                        <button wire:click="completeSale({{ $sale->id }})" wire:confirm="ยืนยันรับของ? สต็อกจะเข้าเคาน์เตอร์ + สร้าง GL Journal"
                                                @disabled($needsSettlement)
                                                title="{{ $needsSettlement ? 'ระบุวิธีจ่ายเงินก่อน' : '' }}"
                                                class="px-2 py-1 text-xs font-medium text-white rounded {{ $needsSettlement ? 'opacity-40 cursor-not-allowed' : '' }}" style="background:#0e513a;">ยืนยันรับของ</button>
                                        <button wire:click="cancelSale({{ $sale->id }})" wire:confirm="ยกเลิกรายการนี้?"
                                                class="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 rounded hover:bg-red-100">ยกเลิก</button>
                                    @elseif ($sale->status === 'reserved')
                                        <button wire:click="markTransit({{ $sale->id }})" wire:confirm="เปลี่ยนสถานะเป็น In Transit?"
                                                class="px-2 py-1 text-xs font-medium text-blue-700 bg-blue-50 rounded hover:bg-blue-100">ขนส่ง</button>
                                        <button wire:click="completeSale({{ $sale->id }})" wire:confirm="ยืนยันขายสำเร็จ? ตัด stock + สร้าง GL Journal"
                                                @disabled($needsSettlement) title="{{ $needsSettlement ? 'ระบุวิธีรับเงินก่อน' : '' }}"
                                                class="px-2 py-1 text-xs font-medium text-white rounded {{ $needsSettlement ? 'opacity-40 cursor-not-allowed' : '' }}" style="background:#0e513a;">ยืนยัน</button>
                                        <button wire:click="cancelSale({{ $sale->id }})" wire:confirm="ยกเลิกรายการนี้? สต็อก Release กลับ"
                                                class="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 rounded hover:bg-red-100">ยกเลิก</button>
                                    @elseif ($sale->status === 'in_transit')
                                        <button wire:click="markDelivered({{ $sale->id }})" wire:confirm="ยืนยันส่งถึงแล้ว?"
                                                class="px-2 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 rounded hover:bg-indigo-100">ส่งถึง</button>
                                        <button wire:click="completeSale({{ $sale->id }})" wire:confirm="ยืนยันขายสำเร็จ?"
                                                @disabled($needsSettlement) title="{{ $needsSettlement ? 'ระบุวิธีรับเงินก่อน' : '' }}"
                                                class="px-2 py-1 text-xs font-medium text-white rounded {{ $needsSettlement ? 'opacity-40 cursor-not-allowed' : '' }}" style="background:#0e513a;">ยืนยัน</button>
                                        <button wire:click="cancelSale({{ $sale->id }})" wire:confirm="ยกเลิก?"
                                                class="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 rounded hover:bg-red-100">ยกเลิก</button>
                                    @elseif ($sale->status === 'delivered')
                                        <button wire:click="completeSale({{ $sale->id }})" wire:confirm="ยืนยันขายสำเร็จ? ตัด stock + GL"
                                                @disabled($needsSettlement) title="{{ $needsSettlement ? 'ระบุวิธีรับเงินก่อน' : '' }}"
                                                class="px-2 py-1 text-xs font-medium text-white rounded {{ $needsSettlement ? 'opacity-40 cursor-not-allowed' : '' }}" style="background:#0e513a;">ยืนยันขาย</button>
                                        <button wire:click="cancelSale({{ $sale->id }})" wire:confirm="ยกเลิก?"
                                                class="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 rounded hover:bg-red-100">ยกเลิก</button>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isBuy ? 12 : 13 }}" class="px-4 py-8 text-center text-gray-400">{{ $isBuy ? 'ยังไม่มีรายการซื้อจากธนาคาร' : 'ยังไม่มีรายการขายธนาคาร' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->sales->hasPages())
        <div class="p-4 border-t">{{ $this->sales->links() }}</div>
        @endif
    </div>
</div>
