@extends('layouts.app')
@section('title', 'รายงานโอน/ยืม/คืน')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">รายงานโอน/ยืม/คืน (Transfer Report)</h2>
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ตั้งแต่</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ถึง</label>
                <input type="date" name="date_to" value="{{ $dateTo }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ประเภท</label>
                <select name="type" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">ทั้งหมด</option>
                    <option value="borrow" {{ $type === 'borrow' ? 'selected' : '' }}>ยืม</option>
                    <option value="return" {{ $type === 'return' ? 'selected' : '' }}>คืน</option>
                    <option value="disbursement" {{ $type === 'disbursement' ? 'selected' : '' }}>เบิกจ่าย</option>
                    <option value="intraday_return" {{ $type === 'intraday_return' ? 'selected' : '' }}>คืนระหว่างวัน</option>
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">แสดง</button>
            <button type="submit" formmethod="POST" formaction="{{ route('reports.transfer.export') }}" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 inline-flex items-center gap-1">
                @csrf <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Excel
            </button>
        </form>
    </div>

    @php $typeLabels = ['borrow'=>'ยืม','return'=>'คืน','disbursement'=>'เบิกจ่าย','intraday_return'=>'คืนระหว่างวัน'];
         $typeBadges = ['borrow'=>'bg-purple-100 text-purple-800','return'=>'bg-indigo-100 text-indigo-800','disbursement'=>'bg-orange-100 text-orange-800','intraday_return'=>'bg-blue-100 text-blue-800']; @endphp

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr style="background:#0e513a;" class="text-white">
                    <th class="px-3 py-3 text-left font-semibold">เลขที่</th>
                    <th class="px-3 py-3 text-left font-semibold">วันที่</th>
                    <th class="px-3 py-3 text-center font-semibold">ประเภท</th>
                    <th class="px-3 py-3 text-left font-semibold">ต้นทาง</th>
                    <th class="px-3 py-3 text-left font-semibold">ปลายทาง</th>
                    <th class="px-3 py-3 text-left font-semibold">สกุลเงิน</th>
                    <th class="px-3 py-3 text-left font-semibold">ธนบัตร</th>
                    <th class="px-3 py-3 text-right font-semibold">จำนวน</th>
                    <th class="px-3 py-3 text-left font-semibold">ผู้ทำ</th>
                </tr></thead>
                <tbody>
                    @forelse ($data as $idx => $tf)
                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                        <td class="px-3 py-2 font-mono text-xs">{{ $tf->transfer_no }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ $tf->transferred_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-3 py-2 text-center"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $typeBadges[$tf->transfer_type] ?? 'bg-gray-100' }}">{{ $typeLabels[$tf->transfer_type] ?? $tf->transfer_type }}</span></td>
                        <td class="px-3 py-2 text-xs">{{ $tf->fromCounter?->counter_name ?? '-' }}</td>
                        <td class="px-3 py-2 text-xs">{{ $tf->toCounter?->counter_name ?? '-' }}</td>
                        <td class="px-3 py-2 font-medium">{{ $tf->currency_code }}</td>
                        <td class="px-3 py-2">{{ $tf->denomination?->denom_label ?? '-' }}</td>
                        <td class="px-3 py-2 text-right font-mono font-medium">{{ number_format($tf->amount, 2) }}</td>
                        <td class="px-3 py-2 text-xs text-gray-600">{{ $tf->createdByUser?->name ?? '-' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-8 text-center text-gray-400">ไม่มีรายการ</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
