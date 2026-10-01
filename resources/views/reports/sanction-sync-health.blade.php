@extends('layouts.app')

@section('title', 'สุขภาพการอัปเดตรายชื่อ')

@section('content')
    <div class="grid md:grid-cols-2 gap-4 mb-4">
        @foreach ($status as $listCode => $s)
            @php $stale = $s['hours_since_success'] === null || $s['hours_since_success'] > 48; @endphp
            <div class="bg-white border rounded p-3 {{ $stale ? 'border-red-400' : '' }}">
                <div class="font-semibold">{{ $listCode }}</div>
                <div class="text-sm mt-1">รายชื่อที่ใช้งานอยู่: <b>{{ number_format($s['active_entries']) }}</b></div>
                <div class="text-sm">As Of ของ ปปง.: {{ $s['source_as_of']?->format('Y-m-d') ?? '-' }}</div>
                <div class="text-sm">
                    sync สำเร็จล่าสุด:
                    {{ $s['last_success_at']?->format('d/m/Y H:i') ?? 'ยังไม่เคยสำเร็จ' }}
                    @if ($s['hours_since_success'] !== null)
                        <span class="{{ $stale ? 'text-red-600 font-bold' : 'text-gray-500' }}">
                            ({{ $s['hours_since_success'] }} ชม. ที่แล้ว)
                        </span>
                    @endif
                </div>
                @if ($s['last_status'] !== \App\Models\SanctionSyncRun::STATUS_SUCCESS)
                    <div class="text-sm text-red-600 mt-1">
                        รอบล่าสุด: {{ $s['last_status'] ?? 'ยังไม่เคยรัน' }}
                        @if ($s['last_error']) — {{ $s['last_error'] }} @endif
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="bg-white border rounded overflow-x-auto">
        <div class="p-2 font-semibold text-sm">ประวัติการ sync 50 รอบล่าสุด</div>
        <table class="w-full text-sm">
            <thead class="bg-gray-50"><tr>
                <th class="p-2 text-left">เริ่ม</th><th class="p-2 text-left">บัญชี</th>
                <th class="p-2 text-left">แหล่งข้อมูล</th><th class="p-2 text-left">สถานะ</th>
                <th class="p-2 text-right">parse</th><th class="p-2 text-right">+/-</th>
                <th class="p-2 text-left">ข้อความ</th>
            </tr></thead>
            <tbody class="divide-y">
                @forelse ($recentRuns as $r)
                    <tr>
                        <td class="p-2 whitespace-nowrap">{{ $r->started_at?->format('d/m/Y H:i') }}</td>
                        <td class="p-2">{{ $r->list_code }}</td>
                        <td class="p-2 text-xs">{{ $r->source_adapter }}</td>
                        <td class="p-2 {{ $r->status === 'success' ? '' : 'text-red-600' }}">{{ $r->status }}</td>
                        <td class="p-2 text-right">{{ $r->entries_parsed }}</td>
                        <td class="p-2 text-right">+{{ $r->entries_added }}/-{{ $r->entries_removed }}</td>
                        <td class="p-2 text-xs text-gray-600">{{ $r->error_message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="p-6 text-center text-gray-500">ยังไม่เคยมีการ sync</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
