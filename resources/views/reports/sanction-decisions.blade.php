@extends('layouts.app')

@section('title', 'รายงานการอนุมัติเมื่อพบชื่อใกล้เคียง')

@section('content')
    <form method="GET" class="flex flex-wrap gap-2 items-end mb-3 text-sm">
        <label>ตั้งแต่ <input type="date" name="date_from" value="{{ $dateFrom }}" class="border rounded px-2 py-1"></label>
        <label>ถึง <input type="date" name="date_to" value="{{ $dateTo }}" class="border rounded px-2 py-1"></label>
        <button class="px-3 py-1.5 rounded text-white" style="background:#0e513a;">ค้นหา</button>
    </form>

    <div class="bg-white border rounded p-3 mb-3">
        <div class="font-semibold text-sm mb-2">สรุปตามสาขา</div>
        <table class="text-sm">
            <thead><tr class="text-left">
                <th class="pr-6">สาขา</th><th class="pr-6">ทั้งหมด</th>
                <th class="pr-6">ไม่ใช่คนเดียวกัน</th><th>ยืนยันตรงกัน</th>
            </tr></thead>
            <tbody>
                @forelse ($byBranch as $name => $s)
                    <tr><td class="pr-6">{{ $name }}</td><td class="pr-6">{{ $s['total'] }}</td>
                        <td class="pr-6">{{ $s['false_positive'] }}</td><td>{{ $s['true_match'] }}</td></tr>
                @empty
                    <tr><td colspan="4" class="py-2 text-gray-500">ไม่มีข้อมูล</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <form method="POST" action="{{ route('reports.sanction-decisions.export') }}" class="mb-3">
        @csrf
        <input type="hidden" name="date_from" value="{{ $dateFrom }}">
        <input type="hidden" name="date_to" value="{{ $dateTo }}">
        <button class="px-3 py-1.5 rounded border text-sm bg-white">ดาวน์โหลด Excel</button>
    </form>

    <div class="bg-white border rounded overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="p-2 text-left">วันเวลา</th><th class="p-2 text-left">ธุรกรรม</th>
                <th class="p-2 text-left">สาขา</th><th class="p-2 text-left">พนักงาน</th>
                <th class="p-2 text-left">ผู้อนุมัติ</th><th class="p-2 text-left">ลูกค้า</th>
                <th class="p-2 text-right">คะแนน</th><th class="p-2 text-left">เหตุผล</th>
            </tr></thead>
            <tbody class="divide-y">
                @forelse ($data as $d)
                    <tr>
                        <td class="p-2 whitespace-nowrap">{{ $d->decided_at?->format('d/m/Y H:i') }}</td>
                        <td class="p-2">{{ $d->transaction?->trns_no ?? '-' }}</td>
                        <td class="p-2">{{ $d->branch?->branch_name ?? '-' }}</td>
                        <td class="p-2">{{ $d->screenedBy?->name ?? 'ระบบ' }}</td>
                        <td class="p-2">{{ $d->decidedBy?->name ?? '-' }}</td>
                        <td class="p-2">{{ $d->input_name ?? '-' }}</td>
                        <td class="p-2 text-right">{{ number_format((float) $d->top_score, 0) }}</td>
                        <td class="p-2 text-xs text-gray-600">{{ $d->decision_reason }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-6 text-center text-gray-500">ไม่มีข้อมูล</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
