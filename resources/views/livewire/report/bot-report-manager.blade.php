<div>
    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4 text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    @if (!$activeReportId)
    {{-- ═══════════════ LIST MODE ═══════════════ --}}

    {{-- Generate new report --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-sm font-semibold text-gray-700 mb-3">สร้างรายงานใหม่ (Generate)</h3>
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">เดือน</label>
                <select wire:model.blur="genMonth" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}">{{ $m }} - {{ ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][$m] }}</option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">ปี (ค.ศ.)</label>
                <input type="number" wire:model.blur="genYear" min="2020" max="2030" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-24">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">สาขา</label>
                <select wire:model.blur="genBranchId" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">ทั้งหมด (ทุกสาขา)</option>
                    @foreach ($this->branches as $b)
                        <option value="{{ $b->id }}">{{ $b->branch_name }}</option>
                    @endforeach
                </select>
            </div>
            <button wire:click="generate" wire:confirm="ดึงข้อมูลจาก transactions มาสร้างรายงาน?"
                    class="px-5 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">
                Clone & สร้างรายงาน
            </button>
        </div>
        <p class="mt-2 text-xs text-gray-400">ระบบจะ clone ข้อมูลธุรกรรมทั้งเดือนมาไว้ใน staging table — แก้ไขได้โดยไม่กระทบข้อมูลต้นทาง | ถ้าเดือนนี้มีอยู่แล้ว จะดึงเฉพาะรายการใหม่เพิ่มเข้ามา (Sync)</p>
    </div>

    {{-- Reports list --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50">
            <h3 class="font-semibold text-gray-700">รายงานที่สร้างแล้ว</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr style="background:#0e513a;" class="text-white">
                        <th class="px-4 py-3 text-left font-semibold">งวดเดือน</th>
                        <th class="px-4 py-3 text-left font-semibold">สาขา</th>
                        <th class="px-4 py-3 text-right font-semibold">ซื้อ</th>
                        <th class="px-4 py-3 text-right font-semibold">ขาย</th>
                        <th class="px-4 py-3 text-center font-semibold">สถานะ</th>
                        <th class="px-4 py-3 text-left font-semibold">สร้างโดย</th>
                        <th class="px-4 py-3 text-left font-semibold">วันที่สร้าง</th>
                        <th class="px-4 py-3 text-center font-semibold">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->reports as $idx => $rpt)
                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                        <td class="px-4 py-2 font-medium">{{ $rpt->report_month }}</td>
                        <td class="px-4 py-2">{{ $rpt->branch?->branch_name ?? 'ทั้งหมด' }}</td>
                        <td class="px-4 py-2 text-right text-green-600">{{ $rpt->transactions()->where('trns_type', 'BUY')->count() }}</td>
                        <td class="px-4 py-2 text-right text-red-600">{{ $rpt->transactions()->where('trns_type', 'SELL')->count() }}</td>
                        <td class="px-4 py-2 text-center">
                            @if ($rpt->status === 'draft')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">ร่าง</span>
                            @elseif ($rpt->status === 'confirmed')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">ยืนยันแล้ว</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">ส่งออกแล้ว</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-xs text-gray-600">{{ $rpt->generatedByUser?->name ?? '-' }}</td>
                        <td class="px-4 py-2 text-xs">{{ $rpt->generated_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-2 text-center">
                            <div class="flex gap-1 justify-center">
                                <button wire:click="viewReport({{ $rpt->id }})" class="px-2 py-1 text-xs text-blue-600 bg-blue-50 rounded hover:bg-blue-100">ดู/แก้ไข</button>
                                @if ($rpt->status === 'draft')
                                    <button wire:click="confirmReport({{ $rpt->id }})" wire:confirm="ยืนยันรายงานนี้?"
                                            class="px-2 py-1 text-xs text-white bg-green-600 rounded hover:bg-green-700">ยืนยัน</button>
                                    <button wire:click="deleteReport({{ $rpt->id }})" wire:confirm="ลบรายงานนี้ทั้งหมด?"
                                            class="px-2 py-1 text-xs text-white bg-red-600 rounded hover:bg-red-700">ลบ</button>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">ยังไม่มีรายงาน — กด "Clone & สร้างรายงาน" ด้านบน</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @else
    {{-- ═══════════════ DETAIL / EDIT MODE ═══════════════ --}}
    @php $rpt = $this->activeReport; @endphp

    <div class="mb-4 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <button wire:click="backToList" class="text-sm text-blue-600 hover:underline">&larr; กลับ</button>
            <h3 class="text-lg font-bold" style="color:#0e513a;">
                รายงาน {{ $rpt->report_month }} — {{ $rpt->branch?->branch_name ?? 'ทั้งหมด' }}
            </h3>
            @if ($rpt->status === 'draft')
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">ร่าง — แก้ไขได้</span>
            @elseif ($rpt->status === 'confirmed')
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">ยืนยันแล้ว</span>
            @endif
        </div>
        <div class="flex gap-2">
            @if ($rpt->status === 'draft')
                <button wire:click="syncNewTransactions" wire:confirm="ดึงรายการใหม่จาก source เข้ามาเพิ่ม? (รายการที่แก้ไขแล้วจะไม่ถูกกระทบ)"
                        class="px-4 py-2 text-sm font-semibold text-white bg-amber-500 rounded-lg hover:bg-amber-600 inline-flex items-center gap-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    ดึงข้อมูลเพิ่ม (Sync)
                </button>
                <button wire:click="confirmReport({{ $rpt->id }})" wire:confirm="ยืนยันรายงานนี้?"
                        class="px-4 py-2 text-sm font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700">ยืนยันรายงาน</button>
            @endif
            <a href="{{ route('reports.bot-monthly.export-staging', $rpt->id) }}"
               class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 rounded-lg hover:bg-blue-700 inline-flex items-center gap-1">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                ดาวน์โหลด Excel (ธปท.)
            </a>
        </div>
    </div>

    {{-- Provider info summary --}}
    <div class="bg-white rounded-lg shadow p-4 mb-4 text-sm grid grid-cols-2 md:grid-cols-4 gap-3">
        <div><span class="text-gray-500">รหัสสถาบัน:</span> <strong>{{ $rpt->institution_code }}</strong></div>
        <div><span class="text-gray-500">License:</span> <strong>{{ $rpt->license_no }}</strong></div>
        <div><span class="text-gray-500">บริษัท:</span> <strong>{{ $rpt->company_name }}</strong></div>
        <div><span class="text-gray-500">สาขา:</span> <strong>{{ $rpt->branch_name ?: 'ทั้งหมด' }}</strong></div>
    </div>

    {{-- Filter --}}
    <div class="bg-white rounded-lg shadow p-4 mb-4">
        <div class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-700">แสดง:</label>
            <select wire:model.live="filterType" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">ทั้งหมด ({{ $rpt->transactions()->count() }} รายการ)</option>
                <option value="BUY">ซื้อ — Buy ({{ $rpt->buyTransactions()->count() }})</option>
                <option value="SELL">ขาย — Sell ({{ $rpt->sellTransactions()->count() }})</option>
            </select>
        </div>
    </div>

    {{-- Transaction table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                    <tr style="background:#0e513a;" class="text-white">
                        <th class="px-2 py-2 text-center font-semibold w-8">#</th>
                        <th class="px-2 py-2 text-center font-semibold">ประเภท</th>
                        <th class="px-2 py-2 text-left font-semibold">วันที่</th>
                        <th class="px-2 py-2 text-left font-semibold">ประเภทลูกค้า</th>
                        <th class="px-2 py-2 text-left font-semibold">ชื่อลูกค้า</th>
                        <th class="px-2 py-2 text-left font-semibold">รหัสประเภท</th>
                        <th class="px-2 py-2 text-left font-semibold">เลขเอกสาร</th>
                        <th class="px-2 py-2 text-left font-semibold">สัญชาติ</th>
                        <th class="px-2 py-2 text-left font-semibold">สกุลเงิน</th>
                        <th class="px-2 py-2 text-right font-semibold">อัตรา</th>
                        <th class="px-2 py-2 text-right font-semibold">จำนวน FX</th>
                        <th class="px-2 py-2 text-right font-semibold">จำนวน THB</th>
                        <th class="px-2 py-2 text-left font-semibold">หมายเหตุ</th>
                        @if ($rpt->status === 'draft')
                        <th class="px-2 py-2 text-center font-semibold w-20">จัดการ</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->transactions as $idx => $tx)
                        @if ($editingRowId === $tx->id)
                        {{-- Editing row --}}
                        <tr class="bg-yellow-50">
                            <td class="px-2 py-1 text-center text-gray-400">{{ $tx->id }}</td>
                            <td class="px-2 py-1 text-center">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $tx->trns_type === 'BUY' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $tx->trns_type }}</span>
                            </td>
                            <td class="px-1 py-1"><input type="date" wire:model.blur="editForm.trns_date" class="w-full border rounded px-1 py-0.5 text-xs"></td>
                            <td class="px-1 py-1">
                                <select wire:model.blur="editForm.customer_type" class="w-full border rounded px-1 py-0.5 text-xs">
                                    <option value="คนไทย">คนไทย</option>
                                    <option value="ชาวต่างชาติ">ชาวต่างชาติ</option>
                                    <option value="นิติบุคคลไทย">นิติบุคคลไทย</option>
                                </select>
                            </td>
                            <td class="px-1 py-1"><input type="text" wire:model.blur="editForm.customer_name" class="w-full border rounded px-1 py-0.5 text-xs"></td>
                            <td class="px-1 py-1">
                                <select wire:model.blur="editForm.id_type_code" class="w-full border rounded px-1 py-0.5 text-xs">
                                    <option value="324001">324001 บัตร ปชช</option>
                                    <option value="324002">324002 Passport</option>
                                    <option value="324004">324004 นิติบุคคล</option>
                                    <option value="324010">324010 ต่างชาติอื่น</option>
                                </select>
                            </td>
                            <td class="px-1 py-1"><input type="text" wire:model.blur="editForm.id_number" class="w-full border rounded px-1 py-0.5 text-xs"></td>
                            <td class="px-1 py-1"><input type="text" wire:model.blur="editForm.nationality" class="w-full border rounded px-1 py-0.5 text-xs w-12"></td>
                            <td class="px-1 py-1"><input type="text" wire:model.blur="editForm.currency_code" class="w-full border rounded px-1 py-0.5 text-xs w-12"></td>
                            <td class="px-1 py-1"><input type="number" step="0.00000001" wire:model.blur="editForm.exchange_rate" class="w-full border rounded px-1 py-0.5 text-xs text-right w-20"></td>
                            <td class="px-1 py-1"><input type="number" step="0.01" wire:model.blur="editForm.fx_amount" class="w-full border rounded px-1 py-0.5 text-xs text-right w-20"></td>
                            <td class="px-1 py-1"><input type="number" step="0.01" wire:model.blur="editForm.thb_amount" class="w-full border rounded px-1 py-0.5 text-xs text-right w-20"></td>
                            <td class="px-1 py-1"><input type="text" wire:model.blur="editForm.remark" class="w-full border rounded px-1 py-0.5 text-xs"></td>
                            <td class="px-2 py-1 text-center">
                                <div class="flex gap-1 justify-center">
                                    <button wire:click="saveEdit" class="px-1.5 py-0.5 text-xs text-white bg-green-600 rounded">บันทึก</button>
                                    <button wire:click="cancelEdit" class="px-1.5 py-0.5 text-xs text-gray-600 bg-gray-200 rounded">ยกเลิก</button>
                                </div>
                            </td>
                        </tr>
                        @else
                        {{-- Normal row --}}
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                            <td class="px-2 py-1 text-center text-gray-400">{{ $tx->id }}</td>
                            <td class="px-2 py-1 text-center">
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium {{ $tx->trns_type === 'BUY' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">{{ $tx->trns_type === 'BUY' ? 'ซื้อ' : 'ขาย' }}</span>
                            </td>
                            <td class="px-2 py-1">{{ $tx->trns_date->format('d/m') }}</td>
                            <td class="px-2 py-1">{{ $tx->customer_type }}</td>
                            <td class="px-2 py-1">{{ $tx->customer_name }}</td>
                            <td class="px-2 py-1 font-mono">{{ $tx->id_type_code }}</td>
                            <td class="px-2 py-1 font-mono">{{ $tx->id_number }}</td>
                            <td class="px-2 py-1">{{ $tx->nationality }}</td>
                            <td class="px-2 py-1 font-medium">{{ $tx->currency_code }}</td>
                            <td class="px-2 py-1 text-right font-mono">{{ number_format($tx->exchange_rate, 4) }}</td>
                            <td class="px-2 py-1 text-right font-mono">{{ number_format($tx->fx_amount, 2) }}</td>
                            <td class="px-2 py-1 text-right font-mono">{{ number_format($tx->thb_amount, 2) }}</td>
                            <td class="px-2 py-1 text-gray-500">{{ $tx->remark }}</td>
                            @if ($rpt->status === 'draft')
                            <td class="px-2 py-1 text-center">
                                <div class="flex gap-1 justify-center">
                                    <button wire:click="startEdit({{ $tx->id }})" class="px-1.5 py-0.5 text-xs text-blue-600 bg-blue-50 rounded hover:bg-blue-100">แก้ไข</button>
                                    <button wire:click="deleteRow({{ $tx->id }})" wire:confirm="ลบรายการนี้?" class="px-1.5 py-0.5 text-xs text-red-600 bg-red-50 rounded hover:bg-red-100">ลบ</button>
                                </div>
                            </td>
                            @endif
                        </tr>
                        @endif
                    @empty
                    <tr><td colspan="14" class="px-4 py-8 text-center text-gray-400">ไม่มีรายการ</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->transactions->hasPages())
        <div class="p-4 border-t border-gray-200">{{ $this->transactions->links() }}</div>
        @endif
    </div>
    @endif
</div>
