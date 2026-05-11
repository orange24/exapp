@extends('layouts.app')
@section('title', 'สรุปเคลื่อนไหวสต็อกรายวัน')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">สรุปเคลื่อนไหวสต็อกรายวัน (Daily Stock Movement)</h2>
        <form method="GET" class="flex flex-wrap items-end gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เคาน์เตอร์</label>
                <select name="counter_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">-- ทั้งหมด --</option>
                    @foreach ($counters as $c)
                        <option value="{{ $c->id }}" {{ $counterId == $c->id ? 'selected' : '' }}>{{ $c->counter_name }} ({{ $c->branch->branch_name ?? '' }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่</label>
                <input type="date" name="date" value="{{ $date }}" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">แสดงข้อมูล</button>
            <button type="submit" formmethod="POST" formaction="{{ route('reports.stock-movement.export') }}" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 inline-flex items-center gap-1">
                @csrf
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                Excel
            </button>
        </form>
    </div>

    @if ($data->count())
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr style="background:#0e513a;" class="text-white">
                    <th class="px-3 py-3 text-left font-semibold">สกุลเงิน</th>
                    <th class="px-3 py-3 text-left font-semibold">ธนบัตร</th>
                    <th class="px-3 py-3 text-right font-semibold">ยอดยกมา</th>
                    <th class="px-3 py-3 text-right font-semibold text-green-300">ซื้อเข้า</th>
                    <th class="px-3 py-3 text-right font-semibold text-red-300">ขายออก</th>
                    <th class="px-3 py-3 text-right font-semibold text-blue-300">โอนเข้า</th>
                    <th class="px-3 py-3 text-right font-semibold text-pink-300">โอนออก</th>
                    <th class="px-3 py-3 text-right font-semibold text-yellow-300">ปรับปรุง</th>
                    <th class="px-3 py-3 text-right font-semibold">คงเหลือ</th>
                </tr></thead>
                <tbody>
                    @foreach ($data as $idx => $row)
                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                        <td class="px-3 py-2 font-medium">{{ $row['currency_code'] }}</td>
                        <td class="px-3 py-2">{{ $row['denom_label'] }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($row['opening'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-green-600">{{ $row['bought'] > 0 ? number_format($row['bought'], 2) : '' }}</td>
                        <td class="px-3 py-2 text-right text-red-600">{{ $row['sold'] > 0 ? number_format($row['sold'], 2) : '' }}</td>
                        <td class="px-3 py-2 text-right text-blue-600">{{ $row['tfr_in'] > 0 ? number_format($row['tfr_in'], 2) : '' }}</td>
                        <td class="px-3 py-2 text-right text-pink-600">{{ $row['tfr_out'] > 0 ? number_format($row['tfr_out'], 2) : '' }}</td>
                        <td class="px-3 py-2 text-right {{ $row['adjust'] >= 0 ? 'text-yellow-600' : 'text-red-600' }}">{{ $row['adjust'] != 0 ? number_format($row['adjust'], 2) : '' }}</td>
                        <td class="px-3 py-2 text-right font-bold">{{ number_format($row['remaining'], 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot><tr class="bg-gray-100 font-bold border-t-2">
                    <td class="px-3 py-2" colspan="2">รวม</td>
                    <td class="px-3 py-2 text-right">{{ number_format($data->sum('opening'), 2) }}</td>
                    <td class="px-3 py-2 text-right text-green-600">{{ number_format($data->sum('bought'), 2) }}</td>
                    <td class="px-3 py-2 text-right text-red-600">{{ number_format($data->sum('sold'), 2) }}</td>
                    <td class="px-3 py-2 text-right text-blue-600">{{ number_format($data->sum('tfr_in'), 2) }}</td>
                    <td class="px-3 py-2 text-right text-pink-600">{{ number_format($data->sum('tfr_out'), 2) }}</td>
                    <td class="px-3 py-2 text-right text-yellow-600">{{ number_format($data->sum('adjust'), 2) }}</td>
                    <td class="px-3 py-2 text-right">{{ number_format($data->sum('remaining'), 2) }}</td>
                </tr></tfoot>
            </table>
        </div>
    </div>
    @else
    <div class="bg-white rounded-lg shadow p-8 text-center text-gray-400">เลือกเคาน์เตอร์และวันที่เพื่อดูข้อมูล</div>
    @endif
</div>
@endsection
