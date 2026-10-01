@extends('layouts.app')

@section('title', 'บันทึกการตรวจรายชื่อ')

@section('content')
    <form method="GET" class="flex flex-wrap gap-2 items-end mb-3 text-sm">
        <label>ตั้งแต่ <input type="date" name="date_from" value="{{ $dateFrom }}" class="border rounded px-2 py-1"></label>
        <label>ถึง <input type="date" name="date_to" value="{{ $dateTo }}" class="border rounded px-2 py-1"></label>
        <label>ผล
            <select name="result" class="border rounded px-2 py-1">
                <option value="">ทั้งหมด</option>
                <option value="clear" @selected($result === 'clear')>ไม่พบ</option>
                <option value="potential_match" @selected($result === 'potential_match')>พบชื่อใกล้เคียง</option>
                <option value="confirmed_match" @selected($result === 'confirmed_match')>พบตรงกัน (ระงับ)</option>
            </select>
        </label>
        <button class="px-3 py-1.5 rounded text-white" style="background:#0e513a;">ค้นหา</button>
    </form>

    <form method="POST" action="{{ route('reports.sanction-screening-log.export') }}" class="mb-3">
        @csrf
        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
        <input type="hidden" name="date_to" value="{{ $dateTo }}">
        <input type="hidden" name="result" value="{{ $result }}">
        <button class="px-3 py-1.5 rounded border text-sm bg-white">ดาวน์โหลด Excel</button>
    </form>

    <div class="bg-white border rounded overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="p-2 text-left">วันเวลา</th>
                    <th class="p-2 text-left">ธุรกรรม</th>
                    <th class="p-2 text-left">สาขา</th>
                    <th class="p-2 text-left">ผู้ตรวจ</th>
                    <th class="p-2 text-left">ชื่อที่ตรวจ</th>
                    <th class="p-2 text-left">ผล</th>
                    <th class="p-2 text-right">คะแนน</th>
                    <th class="p-2 text-left">As Of</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse ($data as $d)
                    <tr>
                        <td class="p-2 whitespace-nowrap">{{ $d->screened_at?->format('d/m/Y H:i') }}</td>
                        <td class="p-2">{{ $d->transaction?->trns_no ?? '-' }}</td>
                        <td class="p-2">{{ $d->branch?->branch_name ?? '-' }}</td>
                        <td class="p-2">{{ $d->screenedBy?->name ?? 'ระบบ' }}</td>
                        <td class="p-2">{{ $d->input_name ?? '(ไม่มีข้อมูลลูกค้า)' }}</td>
                        <td class="p-2">
                            @if ($d->result === 'confirmed_match') 🔴 ระงับ
                            @elseif ($d->result === 'potential_match') 🟠 ใกล้เคียง
                            @else ✓ ไม่พบ @endif
                        </td>
                        <td class="p-2 text-right">{{ number_format((float) $d->top_score, 0) }}</td>
                        <td class="p-2">{{ $d->syncRun?->source_as_of?->format('Y-m-d') ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-6 text-center text-gray-500">ไม่มีข้อมูล</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
