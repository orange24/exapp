{{--
    ปุ่ม "ขออนุมัติทำรายการต่อ" ต้องแสดงให้ "พนักงาน" เห็น ไม่ใช่เฉพาะผู้มีสิทธิ์อนุมัติ

    flow จริงคือพนักงานเป็นคนกดปุ่มเพื่อเปิดช่องให้ผู้จัดการเดินมาใส่รหัสของตัวเอง
    (supervisor override) ถ้าไปซ่อนปุ่มจากคนที่ไม่มีสิทธิ์ ก็เท่ากับซ่อนจากคนที่ต้องกด
    การบังคับสิทธิ์อยู่ฝั่ง server ตอน submitSanctionApproval() ไม่ใช่ที่การแสดงปุ่ม
--}}
@props(['screening' => null, 'matches' => []])

@if ($screening && $screening['result'] !== 'clear')
    @php
        $blocked = $screening['result'] === 'confirmed_match';
        $barClass = $blocked ? 'bg-red-50 border-red-500' : 'bg-orange-50 border-orange-400';
    @endphp

    <div class="mb-4 border-l-4 rounded {{ $barClass }} p-4" wire:key="sanction-alert">
        <div class="flex items-start gap-2">
            <span class="text-xl leading-none">{{ $blocked ? '🔴' : '⚠' }}</span>
            <div class="flex-1">
                <div class="font-semibold {{ $blocked ? 'text-red-800' : 'text-orange-800' }}">
                    @if ($blocked)
                        พบรายชื่อตรงกันด้วยเลขเอกสาร — ไม่สามารถทำรายการได้
                    @else
                        พบชื่อใกล้เคียงรายชื่อบุคคลที่ถูกกำหนด ({{ count($matches) }} รายการ)
                    @endif
                </div>

                @if ($blocked)
                    <p class="text-sm text-red-700 mt-1">
                        โปรดระงับธุรกรรมและแจ้งผู้จัดการสาขาทันที (ระบบแจ้งส่วนกลางแล้ว)
                    </p>
                @endif

                <div class="mt-3 space-y-3">
                    @foreach ($matches as $i => $m)
                        <div class="bg-white border rounded p-3 text-sm">
                            <div class="flex items-baseline justify-between gap-2">
                                <div class="font-medium">
                                    {{ $i + 1 }}. {{ $m['entry_name_en'] ?: '-' }}
                                    @if (!empty($m['entry_name_th']))
                                        / {{ $m['entry_name_th'] }}
                                    @endif
                                </div>
                                <div class="whitespace-nowrap">
                                    คะแนน <span class="font-bold">{{ number_format((float) $m['score'], 0) }}</span>
                                    {{ $m['severity'] === 'red' ? '🔴' : '🟠' }}
                                </div>
                            </div>

                            <div class="text-xs text-gray-600 mt-1">
                                {{ $m['list_label'] }}
                                @if (!empty($m['section'])) · ม.{{ $m['section'] }} @endif
                                @if (!empty($m['notification_number'])) · ประกาศ {{ $m['notification_number'] }} @endif
                                @if (!empty($m['reference_number'])) · {{ $m['reference_number'] }} @endif
                            </div>

                            <div class="text-xs text-gray-700 mt-1">ตรงที่: {{ $m['matched_on'] }}</div>

                            {{-- เหตุผลของคะแนน — คนอนุมัติต้องรู้ว่าหลักฐานแข็งแค่ไหน
                                 ไม่ใช่เห็นแค่ตัวเลขแล้วเดาเอง --}}
                            @if (!empty($m['why']))
                                <ul class="text-xs text-gray-600 mt-1 list-disc list-inside">
                                    @foreach ($m['why'] as $reason)
                                        <li>{{ $reason }}</li>
                                    @endforeach
                                </ul>
                            @endif

                            {{-- แสดงเทียบกันเป็นคู่ — คนตัดสินใจต้องเห็นว่าต่างกันตรงไหน --}}
                            <div class="grid grid-cols-2 gap-2 mt-2 text-xs">
                                <div class="bg-gray-50 rounded p-2">
                                    <div class="text-gray-500 mb-1">ลูกค้า</div>
                                    <div>{{ $screening['input_name'] ?: '-' }}</div>
                                    <div>เกิด {{ $screening['input_dob'] ?: '-' }}</div>
                                    <div>สัญชาติ {{ $screening['input_nationality'] ?: '-' }}</div>
                                    <div>เอกสาร {{ $screening['input_id_number'] ?: '-' }}</div>
                                </div>
                                <div class="bg-gray-50 rounded p-2">
                                    <div class="text-gray-500 mb-1">รายชื่อต้องห้าม</div>
                                    <div>{{ $m['entry_name_en'] ?: $m['entry_name_th'] ?: '-' }}</div>
                                    <div>เกิด {{ $m['entry_dob'] ?: '-' }}</div>
                                    <div>สัญชาติ {{ $m['entry_nationality'] ?: '-' }}</div>
                                    <div>เอกสาร {{ $m['entry_id_number'] ?: '-' }}</div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-3 flex gap-2">
                    @if (! $blocked)
                        <button type="button" wire:click="openSanctionApproval"
                                class="px-3 py-1.5 rounded text-white text-sm"
                                style="background:#0e513a;">
                            ขออนุมัติทำรายการต่อ
                        </button>
                    @endif
                    <button type="button" wire:click="cancelForSanction"
                            class="px-3 py-1.5 rounded border text-sm">
                        ยกเลิกรายการ
                    </button>
                </div>
            </div>
        </div>
    </div>
@elseif ($screening)
    <div class="mb-2 text-xs text-gray-500">
        ✓ ตรวจรายชื่อแล้ว {{ $screening['screened_at_label'] }}
    </div>
@endif
