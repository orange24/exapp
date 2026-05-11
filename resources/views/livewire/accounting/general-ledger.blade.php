<div>
    {{-- Controls --}}
    <div class="bg-white rounded-lg shadow p-4 mb-6">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">มุมมอง</label>
                <select wire:model.live="viewMode" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="trial_balance">งบทดลอง (Trial Balance)</option>
                    <option value="ledger">แยกประเภท (Ledger)</option>
                </select>
            </div>
            @if ($viewMode === 'ledger')
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">บัญชี</label>
                <select wire:model.live="accountId" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">-- เลือกบัญชี --</option>
                    @foreach ($this->accounts as $acc)
                        <option value="{{ $acc->id }}">{{ $acc->account_code }} {{ $acc->name_th }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ตั้งแต่</label>
                <input type="date" wire:model.live="dateFrom" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ถึง</label>
                <input type="date" wire:model.live="dateTo" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
        </div>
    </div>

    {{-- Trial Balance --}}
    @if ($viewMode === 'trial_balance')
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr style="background:#0e513a;" class="text-white">
                        <th class="px-4 py-3 text-left font-semibold">รหัสบัญชี</th>
                        <th class="px-4 py-3 text-left font-semibold">ชื่อบัญชี</th>
                        <th class="px-4 py-3 text-center font-semibold">ประเภท</th>
                        <th class="px-4 py-3 text-right font-semibold">เดบิต</th>
                        <th class="px-4 py-3 text-right font-semibold">เครดิต</th>
                        <th class="px-4 py-3 text-right font-semibold">ยอดคงเหลือ</th>
                    </tr>
                </thead>
                <tbody>
                    @php $sumDr = 0; $sumCr = 0; @endphp
                    @forelse ($this->trialBalance as $idx => $row)
                        @php $sumDr += (float)$row->total_debit; $sumCr += (float)$row->total_credit; @endphp
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ $row->account_code }}</td>
                            <td class="px-4 py-2">{{ $row->name_th }}</td>
                            <td class="px-4 py-2 text-center">
                                @php
                                    $typeLabels = ['asset' => 'สินทรัพย์', 'liability' => 'หนี้สิน', 'equity' => 'ทุน', 'revenue' => 'รายได้', 'expense' => 'ค่าใช้จ่าย'];
                                    $typeColors = ['asset' => 'bg-blue-100 text-blue-800', 'liability' => 'bg-pink-100 text-pink-800', 'equity' => 'bg-purple-100 text-purple-800', 'revenue' => 'bg-green-100 text-green-800', 'expense' => 'bg-orange-100 text-orange-800'];
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $typeColors[$row->type] ?? 'bg-gray-100' }}">
                                    {{ $typeLabels[$row->type] ?? $row->type }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right text-blue-700">{{ number_format($row->total_debit, 2) }}</td>
                            <td class="px-4 py-2 text-right text-red-700">{{ number_format($row->total_credit, 2) }}</td>
                            <td class="px-4 py-2 text-right font-medium {{ $row->balance >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                                {{ number_format(abs($row->balance), 2) }} {{ $row->balance < 0 ? 'Cr' : 'Dr' }}
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">ไม่มีข้อมูล</td></tr>
                    @endforelse
                </tbody>
                @if ($this->trialBalance->isNotEmpty())
                <tfoot>
                    <tr class="bg-gray-100 font-bold border-t-2">
                        <td colspan="3" class="px-4 py-3 text-right">รวม</td>
                        <td class="px-4 py-3 text-right text-blue-700">{{ number_format($sumDr, 2) }}</td>
                        <td class="px-4 py-3 text-right text-red-700">{{ number_format($sumCr, 2) }}</td>
                        <td class="px-4 py-3 text-right {{ abs($sumDr - $sumCr) < 0.01 ? 'text-green-600' : 'text-red-600' }}">
                            {{ abs($sumDr - $sumCr) < 0.01 ? 'สมดุล' : 'ไม่สมดุล: ' . number_format(abs($sumDr - $sumCr), 2) }}
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
    @endif

    {{-- Account Ledger --}}
    @if ($viewMode === 'ledger')
        @if (!$accountId)
            <div class="bg-white rounded-lg shadow p-8 text-center text-gray-400">กรุณาเลือกบัญชีที่ต้องการดู</div>
        @else
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr style="background:#0e513a;" class="text-white">
                                <th class="px-4 py-3 text-left font-semibold">วันที่</th>
                                <th class="px-4 py-3 text-left font-semibold">เลขที่</th>
                                <th class="px-4 py-3 text-left font-semibold">คำอธิบาย</th>
                                <th class="px-4 py-3 text-right font-semibold">เดบิต</th>
                                <th class="px-4 py-3 text-right font-semibold">เครดิต</th>
                                <th class="px-4 py-3 text-right font-semibold">ยอดสะสม</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $running = 0; @endphp
                            @forelse ($this->ledgerEntries as $idx => $le)
                                @php $running += (float)$le->debit - (float)$le->credit; @endphp
                                <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                                    <td class="px-4 py-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($le->entry_date)->format('d/m/Y') }}</td>
                                    <td class="px-4 py-2 font-mono text-xs">{{ $le->entry_no }}</td>
                                    <td class="px-4 py-2 text-gray-700">{{ $le->line_desc ?: $le->entry_desc }}</td>
                                    <td class="px-4 py-2 text-right {{ $le->debit > 0 ? 'text-blue-700' : 'text-gray-300' }}">{{ number_format($le->debit, 2) }}</td>
                                    <td class="px-4 py-2 text-right {{ $le->credit > 0 ? 'text-red-700' : 'text-gray-300' }}">{{ number_format($le->credit, 2) }}</td>
                                    <td class="px-4 py-2 text-right font-medium {{ $running >= 0 ? 'text-blue-700' : 'text-red-700' }}">
                                        {{ number_format(abs($running), 2) }} {{ $running < 0 ? 'Cr' : 'Dr' }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">ไม่มีรายการ</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
