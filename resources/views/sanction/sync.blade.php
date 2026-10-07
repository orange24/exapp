@extends('layouts.app')

@section('title', 'อัปเดตรายชื่อบุคคลต้องห้าม (ปปง.)')

@section('content')
    <div class="max-w-4xl">

        {{-- layout ส่วนกลางแสดงแค่ error ไม่มี success จึงแสดงเองที่นี่ทั้งคู่ --}}
        @if (session('success'))
            <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-800 rounded text-sm">
                {{ session('success') }}
            </div>
        @endif

        <div class="bg-white rounded shadow p-5 mb-5">
            <h1 class="text-lg font-semibold" style="color:#0e513a">อัปเดตรายชื่อบุคคลต้องห้าม (ปปง.)</h1>

            <p class="text-sm text-gray-600 mt-2">
                ระบบอ่านรายชื่อจากหน้าเว็บสาธารณะของ ปปง. ที่
                <span class="font-mono text-xs">aps.amlo.go.th</span>
                ซึ่งเป็นหน้าเว็บ ไม่ใช่ API — ไม่มีช่องทางดึงข้อมูลสดแบบ realtime
                ระบบจึงต้องไล่อ่านแล้วแกะข้อมูลเอง
            </p>

            <div class="mt-4 grid grid-cols-2 gap-4 text-sm">
                <div class="border rounded p-3">
                    <div class="text-gray-500 text-xs">รายชื่อที่ใช้งานอยู่</div>
                    <div class="text-2xl font-semibold" style="color:#0e513a">{{ number_format($activeEntries) }}</div>
                </div>
                <div class="border rounded p-3">
                    <div class="text-gray-500 text-xs">อัปเดตสำเร็จครั้งล่าสุด</div>
                    <div class="text-lg font-semibold">
                        @php($lastOk = $runs->firstWhere('status', 'success'))
                        {{ $lastOk?->finished_at?->format('d/m/Y H:i') ?? 'ยังไม่เคย' }}
                    </div>
                </div>
            </div>

            <div class="mt-5">
                @if ($inProgress)
                    <div class="p-3 bg-amber-50 border border-amber-300 rounded text-sm text-amber-800">
                        กำลังอัปเดตอยู่ — เริ่มเมื่อ {{ $inProgress->started_at->format('d/m/Y H:i:s') }}
                        ({{ $inProgress->started_at->diffForHumans() }})
                        <div class="mt-1 text-xs">รีเฟรชหน้านี้เพื่อดูความคืบหน้า</div>
                    </div>
                @else
                    <a href="{{ route('sanctions.sync', ['action' => 'update']) }}"
                       class="inline-block px-5 py-2.5 rounded text-white font-medium"
                       style="background-color:#0e513a"
                       onclick="return confirm('เริ่มอัปเดตรายชื่อจาก ปปง. ตอนนี้?\n\nถ้าไม่มีอะไรเปลี่ยนจะใช้เวลาไม่กี่นาที\nถ้า ปปง. แก้ประกาศ อาจใช้เวลาราว 20 นาที')">
                        เริ่มอัปเดตรายชื่อ
                    </a>
                    <p class="text-xs text-gray-500 mt-2">
                        ระบบหน่วงคำขอไปเซิร์ฟเวอร์ ปปง. ไว้ 1 วินาทีต่อหน้าเป็นมารยาท
                        วันที่รายชื่อเปลี่ยนจึงต้องไล่อ่านนับพันหน้าและใช้เวลาหลายนาที
                        งานจะทำเบื้องหลัง ปิดหน้านี้ได้เลย
                    </p>
                @endif
            </div>
        </div>

        <div class="bg-white rounded shadow p-5">
            <h2 class="font-semibold mb-3" style="color:#0e513a">ประวัติการอัปเดต 15 ครั้งล่าสุด</h2>

            @if ($runs->isEmpty())
                <p class="text-sm text-gray-500">ยังไม่มีประวัติ</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-gray-500 border-b">
                                <th class="py-2 pr-3">เริ่ม</th>
                                <th class="py-2 pr-3">รายชื่อ</th>
                                <th class="py-2 pr-3">สถานะ</th>
                                <th class="py-2 pr-3 text-right">เพิ่ม</th>
                                <th class="py-2 pr-3 text-right">แก้</th>
                                <th class="py-2 pr-3 text-right">ถอน</th>
                                <th class="py-2">สั่งโดย</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($runs as $run)
                                <tr class="border-b last:border-0">
                                    <td class="py-2 pr-3 whitespace-nowrap">{{ $run->started_at?->format('d/m/Y H:i') }}</td>
                                    <td class="py-2 pr-3 whitespace-nowrap text-xs">{{ $run->list_code }}</td>
                                    <td class="py-2 pr-3">
                                        @if ($run->status === 'success')
                                            <span class="text-green-700">สำเร็จ</span>
                                        @elseif ($run->finished_at === null)
                                            <span class="text-amber-700">กำลังทำงาน</span>
                                        @else
                                            <span class="text-red-700" title="{{ $run->error_message }}">{{ $run->status }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-3 text-right">{{ $run->entries_added ?? '-' }}</td>
                                    <td class="py-2 pr-3 text-right">{{ $run->entries_updated ?? '-' }}</td>
                                    <td class="py-2 pr-3 text-right">{{ $run->entries_removed ?? '-' }}</td>
                                    <td class="py-2 text-xs text-gray-600">
                                        {{ $run->forcedBy?->name ?? 'ระบบ' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
