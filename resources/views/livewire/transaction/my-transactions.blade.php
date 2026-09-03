<div x-data="{
    showDetail: false,
    showCancelReason: false,
    detailData: null,
    cancelReasonText: '',
    loadingDetail: false,
    async openDetail(id) {
        this.loadingDetail = true;
        this.showDetail = true;
        try {
            const res = await fetch('/transaction/' + id + '/detail');
            this.detailData = (await res.json()).data;
        } catch (e) {
            this.detailData = null;
        }
        this.loadingDetail = false;
    },
    openCancelReason(reason) {
        this.cancelReasonText = reason;
        this.showCancelReason = true;
    }
}">
    {{-- Flash messages --}}
    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-300 text-red-700 rounded text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Search bar --}}
    <div class="bg-white rounded-lg shadow p-4 mb-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">เลขที่เอกสาร (Transaction No)</label>
                <input type="text" wire:model="searchTrnsNo" placeholder="B2024..., S2024..."
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-48 focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">วันที่เริ่ม (Date From)</label>
                <input type="date" wire:model="dateFrom"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">วันที่สิ้นสุด (Date To)</label>
                <input type="date" wire:model="dateTo"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex gap-2">
                <button wire:click="search"
                        class="bg-[#0e513a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
                    ค้นหา (Search)
                </button>
                <button wire:click="clear"
                        class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-300 transition-colors">
                    ล้าง (Clear)
                </button>
            </div>
        </div>
    </div>

    {{-- Results table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">วันที่</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">เลขที่</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ประเภท</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">เคาน์เตอร์</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 uppercase">ลูกค้า</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">สกุลเงิน</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 uppercase">จำนวน</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">สถานะ</th>
                        <th class="px-4 py-3 text-center text-xs font-semibold text-gray-600 uppercase">จัดการ</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse ($transactions as $trns)
                        <tr class="{{ $trns->flag_cancel === 'R' ? 'bg-amber-50' : ($trns->flag_cancel === 'Y' ? 'bg-gray-100' : '') }}">
                            <td class="px-4 py-3 text-sm {{ $trns->isCancelled() ? 'line-through text-gray-400' : 'text-gray-700' }}">
                                {{ $trns->trns_datetime->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3 text-sm font-mono {{ $trns->isCancelled() ? 'line-through text-gray-400' : 'text-gray-900' }}">
                                {{ $trns->trns_no }}
                            </td>
                            <td class="px-4 py-3 text-sm">
                                @if ($trns->trns_type === 'BUYING')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">BUYING</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">SELLING</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-sm {{ $trns->isCancelled() ? 'line-through text-gray-400' : 'text-gray-700' }}">
                                {{ $trns->counter_name }}
                            </td>
                            <td class="px-4 py-3 text-sm {{ $trns->isCancelled() ? 'line-through text-gray-400' : 'text-gray-700' }}">
                                {{ $trns->cust_name ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-sm text-center font-mono {{ $trns->isCancelled() ? 'line-through text-gray-400' : 'text-gray-700' }}">
                                {{ $trns->total_currency }}
                            </td>
                            <td class="px-4 py-3 text-sm text-right font-mono {{ $trns->isCancelled() ? 'line-through text-gray-400' : 'text-gray-900' }}">
                                {{ number_format($trns->total_thb, 2) }}
                            </td>
                            <td class="px-4 py-3 text-center">
                                @if ($trns->flag_cancel === 'N')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">ปกติ</span>
                                @elseif ($trns->flag_cancel === 'R')
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800">รอยกเลิก</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800">ยกเลิก</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-1 flex-wrap">
                                    {{-- Detail button --}}
                                    <button @click="openDetail({{ $trns->id }})"
                                            class="text-xs bg-blue-50 text-blue-700 px-2 py-1 rounded hover:bg-blue-100 transition-colors">
                                        ดูรายละเอียด
                                    </button>

                                    @if ($trns->flag_cancel === 'N')
                                        {{-- Print — ยิงเข้า iframe ที่ซ่อนไว้ สลิปจะ window.print() เอง (?print=Y) --}}
                                        <button type="button"
                                                onclick="document.getElementById('print-iframe').src = '{{ route('transaction.print', $trns) }}?print=Y&t=' + Date.now();"
                                                class="text-xs bg-gray-50 text-gray-700 px-2 py-1 rounded hover:bg-gray-100 transition-colors">
                                            พิมพ์
                                        </button>
                                        {{-- Request cancel --}}
                                        <button wire:click="requestCancel({{ $trns->id }})"
                                                class="text-xs bg-red-50 text-red-700 px-2 py-1 rounded hover:bg-red-100 transition-colors">
                                            ขอยกเลิก
                                        </button>
                                    @elseif ($trns->flag_cancel === 'R')
                                        {{-- Show reason --}}
                                        <button @click="openCancelReason('{{ addslashes($trns->cancel_reason) }}')"
                                                class="text-xs bg-amber-50 text-amber-700 px-2 py-1 rounded hover:bg-amber-100 transition-colors">
                                            ดูเหตุผล
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-sm text-gray-500">
                                ไม่พบรายการ (No transactions found)
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if ($transactions->hasPages())
            <div class="px-4 py-3 border-t border-gray-200">
                {{ $transactions->links() }}
            </div>
        @endif
    </div>

    {{-- Detail Modal --}}
    <div x-show="showDetail" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         @keydown.escape.window="showDetail = false">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[80vh] overflow-y-auto mx-4" @click.away="showDetail = false">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">รายละเอียดธุรกรรม (Transaction Detail)</h3>
                <button @click="showDetail = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6 relative">
                <template x-if="loadingDetail">
                    <div class="text-center py-8 text-gray-500">กำลังโหลด...</div>
                </template>
                <template x-if="!loadingDetail && detailData">
                    <div>
                        {{-- CANCEL Stamp --}}
                        <template x-if="detailData.flag_cancel === 'Y'">
                            <div class="absolute top-4 right-6 pointer-events-none" style="transform: rotate(-18deg);">
                                <div style="border: 4px solid #dc2626; border-radius: 12px; padding: 6px 24px; opacity: 0.85;">
                                    <span style="font-size: 32px; font-weight: 900; color: #dc2626; letter-spacing: 4px; line-height: 1;">ยกเลิก</span>
                                    <br>
                                    <span style="font-size: 14px; font-weight: 700; color: #dc2626; letter-spacing: 6px;">CANCELLED</span>
                                </div>
                            </div>
                        </template>
                        <template x-if="detailData.flag_cancel === 'R'">
                            <div class="absolute top-4 right-6 pointer-events-none" style="transform: rotate(-18deg);">
                                <div style="border: 4px solid #d97706; border-radius: 12px; padding: 6px 24px; opacity: 0.85;">
                                    <span style="font-size: 28px; font-weight: 900; color: #d97706; letter-spacing: 3px; line-height: 1;">รอยกเลิก</span>
                                    <br>
                                    <span style="font-size: 12px; font-weight: 700; color: #d97706; letter-spacing: 4px;">PENDING CANCEL</span>
                                </div>
                            </div>
                        </template>

                        <div class="grid grid-cols-2 gap-3 mb-4 text-sm">
                            <div><span class="text-gray-500">เลขที่:</span> <span class="font-mono font-medium" x-text="detailData.trns_no"></span></div>
                            <div><span class="text-gray-500">วันที่:</span> <span x-text="detailData.trns_datetime"></span></div>
                            <div><span class="text-gray-500">ประเภท:</span> <span x-text="detailData.trns_type"></span></div>
                            <div><span class="text-gray-500">เคาน์เตอร์:</span> <span x-text="detailData.counter_name"></span></div>
                            <div><span class="text-gray-500">ลูกค้า:</span> <span x-text="detailData.cust_name || '-'"></span></div>
                            <div><span class="text-gray-500">ผู้บันทึก:</span> <span x-text="detailData.created_by || '-'"></span></div>
                        </div>

                        {{-- Passport / Customer Info --}}
                        <template x-if="detailData.customer">
                            <div class="mb-4 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <h4 class="text-xs font-semibold text-gray-600 uppercase mb-3">ข้อมูลลูกค้า / Passport</h4>
                                <div class="flex gap-4">
                                    {{-- Passport Photo --}}
                                    <template x-if="detailData.customer.passport_photo">
                                        <div class="flex-shrink-0">
                                            <a :href="detailData.customer.passport_photo" target="_blank">
                                                <img :src="detailData.customer.passport_photo"
                                                     alt="Passport Photo"
                                                     class="w-32 h-auto rounded-lg border border-gray-300 shadow-sm hover:shadow-md transition-shadow cursor-pointer"
                                                     style="max-height: 180px; object-fit: contain;">
                                            </a>
                                            <p class="text-xs text-blue-500 mt-1 text-center">คลิกเพื่อขยาย</p>
                                        </div>
                                    </template>
                                    {{-- Customer Detail --}}
                                    <div class="flex-1 grid grid-cols-2 gap-2 text-sm">
                                        <div>
                                            <span class="text-gray-500">ประเภทเอกสาร:</span>
                                            <span class="font-medium" x-text="detailData.customer.id_type || '-'"></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">เลขเอกสาร:</span>
                                            <span class="font-mono font-medium" x-text="detailData.customer.id_number || '-'"></span>
                                        </div>
                                        <div>
                                            <span class="text-gray-500">สัญชาติ:</span>
                                            <span class="font-medium" x-text="detailData.customer.nationality || '-'"></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <table class="w-full text-sm border border-gray-200 rounded">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-600">สกุลเงิน</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">จำนวน</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">อัตรา</th>
                                    <th class="px-3 py-2 text-right text-xs font-semibold text-gray-600">รวม (THB)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <template x-for="line in detailData.details" :key="line.currency_code">
                                    <tr class="border-t border-gray-100">
                                        <td class="px-3 py-2" x-text="line.currency_code + ' - ' + line.currency_name"></td>
                                        <td class="px-3 py-2 text-right font-mono" x-text="Number(line.amount).toLocaleString('th-TH', {minimumFractionDigits: 2})"></td>
                                        <td class="px-3 py-2 text-right font-mono" x-text="Number(line.unit_price).toLocaleString('th-TH', {minimumFractionDigits: 4})"></td>
                                        <td class="px-3 py-2 text-right font-mono" x-text="Number(line.total).toLocaleString('th-TH', {minimumFractionDigits: 2})"></td>
                                    </tr>
                                </template>
                            </tbody>
                            <tfoot class="bg-gray-50 font-semibold">
                                <tr class="border-t border-gray-300">
                                    <td colspan="3" class="px-3 py-2 text-right">รวมทั้งสิ้น (Total)</td>
                                    <td class="px-3 py-2 text-right font-mono" x-text="Number(detailData.total_thb).toLocaleString('th-TH', {minimumFractionDigits: 2})"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </template>
            </div>
        </div>
    </div>

    {{-- Cancel Request Modal --}}
    @if ($cancelId)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
             x-data x-init="$refs.cancelInput.focus()">
            <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-semibold text-gray-800">ขอยกเลิกรายการ (Request Cancel)</h3>
                    <button wire:click="dismissCancel" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-600 mb-3">กรุณาระบุเหตุผลในการยกเลิก (Please enter cancellation reason)</p>
                    <textarea wire:model="cancelReason" x-ref="cancelInput" rows="4"
                              class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-red-500 focus:border-red-500"
                              placeholder="ระบุเหตุผล..."></textarea>
                    @error('cancelReason')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                    <div class="flex justify-end gap-2 mt-4">
                        <button wire:click="dismissCancel"
                                class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-300 transition-colors">
                            ยกเลิก (Cancel)
                        </button>
                        <button wire:click="submitCancel"
                                class="bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-red-700 transition-colors">
                            ยืนยันขอยกเลิก (Confirm)
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- Cancel Reason Modal --}}
    <div x-show="showCancelReason" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50"
         @keydown.escape.window="showCancelReason = false">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4" @click.away="showCancelReason = false">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold text-gray-800">เหตุผลในการยกเลิก (Cancel Reason)</h3>
                <button @click="showCancelReason = false" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <div class="p-6">
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-gray-700" x-text="cancelReasonText"></div>
                <div class="flex justify-end mt-4">
                    <button @click="showCancelReason = false"
                            class="bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-300 transition-colors">
                        ปิด (Close)
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- เป้าหมายการพิมพ์ — wire:ignore กัน Livewire morph ทับ src ตอน re-render --}}
    <iframe id="print-iframe" wire:ignore title="print"
            style="position:absolute; width:0; height:0; border:none; overflow:hidden;"></iframe>
</div>
