{{-- ฟอร์มขายให้ธนาคาร — 1 ใบ 1 ธนบัตร จัดสรรจากหลายเคาน์เตอร์ต้นทาง --}}
<div class="flex-1 min-w-0 bg-white rounded-lg shadow p-6">
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
                <x-number-input model="totalAmount" :value="$totalAmount" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="80,000" />
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
                    <option value="pending">ระบุภายหลัง</option>
                </select>
                <p class="text-xs text-gray-400 mt-1">เลือก "ระบุภายหลัง" ได้ แต่ต้องมาระบุก่อนกดยืนยัน</p>
            </div>
        </div>

        {{-- ไม่มีอะไรให้จัดสรร: ต้องบอกให้ชัด ไม่งั้นกดปุ่มแล้วเงียบ
             เพราะ error ของ sourceAmounts เคยซ่อนอยู่ในบล็อกที่ไม่ถูก render --}}
        @if ($denominationId && $this->counterStocks->isEmpty())
        <div class="mb-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-sm text-amber-800">
            <strong>ไม่มีสต็อกให้ขาย</strong> — ยังไม่มีเคาน์เตอร์ไหนในสาขาที่คุณดูแลถือธนบัตรนี้อยู่
            <div class="text-xs mt-1 text-amber-700">
                เติมสต็อกก่อนได้จากเมนู <strong>ซื้อจากธนาคาร</strong> หรือรับซื้อจากลูกค้าที่หน้าเคาน์เตอร์
            </div>
        </div>
        @elseif (! $denominationId)
        <div class="mb-4 p-4 bg-gray-50 border border-gray-200 rounded-lg text-sm text-gray-600">
            เลือกสกุลเงินและธนบัตรก่อน เพื่อดูว่ามีสต็อกที่สาขาไหนบ้าง
        </div>
        @endif

        {{-- error ของการจัดสรรต้องอยู่นอกบล็อกตาราง ไม่งั้นหายไปพร้อมตาราง --}}
        @error('sourceAmounts')
            <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm font-medium">{{ $message }}</div>
        @enderror

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
            <button type="submit" wire:confirm="สร้างรายการขายธนาคาร? สต็อกจะถูก Reserve ทันที"
                    class="px-6 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">
                สร้างรายการ (Reserve Stock)
            </button>
        </div>
    </form>
</div>
