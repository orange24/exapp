<div>
    {{-- Flash Messages --}}
    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4 text-green-800 text-sm">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 p-4 text-red-800 text-sm">{{ session('error') }}</div>
    @endif

    {{-- Header: Pull Rates --}}
    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h3 class="text-lg font-bold" style="color:#0e513a;">ดึงราคาอ้างอิง SuperRich</h3>
                <p class="text-sm text-gray-500 mt-1">
                    @if ($this->lastFetch)
                        ดึงล่าสุด: <span class="font-medium text-gray-700">{{ $this->lastFetch }}</span>
                    @else
                        ยังไม่เคยดึงราคา
                    @endif
                </p>
            </div>
            <div class="flex gap-2">
                <button wire:click="pullRates" wire:loading.attr="disabled"
                        class="px-5 py-2.5 text-sm font-semibold text-white rounded-lg disabled:opacity-60"
                        style="background:#0e513a;">
                    <span wire:loading.remove wire:target="pullRates">ดึงราคา SuperRich</span>
                    <span wire:loading wire:target="pullRates">กำลังดึง...</span>
                </button>
                <button wire:click="$toggle('showAdjustments')"
                        class="px-4 py-2.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                    {{ $showAdjustments ? 'ปิดตั้งค่า' : 'ตั้งค่า Adjustment' }}
                </button>
            </div>
        </div>
    </div>

    {{-- Adjustment Settings Panel --}}
    @if ($showAdjustments)
    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <h3 class="text-base font-bold mb-4" style="color:#0e513a;">ค่าปรับ (Adjustment) ต่อ Denomination</h3>
        <p class="text-xs text-gray-500 mb-3">ค่า + เพิ่มราคา, ค่า - ลดราคา จากราคา SuperRich เช่น SuperRich Buy 32.59 + Adj -0.10 = Final 32.49</p>

        <div class="overflow-x-auto" style="max-height:500px; overflow-y:auto;">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0" style="background:#0e513a;">
                    <tr class="text-white">
                        <th class="px-3 py-2 text-left font-semibold">ธง</th>
                        <th class="px-3 py-2 text-left font-semibold">สกุลเงิน</th>
                        <th class="px-3 py-2 text-left font-semibold">Denomination</th>
                        <th class="px-3 py-2 text-center font-semibold">ปรับ Buy</th>
                        <th class="px-3 py-2 text-center font-semibold">ปรับ Sell</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->denominations as $idx => $d)
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                            <td class="px-3 py-1.5">
                                <img src="{{ asset('images/flags/' . strtolower($d->currency_code) . '.png') }}"
                                     class="w-6 h-auto" onerror="this.style.display='none'">
                            </td>
                            <td class="px-3 py-1.5 font-medium">{{ $d->currency_code }}</td>
                            <td class="px-3 py-1.5 text-gray-600">{{ $d->denom_label }}</td>
                            <td class="px-3 py-1.5 text-center">
                                <input type="number" step="0.000001"
                                       wire:model.blur="adjustments.{{ $d->id }}.adj_rate_buy"
                                       class="w-24 border border-gray-300 rounded px-2 py-1 text-xs text-right font-mono
                                              {{ (float)($adjustments[$d->id]['adj_rate_buy'] ?? 0) > 0 ? 'text-green-700' : ((float)($adjustments[$d->id]['adj_rate_buy'] ?? 0) < 0 ? 'text-red-700' : '') }}">
                            </td>
                            <td class="px-3 py-1.5 text-center">
                                <input type="number" step="0.000001"
                                       wire:model.blur="adjustments.{{ $d->id }}.adj_rate_sell"
                                       class="w-24 border border-gray-300 rounded px-2 py-1 text-xs text-right font-mono
                                              {{ (float)($adjustments[$d->id]['adj_rate_sell'] ?? 0) > 0 ? 'text-green-700' : ((float)($adjustments[$d->id]['adj_rate_sell'] ?? 0) < 0 ? 'text-red-700' : '') }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4 flex justify-end">
            <button wire:click="saveAdjustments"
                    class="px-5 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">
                บันทึกค่าปรับ
            </button>
        </div>
    </div>
    @endif

    {{-- Latest SuperRich Rates Preview --}}
    @if ($this->latestRates->isNotEmpty())
    <div class="bg-white rounded-lg shadow mb-6 overflow-hidden">
        <div class="px-5 py-3 border-b" style="background:#f8f9fa;">
            <h3 class="text-base font-bold" style="color:#0e513a;">ราคา SuperRich ล่าสุด + Adjustment = ราคาสุดท้าย</h3>
        </div>
        <div class="overflow-x-auto" style="max-height:400px; overflow-y:auto;">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0" style="background:#0e513a;">
                    <tr class="text-white">
                        <th class="px-3 py-2 text-left font-semibold">ธง</th>
                        <th class="px-3 py-2 text-left font-semibold">สกุลเงิน</th>
                        <th class="px-3 py-2 text-left font-semibold">SR Denom</th>
                        <th class="px-3 py-2 text-right font-semibold">SR Buy</th>
                        <th class="px-3 py-2 text-right font-semibold">SR Sell</th>
                        <th class="px-3 py-2 text-right font-semibold">Adj Buy</th>
                        <th class="px-3 py-2 text-right font-semibold">Adj Sell</th>
                        <th class="px-3 py-2 text-right font-semibold" style="background:#0a3d2d;">Final Buy</th>
                        <th class="px-3 py-2 text-right font-semibold" style="background:#7f1d1d;">Final Sell</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($this->latestRates as $idx => $sr)
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                            <td class="px-3 py-1.5">
                                <img src="{{ asset('images/flags/' . strtolower($sr->currency_code) . '.png') }}"
                                     class="w-6 h-auto" onerror="this.style.display='none'">
                            </td>
                            <td class="px-3 py-1.5 font-medium">{{ $sr->currency_code }}</td>
                            <td class="px-3 py-1.5 text-gray-500 text-xs">{{ $sr->superrich_denom }} → {{ $sr->denomination?->denom_label ?? '?' }}</td>
                            <td class="px-3 py-1.5 text-right font-mono">{{ number_format($sr->rate_buy, 4) }}</td>
                            <td class="px-3 py-1.5 text-right font-mono">{{ number_format($sr->rate_sell, 4) }}</td>
                            <td class="px-3 py-1.5 text-right font-mono text-xs {{ $sr->adj_buy != 0 ? ($sr->adj_buy > 0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                                {{ $sr->adj_buy >= 0 ? '+' : '' }}{{ number_format($sr->adj_buy, 4) }}
                            </td>
                            <td class="px-3 py-1.5 text-right font-mono text-xs {{ $sr->adj_sell != 0 ? ($sr->adj_sell > 0 ? 'text-green-600' : 'text-red-600') : 'text-gray-400' }}">
                                {{ $sr->adj_sell >= 0 ? '+' : '' }}{{ number_format($sr->adj_sell, 4) }}
                            </td>
                            <td class="px-3 py-1.5 text-right font-mono font-bold text-green-800" style="background:#ecfdf5;">
                                {{ number_format($sr->final_buy, 4) }}
                            </td>
                            <td class="px-3 py-1.5 text-right font-mono font-bold text-red-800" style="background:#fef2f2;">
                                {{ number_format($sr->final_sell, 4) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Target Counters + Create Batch --}}
    <div class="bg-white rounded-lg shadow p-5 mb-6">
        <h3 class="text-base font-bold mb-3" style="color:#0e513a;">เลือกเคาน์เตอร์เป้าหมาย</h3>
        <div class="mb-3">
            <button wire:click="toggleSelectAllCounters" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                {{ count($selectedCounterIds) === $this->counters->count() ? 'ยกเลิกทั้งหมด' : 'เลือกทั้งหมด' }}
            </button>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2 mb-4">
            @foreach ($this->counters as $c)
                <label class="flex items-center gap-2 text-sm p-2 rounded hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" wire:model.live="selectedCounterIds" value="{{ $c->id }}" class="w-4 h-4">
                    <span>{{ $c->counter_name }}</span>
                    <span class="text-xs text-gray-400">({{ $c->branch->branch_name ?? '' }})</span>
                </label>
            @endforeach
        </div>
        <div class="flex justify-end">
            <button wire:click="createBatch" wire:loading.attr="disabled"
                    wire:confirm="สร้างชุดราคาจาก SuperRich + Adjustment? (ต้องอนุมัติก่อนกระจายไปสาขา)"
                    class="px-5 py-2.5 text-sm font-semibold text-white rounded-lg disabled:opacity-60"
                    style="background:#0e513a;">
                <span wire:loading.remove wire:target="createBatch">สร้างชุดราคา (Create Batch)</span>
                <span wire:loading wire:target="createBatch">กำลังสร้าง...</span>
            </button>
        </div>
    </div>
    @endif

    {{-- Batches Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-5 py-3 border-b" style="background:#f8f9fa;">
            <h3 class="text-base font-bold" style="color:#0e513a;">ชุดราคา (Rate Batches)</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr style="background:#0e513a;" class="text-white">
                        <th class="px-4 py-2.5 text-left font-semibold">#</th>
                        <th class="px-4 py-2.5 text-center font-semibold">สถานะ</th>
                        <th class="px-4 py-2.5 text-center font-semibold">จำนวน</th>
                        <th class="px-4 py-2.5 text-center font-semibold">เคาน์เตอร์</th>
                        <th class="px-4 py-2.5 text-left font-semibold">สร้างโดย</th>
                        <th class="px-4 py-2.5 text-left font-semibold">สร้างเมื่อ</th>
                        <th class="px-4 py-2.5 text-left font-semibold">ตรวจสอบโดย</th>
                        <th class="px-4 py-2.5 text-left font-semibold">หมายเหตุ</th>
                        <th class="px-4 py-2.5 text-center font-semibold">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->batches as $idx => $batch)
                        @php
                            $statusBadge = match($batch->status) {
                                'pending' => 'bg-yellow-100 text-yellow-800',
                                'approved' => 'bg-green-100 text-green-800',
                                'rejected' => 'bg-red-100 text-red-800',
                                'distributed' => 'bg-blue-100 text-blue-800',
                                default => 'bg-gray-100 text-gray-800',
                            };
                            $statusLabel = match($batch->status) {
                                'pending' => 'รออนุมัติ',
                                'approved' => 'อนุมัติแล้ว',
                                'rejected' => 'ปฏิเสธ',
                                'distributed' => 'กระจายแล้ว',
                                default => $batch->status,
                            };
                            $ratesCount = is_array($batch->rates_data) ? count($batch->rates_data) : 0;
                            $counterCount = is_array($batch->target_counter_ids) ? count($batch->target_counter_ids) : 0;
                        @endphp
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ $batch->id }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $statusBadge }}">
                                    {{ $statusLabel }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-center text-xs">{{ $ratesCount }} สกุลเงิน</td>
                            <td class="px-4 py-2 text-center text-xs">{{ $counterCount }} เคาน์เตอร์</td>
                            <td class="px-4 py-2 text-xs">{{ $batch->createdByUser->name ?? '—' }}</td>
                            <td class="px-4 py-2 text-xs whitespace-nowrap">{{ $batch->created_at?->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-2 text-xs">
                                {{ $batch->reviewedByUser->name ?? '—' }}
                                @if ($batch->reviewed_at)
                                    <span class="text-gray-400">{{ $batch->reviewed_at->format('H:i') }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-xs text-gray-500">{{ $batch->notes ?? '' }}</td>
                            <td class="px-4 py-2 text-center">
                                <div class="flex items-center justify-center gap-1">
                                    @if ($batch->status === 'pending')
                                        <button wire:click="approveBatch({{ $batch->id }})"
                                                wire:confirm="อนุมัติชุดราคา #{{ $batch->id }}?"
                                                class="px-2 py-1 text-xs font-medium text-green-700 bg-green-50 rounded hover:bg-green-100">
                                            อนุมัติ
                                        </button>
                                        <button wire:click="rejectBatch({{ $batch->id }})"
                                                wire:confirm="ปฏิเสธชุดราคา #{{ $batch->id }}?"
                                                class="px-2 py-1 text-xs font-medium text-red-700 bg-red-50 rounded hover:bg-red-100">
                                            ปฏิเสธ
                                        </button>
                                    @elseif ($batch->status === 'approved')
                                        <button wire:click="distributeBatch({{ $batch->id }})"
                                                wire:confirm="กระจายราคาไปยังเคาน์เตอร์ทั้งหมด?"
                                                class="px-2 py-1 text-xs font-medium text-white rounded" style="background:#0e513a;">
                                            กระจายราคา
                                        </button>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-400">ยังไม่มีชุดราคา</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
