<div>
    {{-- Summary bar --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-7 gap-3 mb-6">
        <div class="rounded-lg p-4 text-white" style="background:#0e513a;">
            <div class="text-xs opacity-75">ยอดยกมา (Opening)</div>
            <div class="text-lg font-bold">{{ number_format($this->summary['opening'], 2) }}</div>
            <div class="text-[10px] opacity-60">ต้นทุนเฉลี่ย</div>
        </div>
        <div class="rounded-lg p-4 text-white bg-green-600">
            <div class="text-xs opacity-75">ซื้อเข้า (Bought)</div>
            <div class="text-lg font-bold">{{ number_format($this->summary['bought'], 2) }}</div>
            <div class="text-[10px] opacity-60">เรทในบิล</div>
        </div>
        <div class="rounded-lg p-4 text-white bg-red-600">
            <div class="text-xs opacity-75">ขายออก (Sold)</div>
            <div class="text-lg font-bold">{{ number_format($this->summary['sold'], 2) }}</div>
            <div class="text-[10px] opacity-60">เรทในบิล</div>
        </div>
        <div class="rounded-lg p-4 text-white bg-blue-600">
            <div class="text-xs opacity-75">โอนเข้า (Transfer In)</div>
            <div class="text-lg font-bold">{{ number_format($this->summary['tfr_in'], 2) }}</div>
            <div class="text-[10px] opacity-60">เรทในบิล</div>
        </div>
        <div class="rounded-lg p-4 text-white bg-pink-600">
            <div class="text-xs opacity-75">โอนออก (Transfer Out)</div>
            <div class="text-lg font-bold">{{ number_format($this->summary['tfr_out'], 2) }}</div>
            <div class="text-[10px] opacity-60">เรทในบิล</div>
        </div>
        <div class="rounded-lg p-4 text-white bg-yellow-600">
            <div class="text-xs opacity-75">ปรับปรุง (Adjust)</div>
            <div class="text-lg font-bold">{{ number_format($this->summary['adjust'], 2) }}</div>
            <div class="text-[10px] opacity-60">เรทในบิล</div>
        </div>
        <div class="rounded-lg p-4 text-white bg-amber-700">
            <div class="text-xs opacity-75">คงเหลือ (Remaining)</div>
            <div class="text-lg font-bold">{{ number_format($this->summary['remaining'], 2) }}</div>
            <div class="text-[10px] opacity-60">ต้นทุนเฉลี่ย</div>
        </div>
    </div>

    <p class="-mt-4 mb-6 text-xs text-gray-500">
        ซื้อเข้า/ขายออก/โอน/ปรับปรุง = เงินบาทตามเรทที่ใช้จริงในแต่ละรายการ ตรงกับบิลและรายการของฉัน &nbsp;·&nbsp;
        ยอดยกมา/คงเหลือ = มูลค่าสต็อกที่ยังไม่ได้ขาย คิดด้วยต้นทุนเฉลี่ย &nbsp;·&nbsp;
        แถบนี้จึงไม่บวกลบกันลงตัว ส่วนต่างคือกำไร/ขาดทุน ดูที่
        <a href="{{ route('reports.profit-loss') }}" class="text-blue-600 hover:underline">รายงานกำไรขาดทุน</a>
    </p>

    {{-- เงินบาทในลิ้นชัก — แยกแถบเพราะไม่มี denomination จึงไม่อยู่ในตารางสต็อกข้างล่าง --}}
    @if ($counterId)
    <div class="mb-6 rounded-lg border border-gray-200 bg-white p-4 shadow">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="font-semibold text-gray-700">เงินบาทในลิ้นชัก (THB Cash)</h3>
            <a href="{{ route('inventory.open-close-day') }}"
               class="text-xs text-blue-600 hover:underline">จัดการเงินทุน →</a>
        </div>
        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            <div class="rounded-lg bg-gray-50 p-3">
                <div class="text-xs text-gray-600">ยอดยกมา</div>
                <div class="font-mono text-lg font-bold text-gray-800">{{ number_format($this->thbSummary['opening'], 2) }}</div>
            </div>
            <div class="rounded-lg bg-green-50 p-3">
                <div class="text-xs text-gray-600">รับเข้า</div>
                <div class="font-mono text-lg font-bold text-green-700">{{ number_format($this->thbSummary['in'], 2) }}</div>
            </div>
            <div class="rounded-lg bg-red-50 p-3">
                <div class="text-xs text-gray-600">จ่ายออก</div>
                <div class="font-mono text-lg font-bold text-red-700">{{ number_format($this->thbSummary['out'], 2) }}</div>
            </div>
            <div class="rounded-lg p-3 {{ $this->thbSummary['closing'] < 0 ? 'bg-amber-50' : 'bg-blue-50' }}">
                <div class="text-xs text-gray-600">คงเหลือ</div>
                <div class="font-mono text-lg font-bold {{ $this->thbSummary['closing'] < 0 ? 'text-amber-700' : 'text-blue-800' }}">
                    {{ number_format($this->thbSummary['closing'], 2) }}
                </div>
            </div>
        </div>
    </div>
    @endif

    {{-- Filters --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เคาน์เตอร์</label>
                <select wire:model.live="counterId"
                        class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="">-- เลือก --</option>
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
                        <option value="{{ $cur->currency_code }}">{{ $cur->currency_code }} — {{ $cur->currency_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่</label>
                <input type="date" wire:model.live="date"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
            </div>
        </div>
    </div>

    {{-- Inventory table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr style="background:#0e513a;" class="text-white">
                        <th class="px-3 py-3 text-left font-semibold">สกุลเงิน</th>
                        <th class="px-3 py-3 text-left font-semibold">ธนบัตร</th>
                        <th class="px-3 py-3 text-right font-semibold">ยอดยกมา</th>
                        <th class="px-3 py-3 text-right font-semibold text-green-300">ซื้อเข้า</th>
                        <th class="px-3 py-3 text-right font-semibold text-red-300">ขายออก</th>
                        <th class="px-3 py-3 text-right font-semibold text-blue-300">โอนเข้า</th>
                        <th class="px-3 py-3 text-right font-semibold text-pink-300">โอนออก</th>
                        <th class="px-3 py-3 text-right font-semibold text-yellow-300">ปรับปรุง</th>
                        <th class="px-3 py-3 text-right font-semibold">คงเหลือ</th>
                        <th class="px-3 py-3 text-right font-semibold">ต้นทุนเฉลี่ย</th>
                        <th class="px-3 py-3 text-right font-semibold">มูลค่า (THB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->inventoryData as $idx => $row)
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                            <td class="px-3 py-2 font-medium">{{ $row['currency_code'] }}</td>
                            <td class="px-3 py-2">{{ $row['denom_label'] }}</td>
                            <td class="px-3 py-2 text-right">{{ number_format($row['opening'], 2) }}</td>
                            <td class="px-3 py-2 text-right text-green-600 font-medium">{{ $row['bought'] > 0 ? number_format($row['bought'], 2) : '' }}</td>
                            <td class="px-3 py-2 text-right text-red-600 font-medium">{{ $row['sold'] > 0 ? number_format($row['sold'], 2) : '' }}</td>
                            <td class="px-3 py-2 text-right text-blue-600 font-medium">{{ $row['tfr_in'] > 0 ? number_format($row['tfr_in'], 2) : '' }}</td>
                            <td class="px-3 py-2 text-right text-pink-600 font-medium">{{ $row['tfr_out'] > 0 ? number_format($row['tfr_out'], 2) : '' }}</td>
                            <td class="px-3 py-2 text-right font-medium {{ $row['adjust'] >= 0 ? 'text-yellow-600' : 'text-red-600' }}">{{ $row['adjust'] != 0 ? number_format($row['adjust'], 2) : '' }}</td>
                            <td class="px-3 py-2 text-right font-bold">{{ number_format($row['remaining'], 2) }}</td>
                            <td class="px-3 py-2 text-right text-gray-500">{{ $row['avg_cost'] > 0 ? number_format($row['avg_cost'], 4) : '—' }}</td>
                            <td class="px-3 py-2 text-right font-medium">{{ number_format($row['thb_value'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="px-4 py-8 text-center text-gray-400">
                                @if (!$this->counterId)
                                    กรุณาเลือกเคาน์เตอร์
                                @else
                                    ไม่มีข้อมูลสต็อก
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($this->inventoryData->count())
                <tfoot>
                    <tr class="bg-gray-100 font-bold border-t-2">
                        <td class="px-3 py-2" colspan="2">รวมทั้งหมด</td>
                        <td class="px-3 py-2 text-right">{{ number_format($this->inventoryData->sum('opening'), 2) }}</td>
                        <td class="px-3 py-2 text-right text-green-600">{{ number_format($this->inventoryData->sum('bought'), 2) }}</td>
                        <td class="px-3 py-2 text-right text-red-600">{{ number_format($this->inventoryData->sum('sold'), 2) }}</td>
                        <td class="px-3 py-2 text-right text-blue-600">{{ number_format($this->inventoryData->sum('tfr_in'), 2) }}</td>
                        <td class="px-3 py-2 text-right text-pink-600">{{ number_format($this->inventoryData->sum('tfr_out'), 2) }}</td>
                        <td class="px-3 py-2 text-right text-yellow-600">{{ number_format($this->inventoryData->sum('adjust'), 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($this->inventoryData->sum('remaining'), 2) }}</td>
                        <td class="px-3 py-2"></td>
                        <td class="px-3 py-2 text-right">{{ number_format($this->inventoryData->sum('thb_value'), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Formula explanation — ใช้กับตารางแยกสกุลเงินข้างบน ซึ่งเป็นจำนวนเงินตรา
         ไม่ใช่แถบสรุปด้านบนสุดที่เป็นเงินบาทคนละฐาน --}}
    <div class="mt-4 text-xs text-gray-400 text-right">
        ตารางด้านบน (จำนวนเงินตรา): คงเหลือ = ยอดยกมา + ซื้อเข้า - ขายออก + โอนเข้า - โอนออก + ปรับปรุง
    </div>
</div>
