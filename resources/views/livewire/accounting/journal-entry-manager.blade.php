<div>
    @if (session('success'))
        <div class="mb-4 rounded-lg bg-green-50 border border-green-200 p-4 text-green-800 text-sm">{{ session('success') }}</div>
    @endif

    {{-- Detail Modal --}}
    @if ($showDetail && $this->detailEntry)
    <div style="position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.5); display:flex; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:12px; padding:24px; width:100%; max-width:700px; max-height:80vh; overflow-y:auto;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-lg font-bold" style="color:#0e513a;">รายละเอียด {{ $this->detailEntry->entry_no }}</h3>
                <button wire:click="closeDetail" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>

            <div class="grid grid-cols-2 gap-4 mb-4 text-sm">
                <div><span class="text-gray-500">วันที่:</span> <strong>{{ $this->detailEntry->entry_date?->format('d/m/Y') }}</strong></div>
                <div><span class="text-gray-500">สาขา:</span> <strong>{{ $this->detailEntry->branch?->branch_name ?? '-' }}</strong></div>
                <div><span class="text-gray-500">ประเภท:</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $this->detailEntry->type === 'auto' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                        {{ $this->detailEntry->type === 'auto' ? 'อัตโนมัติ' : 'บันทึกเอง' }}
                    </span>
                </div>
                <div><span class="text-gray-500">สถานะ:</span>
                    @if ($this->detailEntry->is_posted)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">ผ่านรายการแล้ว</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">ยังไม่ผ่านรายการ</span>
                    @endif
                </div>
            </div>

            <div class="mb-4 text-sm"><span class="text-gray-500">คำอธิบาย:</span> {{ $this->detailEntry->description }}</div>

            <table class="w-full text-sm border">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="px-3 py-2 text-left border-b">รหัสบัญชี</th>
                        <th class="px-3 py-2 text-left border-b">ชื่อบัญชี</th>
                        <th class="px-3 py-2 text-right border-b">เดบิต</th>
                        <th class="px-3 py-2 text-right border-b">เครดิต</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalDr = 0; $totalCr = 0; @endphp
                    @foreach ($this->detailEntry->lines as $line)
                        @php $totalDr += (float)$line->debit; $totalCr += (float)$line->credit; @endphp
                        <tr class="border-b">
                            <td class="px-3 py-2 font-mono text-xs">{{ $line->account?->account_code ?? '-' }}</td>
                            <td class="px-3 py-2">{{ $line->account?->name_th ?? '-' }}</td>
                            <td class="px-3 py-2 text-right {{ $line->debit > 0 ? 'text-blue-700 font-medium' : 'text-gray-300' }}">{{ number_format($line->debit, 2) }}</td>
                            <td class="px-3 py-2 text-right {{ $line->credit > 0 ? 'text-red-700 font-medium' : 'text-gray-300' }}">{{ number_format($line->credit, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-bold">
                        <td colspan="2" class="px-3 py-2 text-right">รวม</td>
                        <td class="px-3 py-2 text-right text-blue-700">{{ number_format($totalDr, 2) }}</td>
                        <td class="px-3 py-2 text-right text-red-700">{{ number_format($totalCr, 2) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <div></div>
        <button wire:click="$toggle('showForm')" class="px-4 py-2 text-sm font-semibold text-white rounded-lg" style="background:#0e513a;">
            {{ $showForm ? '✕ ปิดฟอร์ม' : '+ บันทึกรายการ' }}
        </button>
    </div>

    {{-- Create Form --}}
    @if ($showForm)
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-lg font-bold mb-4" style="color:#0e513a;">บันทึกรายการบัญชี (Journal Entry)</h3>
        <form wire:submit="save">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">วันที่</label>
                    <input type="date" wire:model.blur="entryDate" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">สาขา</label>
                    <select wire:model.blur="branchId" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <option value="">-- เลือกสาขา --</option>
                        @foreach ($this->branches as $b)
                            <option value="{{ $b->id }}">{{ $b->branch_name }}</option>
                        @endforeach
                    </select>
                    @error('branchId') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">คำอธิบาย</label>
                    <input type="text" wire:model.blur="description" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm" placeholder="คำอธิบายรายการ">
                    @error('description') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
            </div>

            {{-- Lines --}}
            <div class="border rounded-lg overflow-hidden mb-4">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50">
                            <th class="px-3 py-2 text-left font-medium text-gray-700" style="width:35%;">บัญชี</th>
                            <th class="px-3 py-2 text-left font-medium text-gray-700" style="width:20%;">คำอธิบาย</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-700" style="width:18%;">เดบิต</th>
                            <th class="px-3 py-2 text-right font-medium text-gray-700" style="width:18%;">เครดิต</th>
                            <th class="px-3 py-2 text-center font-medium text-gray-700" style="width:9%;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($lines as $i => $line)
                        <tr class="border-t">
                            <td class="px-2 py-1">
                                <select wire:model.blur="lines.{{ $i }}.account_id" class="w-full border border-gray-300 rounded px-2 py-1 text-sm">
                                    <option value="">-- เลือกบัญชี --</option>
                                    @foreach ($this->accounts as $acc)
                                        <option value="{{ $acc->id }}">{{ $acc->account_code }} {{ $acc->name_th }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td class="px-2 py-1">
                                <input type="text" wire:model.blur="lines.{{ $i }}.description" class="w-full border border-gray-300 rounded px-2 py-1 text-sm" placeholder="รายละเอียด">
                            </td>
                            <td class="px-2 py-1">
                                <input type="number" step="0.01" wire:model.blur="lines.{{ $i }}.debit" class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-right" placeholder="0.00">
                            </td>
                            <td class="px-2 py-1">
                                <input type="number" step="0.01" wire:model.blur="lines.{{ $i }}.credit" class="w-full border border-gray-300 rounded px-2 py-1 text-sm text-right" placeholder="0.00">
                            </td>
                            <td class="px-2 py-1 text-center">
                                @if (count($lines) > 2)
                                    <button type="button" wire:click="removeLine({{ $i }})" class="text-red-400 hover:text-red-600 text-lg">&times;</button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="border-t bg-gray-50">
                            <td colspan="2" class="px-3 py-2">
                                <button type="button" wire:click="addLine" class="text-sm text-blue-600 hover:underline">+ เพิ่มบรรทัด</button>
                            </td>
                            <td class="px-3 py-2 text-right font-bold text-blue-700">
                                @php $td = collect($lines)->sum(fn($l) => (float)($l['debit'] ?: 0)); @endphp
                                {{ number_format($td, 2) }}
                            </td>
                            <td class="px-3 py-2 text-right font-bold text-red-700">
                                @php $tc = collect($lines)->sum(fn($l) => (float)($l['credit'] ?: 0)); @endphp
                                {{ number_format($tc, 2) }}
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            @error('lines') <div class="mb-3 text-red-500 text-sm font-medium">{{ $message }}</div> @enderror

            @php $diff = abs($td - $tc); @endphp
            @if ($td > 0 && $diff > 0.01)
                <div class="mb-3 p-3 rounded-lg bg-yellow-50 border border-yellow-200 text-yellow-800 text-sm">
                    ยอดเดบิต-เครดิตต่างกัน: {{ number_format($diff, 2) }} — ต้องเท่ากันจึงจะบันทึกได้
                </div>
            @endif

            <div class="flex justify-end gap-2">
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
                <label class="block text-sm font-medium text-gray-700 mb-1">ตั้งแต่</label>
                <input type="date" wire:model.live="filterDateFrom" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ถึง</label>
                <input type="date" wire:model.live="filterDateTo" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ประเภท</label>
                <select wire:model.live="filterType" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">ทั้งหมด</option>
                    <option value="manual">บันทึกเอง</option>
                    <option value="auto">อัตโนมัติ</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">สถานะ</label>
                <select wire:model.live="filterPosted" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">ทั้งหมด</option>
                    <option value="1">ผ่านรายการแล้ว</option>
                    <option value="0">ยังไม่ผ่าน</option>
                </select>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr style="background:#0e513a;" class="text-white">
                        <th class="px-4 py-3 text-left font-semibold">เลขที่</th>
                        <th class="px-4 py-3 text-left font-semibold">วันที่</th>
                        <th class="px-4 py-3 text-left font-semibold">คำอธิบาย</th>
                        <th class="px-4 py-3 text-left font-semibold">สาขา</th>
                        <th class="px-4 py-3 text-center font-semibold">ประเภท</th>
                        <th class="px-4 py-3 text-right font-semibold">เดบิต</th>
                        <th class="px-4 py-3 text-right font-semibold">เครดิต</th>
                        <th class="px-4 py-3 text-center font-semibold">สถานะ</th>
                        <th class="px-4 py-3 text-center font-semibold">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->entries as $idx => $entry)
                        <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }} hover:bg-blue-50">
                            <td class="px-4 py-2 font-mono text-xs">{{ $entry->entry_no }}</td>
                            <td class="px-4 py-2">{{ $entry->entry_date?->format('d/m/Y') }}</td>
                            <td class="px-4 py-2 text-gray-700 max-w-xs truncate">{{ $entry->description }}</td>
                            <td class="px-4 py-2 text-xs">{{ $entry->branch?->branch_name ?? '-' }}</td>
                            <td class="px-4 py-2 text-center">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $entry->type === 'auto' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                    {{ $entry->type === 'auto' ? 'อัตโนมัติ' : 'บันทึกเอง' }}
                                </span>
                            </td>
                            <td class="px-4 py-2 text-right text-blue-700">{{ number_format($entry->lines->sum('debit'), 2) }}</td>
                            <td class="px-4 py-2 text-right text-red-700">{{ number_format($entry->lines->sum('credit'), 2) }}</td>
                            <td class="px-4 py-2 text-center">
                                @if ($entry->is_posted)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">ผ่านแล้ว</span>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800">รอผ่าน</span>
                                @endif
                            </td>
                            <td class="px-4 py-2 text-center">
                                <div class="flex gap-1 justify-center">
                                    <button wire:click="viewDetail({{ $entry->id }})" class="px-2 py-1 text-xs text-blue-600 bg-blue-50 rounded hover:bg-blue-100">ดู</button>
                                    @if (!$entry->is_posted && Auth::user()->isAdmin())
                                        <button wire:click="postEntry({{ $entry->id }})" wire:confirm="ยืนยันผ่านรายการนี้?"
                                                class="px-2 py-1 text-xs text-white bg-green-600 rounded hover:bg-green-700">ผ่าน</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">ไม่มีรายการบัญชี</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($this->entries->hasPages())
        <div class="p-4 border-t border-gray-200">{{ $this->entries->links() }}</div>
        @endif
    </div>
</div>
