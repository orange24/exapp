@extends('layouts.app')

@section('title', 'การเปลี่ยนแปลงรายชื่อ ปปง.')

@section('content')
    <form method="GET" class="flex gap-2 items-end mb-3 text-sm">
        <label>ตั้งแต่ <input type="date" name="date_from" value="{{ $dateFrom }}" class="border rounded px-2 py-1"></label>
        <label>ถึง <input type="date" name="date_to" value="{{ $dateTo }}" class="border rounded px-2 py-1"></label>
        <button class="px-3 py-1.5 rounded text-white" style="background:#0e513a;">ค้นหา</button>
    </form>

    <div class="bg-white border rounded mb-4 overflow-x-auto">
        <div class="p-2 font-semibold text-sm">รอบที่มีการเปลี่ยนแปลง</div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="p-2 text-left">วันเวลา</th><th class="p-2 text-left">บัญชี</th>
                <th class="p-2 text-left">As Of</th><th class="p-2 text-right">เพิ่ม</th>
                <th class="p-2 text-right">ถอน</th><th class="p-2 text-right">แก้ไข</th>
            </tr></thead>
            <tbody class="divide-y">
                @forelse ($runs as $r)
                    <tr>
                        <td class="p-2 whitespace-nowrap">{{ $r->started_at?->format('d/m/Y H:i') }}</td>
                        <td class="p-2">{{ $r->list_code }}</td>
                        <td class="p-2">{{ $r->source_as_of?->format('Y-m-d') ?? '-' }}</td>
                        <td class="p-2 text-right">{{ $r->entries_added }}</td>
                        <td class="p-2 text-right">{{ $r->entries_removed }}</td>
                        <td class="p-2 text-right">{{ $r->entries_updated }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center text-gray-500">ไม่มีการเปลี่ยนแปลงในช่วงนี้</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="grid md:grid-cols-2 gap-4">
        <div class="bg-white border rounded">
            <div class="p-2 font-semibold text-sm">รายชื่อที่เพิ่มเข้ามา ({{ $recentlyAdded->count() }})</div>
            <ul class="divide-y text-sm">
                @foreach ($recentlyAdded as $e)
                    <li class="p-2">
                        {{ $e->name_en ?: $e->name_th }}
                        <span class="text-xs text-gray-500">
                            {{ $e->list_code }} · {{ $e->first_seen_at?->format('d/m/Y') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="bg-white border rounded">
            <div class="p-2 font-semibold text-sm">รายชื่อที่ถูกเพิกถอน ({{ $recentlyRemoved->count() }})</div>
            <ul class="divide-y text-sm">
                @foreach ($recentlyRemoved as $e)
                    <li class="p-2">
                        {{ $e->name_en ?: $e->name_th }}
                        <span class="text-xs text-gray-500">
                            {{ $e->list_code }} · {{ $e->delisted_at?->format('d/m/Y') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endsection
