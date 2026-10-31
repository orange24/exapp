@extends('layouts.app')

@section('title', 'ส่งข้อมูลให้แบงค์ชาติ (BOT Submission)')

@section('content')
@php
    $thaiMonths = [1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'];
    $groupLabels = [\App\Services\Bot\BotSubmission::THAI => 'คนไทย', \App\Services\Bot\BotSubmission::FOREIGN => 'ชาวต่างชาติ'];
@endphp

<div class="mb-4">
    <h2 class="text-lg font-semibold text-gray-800">ส่งข้อมูลให้แบงค์ชาติ (BOT Submission)</h2>
    <p class="text-sm text-gray-500">
        ออกไฟล์รายงานการซื้อ/ขายเงินตราต่างประเทศรายเดือน ลงใน template ของแบงค์ชาติ — คนไทยกับชาวต่างชาติแยกไฟล์กัน
    </p>
</div>

{{-- Filters --}}
<form method="GET" action="{{ route('reports.bot-submission') }}"
      class="bg-white rounded-lg shadow p-5 mb-4 flex flex-wrap items-end gap-4">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">ประจำงวด (เดือน)</label>
        <select name="month" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            @foreach ($thaiMonths as $m => $label)
                <option value="{{ $m }}" {{ $month === $m ? 'selected' : '' }}>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">ประจำงวด (ปี)</label>
        <select name="year" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            @for ($y = now()->year + 1; $y >= now()->year - 5; $y--)
                <option value="{{ $y }}" {{ $year === $y ? 'selected' : '' }}>{{ $y }}</option>
            @endfor
        </select>
    </div>
    <div class="min-w-[16rem]">
        <label class="block text-xs font-medium text-gray-600 mb-1">สาขา</label>
        <select name="branch_id" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            @forelse ($branches as $b)
                <option value="{{ $b->id }}" {{ $branch?->id === $b->id ? 'selected' : '' }}>
                    {{ $b->branch_code }} — {{ $b->branch_name }}
                </option>
            @empty
                <option value="">ไม่มีสาขาในบริษัทที่เปิดอยู่</option>
            @endforelse
        </select>
    </div>
    <div>
        <span class="block text-xs font-medium text-gray-600 mb-1">กลุ่มลูกค้า</span>
        <div class="flex gap-2">
            @foreach ($groupLabels as $code => $label)
                <label class="flex items-center gap-2 border rounded-lg px-3 py-2 text-sm cursor-pointer
                              {{ $group === $code ? 'border-[#0e513a] bg-green-50 text-[#0e513a] font-semibold' : 'border-gray-300 text-gray-700' }}">
                    <input type="radio" name="group" value="{{ $code }}" {{ $group === $code ? 'checked' : '' }}
                           onchange="this.form.submit()" class="accent-green-700">
                    {{ $label }}
                    @isset($counts[$code])
                        <span class="text-xs font-normal text-gray-500">({{ $counts[$code]['buy'] + $counts[$code]['sell'] }})</span>
                    @endisset
                </label>
            @endforeach
        </div>
    </div>
    <button type="submit" class="bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800">
        แสดงข้อมูล
    </button>
</form>

@if ($submission)
    @php
        $company = $branch->company;
        $buyCount = count($rows['buy']);
        $sellCount = count($rows['sell']);
        $parts = (int) max(ceil($buyCount / \App\Services\Bot\BotSubmission::ROWS_PER_FILE), ceil($sellCount / \App\Services\Bot\BotSubmission::ROWS_PER_FILE), 1);
        $missing = collect([
            'รหัสสถาบันผู้ส่งข้อมูล (เลขนิติบุคคล)' => $company?->tax_id ?: $branch->tax_id,
            'ชื่อบุคคลรับอนุญาต' => $company?->company_name ?: $branch->company_name,
            'License No' => $company?->license_no ?: $branch->license_no,
            'ที่อยู่สถานประกอบการ' => $branch->address ?: $company?->address,
        ])->filter(fn ($v) => blank($v))->keys();
    @endphp

    {{-- Summary + export --}}
    <div class="bg-white rounded-lg shadow p-5 mb-4">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="text-sm text-gray-700 space-y-1">
                <div><span class="text-gray-500">บุคคลรับอนุญาต:</span> {{ $company?->company_name ?: $branch->company_name ?: '—' }}</div>
                <div><span class="text-gray-500">License No:</span> {{ $company?->license_no ?: $branch->license_no ?: '—' }}
                    <span class="text-gray-500 ml-3">รหัสสถาบัน:</span> {{ $company?->tax_id ?: $branch->tax_id ?: '—' }}</div>
                <div><span class="text-gray-500">สถานประกอบการ:</span> {{ $branch->branch_name }} — {{ $branch->address ?: $company?->address ?: '—' }}</div>
                <div><span class="text-gray-500">งวด:</span> {{ $thaiMonths[$month] }} {{ $year }}
                    <span class="text-gray-500 ml-3">ไฟล์:</span>
                    <span class="font-mono">{{ $submission->baseName($group) }}{{ $parts > 1 ? '.zip' : '.xlsx' }}</span></div>
            </div>
            <div class="text-right">
                <div class="text-sm text-gray-600 mb-2">
                    {{ $groupLabels[$group] }}: ซื้อ <b>{{ number_format($buyCount) }}</b> รายการ · ขาย <b>{{ number_format($sellCount) }}</b> รายการ
                </div>
                <form method="POST" action="{{ route('reports.bot-submission.export') }}">
                    @csrf
                    <input type="hidden" name="branch_id" value="{{ $branch->id }}">
                    <input type="hidden" name="year" value="{{ $year }}">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="group" value="{{ $group }}">
                    <button type="submit"
                            class="bg-[#0e513a] text-white px-5 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d]">
                        ดาวน์โหลดไฟล์{{ $groupLabels[$group] }}
                    </button>
                </form>
            </div>
        </div>

        @if ($missing->isNotEmpty())
            <div class="mt-4 p-3 bg-amber-50 border border-amber-300 text-amber-800 rounded text-sm">
                ข้อมูลบริษัท/สาขายังไม่ครบ: {{ $missing->implode(', ') }} — ไปกรอกที่หน้าจัดการบริษัทหรือจัดการสาขา
                (ออกไฟล์ได้ แต่ช่องนั้นในชีต Provider Info จะว่าง)
            </div>
        @endif

        @if ($parts > 1)
            <div class="mt-4 p-3 bg-blue-50 border border-blue-200 text-blue-800 rounded text-sm">
                template รับได้ชีตละ {{ \App\Services\Bot\BotSubmission::ROWS_PER_FILE }} รายการ
                เดือนนี้มีมากกว่านั้น จึงแบ่งเป็น {{ $parts }} ไฟล์ รวมไว้ใน zip เดียว
            </div>
        @endif
    </div>

    {{-- Skipped --}}
    @if (count($submission->skipped()) > 0)
        <div class="bg-white rounded-lg shadow p-5 mb-4 border-l-4 border-amber-400">
            <h3 class="text-sm font-semibold text-amber-800 mb-1">
                รายการที่ไม่ได้ใส่ในไฟล์ ({{ count($submission->skipped()) }} บิล)
            </h3>
            <p class="text-xs text-gray-500 mb-3">
                ข้อมูลลูกค้าไม่ครบจนแบงค์ชาติรับไม่ได้ แก้ข้อมูลลูกค้าของบิลเหล่านี้แล้วออกไฟล์ใหม่
                (รายการนี้รวมทั้งคนไทยและชาวต่างชาติ เพราะบางบิลแยกกลุ่มไม่ได้)
            </p>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="px-3 py-2 text-left">เลขบิล</th>
                            <th class="px-3 py-2 text-left">วันที่</th>
                            <th class="px-3 py-2 text-left">ชื่อลูกค้า</th>
                            <th class="px-3 py-2 text-left">สาเหตุ</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($submission->skipped() as $s)
                            <tr>
                                <td class="px-3 py-1.5 font-mono">{{ $s['trns_no'] }}</td>
                                <td class="px-3 py-1.5">{{ $s['date'] }}</td>
                                <td class="px-3 py-1.5">{{ $s['name'] ?: '—' }}</td>
                                <td class="px-3 py-1.5 text-amber-800">{{ $s['reason'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Preview --}}
    @foreach (['buy' => 'Buy FX — รายการซื้อ', 'sell' => 'Sell FX — รายการขาย'] as $side => $title)
        <div class="bg-white rounded-lg shadow p-5 mb-4">
            <h3 class="text-sm font-semibold text-gray-800 mb-3">{{ $title }} ({{ $groupLabels[$group] }})</h3>
            @if (count($rows[$side]) === 0)
                <p class="text-sm text-gray-400">ไม่มีรายการ</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="px-3 py-2 text-left">วันที่</th>
                                <th class="px-3 py-2 text-left">เลขบิล</th>
                                <th class="px-3 py-2 text-left">ประเภทลูกค้า</th>
                                <th class="px-3 py-2 text-left">ชื่อลูกค้า</th>
                                <th class="px-3 py-2 text-left">ประเภทรหัส</th>
                                <th class="px-3 py-2 text-left">รหัสลูกค้า</th>
                                <th class="px-3 py-2 text-left">สัญชาติ</th>
                                <th class="px-3 py-2 text-left">สกุลเงิน</th>
                                <th class="px-3 py-2 text-right">อัตรา</th>
                                <th class="px-3 py-2 text-right">จำนวนเงินตรา</th>
                                <th class="px-3 py-2 text-right">บาท</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($rows[$side] as $row)
                                <tr>
                                    <td class="px-3 py-1.5 whitespace-nowrap">{{ $row['date']->format('d/m/Y') }}</td>
                                    <td class="px-3 py-1.5 font-mono whitespace-nowrap">{{ $row['trns_no'] }}</td>
                                    <td class="px-3 py-1.5 whitespace-nowrap">{{ $row['customer_type'] }}</td>
                                    <td class="px-3 py-1.5">{{ $row['name'] }}</td>
                                    <td class="px-3 py-1.5 whitespace-nowrap">{{ $row['id_type'] }}</td>
                                    <td class="px-3 py-1.5 font-mono">{{ $row['id_number'] }}</td>
                                    <td class="px-3 py-1.5">{{ $row['country'] ?? 'TH' }}</td>
                                    <td class="px-3 py-1.5">{{ $row['currency'] }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ number_format($row['rate'], 6) }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ number_format($row['fc_amount'], 2) }}</td>
                                    <td class="px-3 py-1.5 text-right">{{ number_format($row['thb_amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endforeach
@endif
@endsection
