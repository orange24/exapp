<div>
    @if (session('message'))
        <div class="mb-3 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
            {{ session('message') }}
        </div>
    @endif

    <div class="flex flex-wrap gap-2 items-end mb-3 text-sm">
        <label>สาขา
            <select wire:model.live="branchFilter" class="border rounded px-2 py-1">
                <option value="">ทั้งหมด</option>
                @foreach ($this->branches as $b)
                    <option value="{{ $b->id }}">{{ $b->branch_name }}</option>
                @endforeach
            </select>
        </label>

        <label>บัญชี
            <select wire:model.live="listFilter" class="border rounded px-2 py-1">
                <option value="">ทั้งหมด</option>
                <option value="{{ \App\Models\SanctionEntry::LIST_FREEZE_05_TH }}">FREEZE-05 Thailand</option>
                <option value="{{ \App\Models\SanctionEntry::LIST_FREEZE_04_UN }}">FREEZE-04 UN</option>
            </select>
        </label>

        <label>คะแนนขั้นต่ำ
            <input type="number" wire:model.live="minScore" class="border rounded px-2 py-1 w-20">
        </label>
    </div>

    <div class="bg-white border rounded overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-2 w-8"></th>
                    <th class="p-2 text-left">คะแนน</th>
                    <th class="p-2 text-left">ลูกค้า</th>
                    <th class="p-2 text-left">รายชื่อที่ตรง</th>
                    <th class="p-2 text-left">สาขา</th>
                    <th class="p-2 text-left">ตรวจเมื่อ</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($this->rows as $row)
                    <tr>
                        <td class="p-2 align-top">
                            <input type="checkbox" wire:model.live="selected" value="{{ $row['id'] }}">
                        </td>
                        <td class="p-2 align-top font-bold">{{ number_format($row['top_score'], 0) }}</td>
                        <td class="p-2 align-top">
                            <div>{{ $row['input_name'] ?: '-' }}</div>
                            <div class="text-xs text-gray-500">
                                {{ $row['input_id_number'] ?: '-' }} ·
                                เกิด {{ $row['input_dob'] ?: '-' }} ·
                                {{ $row['input_nationality'] ?: '-' }}
                            </div>
                        </td>
                        <td class="p-2 align-top">
                            @foreach ($row['matches'] as $m)
                                <div class="mb-1">
                                    <div>{{ $m['name'] }}
                                        <span class="text-xs text-gray-500">({{ number_format($m['score'], 0) }})</span>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        {{ $m['list_code'] }} · เกิด {{ $m['dob'] ?: '-' }} ·
                                        {{ $m['nationality'] ?: '-' }} · {{ $m['matched_on'] }}
                                    </div>
                                </div>
                            @endforeach
                        </td>
                        <td class="p-2 align-top">{{ $row['branch_name'] ?: '-' }}</td>
                        <td class="p-2 align-top whitespace-nowrap">
                            {{ $row['screened_at'] }}
                            <div class="text-xs text-gray-500">{{ $row['trigger'] }}</div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">ไม่มีรายการรอตรวจสอบ</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3 bg-white border rounded p-3">
        <div class="text-sm mb-2">เลือกแล้ว {{ count($selected) }} รายการ</div>

        <textarea wire:model="bulkReason" rows="2" class="w-full border rounded px-2 py-1 text-sm"
                  placeholder="เหตุผล (บังคับ อย่างน้อย 20 ตัวอักษร) — ใช้กับทุกรายการที่เลือก"></textarea>
        @error('bulkReason') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror

        <div class="flex gap-2 mt-2">
            <button type="button" wire:click="bulkDecide('{{ \App\Models\SanctionScreening::DECISION_FALSE_POSITIVE }}')"
                    class="px-3 py-1.5 rounded text-white text-sm" style="background:#0e513a;">
                ไม่ใช่บุคคลเดียวกัน (เคลียร์)
            </button>
            <button type="button" wire:click="bulkDecide('{{ \App\Models\SanctionScreening::DECISION_TRUE_MATCH }}')"
                    class="px-3 py-1.5 rounded bg-red-600 text-white text-sm">
                ยืนยันว่าเป็นบุคคลเดียวกัน
            </button>
            <button type="button" wire:click="bulkDecide('{{ \App\Models\SanctionScreening::DECISION_ESCALATED }}')"
                    class="px-3 py-1.5 rounded border text-sm">
                ส่งต่อให้ผู้บริหารตัดสิน
            </button>
        </div>
    </div>
</div>
