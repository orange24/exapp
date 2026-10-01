@extends('layouts.app')

@section('title', 'ธุรกรรมที่ไม่ได้ตรวจรายชื่อ')

@section('content')
    <form method="GET" class="flex gap-2 items-end mb-3 text-sm">
        <label>ตั้งแต่ <input type="date" name="date_from" value="{{ $dateFrom }}" class="border rounded px-2 py-1"></label>
        <label>ถึง <input type="date" name="date_to" value="{{ $dateTo }}" class="border rounded px-2 py-1"></label>
        <button class="px-3 py-1.5 rounded text-white" style="background:#0e513a;">ค้นหา</button>
    </form>

    <div class="bg-white border rounded p-3 mb-3 text-sm">
        ธุรกรรมทั้งหมด <b>{{ number_format($total) }}</b> รายการ ·
        ไม่ได้ตรวจรายชื่อ <b class="{{ $unscreenedTotal ? 'text-red-600' : '' }}">{{ number_format($unscreenedTotal) }}</b> รายการ
        @if ($total > 0)
            ({{ number_format($unscreenedTotal / $total * 100, 1) }}%)
        @endif
        <div class="text-xs text-gray-500 mt-1">
            สาเหตุปกติคือพนักงานไม่ได้กรอกข้อมูลลูกค้า — ใช้รายงานนี้หาสาขาที่ข้ามขั้นตอนบ่อย
        </div>
        @if ($unscreenedTotal > $unscreened->count())
            <div class="text-xs text-gray-500 mt-1">
                แสดงเฉพาะ {{ number_format($unscreened->count()) }} รายการล่าสุด — ย่อช่วงวันที่เพื่อดูให้ครบ
            </div>
        @endif
    </div>

    <div class="bg-white border rounded overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="p-2 text-left">วันเวลา</th><th class="p-2 text-left">เลขที่</th>
                <th class="p-2 text-left">ประเภท</th><th class="p-2 text-left">เคาน์เตอร์</th>
                <th class="p-2 text-left">ชื่อลูกค้าที่บันทึกไว้</th>
            </tr></thead>
            <tbody class="divide-y">
                @forelse ($unscreened as $t)
                    <tr>
                        <td class="p-2 whitespace-nowrap">{{ $t->trns_datetime?->format('d/m/Y H:i') }}</td>
                        <td class="p-2">{{ $t->trns_no }}</td>
                        <td class="p-2">{{ $t->trns_type }}</td>
                        <td class="p-2">{{ $t->counter_name }}</td>
                        <td class="p-2">{{ $t->cust_name ?: '(ไม่ระบุ)' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-green-700">ตรวจครบทุกรายการ</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
