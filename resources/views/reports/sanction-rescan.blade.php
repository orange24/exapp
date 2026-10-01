@extends('layouts.app')

@section('title', 'ลูกค้าเดิมที่กลายเป็นชื่อต้องห้าม')

@section('content')
    <form method="GET" class="flex gap-2 items-end mb-3 text-sm">
        <label>ตั้งแต่ <input type="date" name="date_from" value="{{ $dateFrom }}" class="border rounded px-2 py-1"></label>
        <label>ถึง <input type="date" name="date_to" value="{{ $dateTo }}" class="border rounded px-2 py-1"></label>
        <button class="px-3 py-1.5 rounded text-white" style="background:#0e513a;">ค้นหา</button>
    </form>

    <div class="bg-white border rounded overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="p-2 text-left">พบเมื่อ</th><th class="p-2 text-left">ลูกค้า</th>
                <th class="p-2 text-left">รายชื่อที่ตรง</th><th class="p-2 text-right">คะแนน</th>
                <th class="p-2 text-left">สถานะ</th>
            </tr></thead>
            <tbody class="divide-y">
                @forelse ($data as $d)
                    <tr>
                        <td class="p-2 whitespace-nowrap">{{ $d->screened_at?->format('d/m/Y H:i') }}</td>
                        <td class="p-2">
                            {{ $d->input_name ?? '-' }}
                            <div class="text-xs text-gray-500">{{ $d->input_id_number ?? '-' }}</div>
                        </td>
                        <td class="p-2">
                            @foreach ($d->matches as $m)
                                <div>{{ $m->entry?->name_en ?: $m->entry?->name_th }}
                                    <span class="text-xs text-gray-500">{{ $m->entry?->list_code }}</span></div>
                            @endforeach
                        </td>
                        <td class="p-2 text-right">{{ number_format((float) $d->top_score, 0) }}</td>
                        <td class="p-2">
                            {{ $d->decision ? ($d->decision . ' โดย ' . ($d->decidedBy?->name ?? 'ระบบ')) : 'รอตรวจสอบ' }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-gray-500">ไม่มีข้อมูล</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
