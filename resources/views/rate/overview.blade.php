@extends('layouts.app')

@section('title', 'เรททุกสาขา (Rate Overview)')

@section('content')
<div class="mb-4">
    <h2 class="text-lg font-semibold text-gray-800">เรททุกสาขา (Rate Overview)</h2>
    <p class="text-sm text-gray-500">
        ราคารับซื้อ / ขาย วันนี้ ({{ today()->format('d/m/Y') }}) ของทุกเคาน์เตอร์ในสาขาที่มีสิทธิ์ — ดูอย่างเดียว
        @if ($lastUpdate) · อัปเดตล่าสุด {{ $lastUpdate }} @endif
    </p>
</div>

<form method="GET" action="{{ route('rates.overview') }}" class="bg-white rounded-lg shadow p-4 mb-4 flex flex-wrap items-end gap-3">
    <div>
        <label class="block text-xs font-medium text-gray-600 mb-1">สาขา</label>
        <select name="branch_id" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">ทุกสาขา</option>
            @foreach ($branches as $b)
                <option value="{{ $b->id }}" {{ $branchId === $b->id ? 'selected' : '' }}>{{ $b->branch_name }}</option>
            @endforeach
        </select>
    </div>
</form>

@if ($counters->isEmpty())
    <div class="bg-white rounded-lg shadow p-6 text-center text-gray-500">ไม่มีเคาน์เตอร์ในสาขาที่มีสิทธิ์</div>
@elseif ($rows->isEmpty())
    <div class="bg-white rounded-lg shadow p-6 text-center text-gray-500">วันนี้ยังไม่มีเคาน์เตอร์ไหนตั้งราคา</div>
@else
    <div class="bg-white rounded-lg shadow overflow-x-auto">
        <table class="text-sm whitespace-nowrap">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th rowspan="2" class="px-3 py-2 text-left sticky left-0 bg-gray-50">สกุลเงิน</th>
                    @foreach ($counters as $c)
                        <th colspan="2" class="px-3 py-2 text-center border-l">
                            <div class="font-semibold text-gray-800">{{ $c->branch->branch_name ?? '' }}</div>
                            <div class="text-xs font-normal">{{ $c->counter_name }}</div>
                        </th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($counters as $c)
                        <th class="px-3 py-1 text-right text-xs font-medium border-l">ซื้อ</th>
                        <th class="px-3 py-1 text-right text-xs font-medium">ขาย</th>
                    @endforeach
                </tr>
            </thead>
            <tbody class="divide-y">
                @foreach ($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 sticky left-0 bg-white">
                            <div class="flex items-center gap-2">
                                @if ($row['currency'])
                                    <img src="{{ $row['currency']->flag_url }}" alt="" class="w-5 h-auto">
                                @endif
                                <span class="font-semibold">{{ $row['denomination']->display_name ?? $row['denomination']->denom_label ?? '' }}</span>
                            </div>
                        </td>
                        @foreach ($counters as $c)
                            @php $r = $row['byCounter']->get($c->id); @endphp
                            <td class="px-3 py-2 text-right font-mono border-l">{{ $r && $r->rate_buy > 0 ? format_rate($r->rate_buy) : '—' }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ $r && $r->rate_sell > 0 ? format_rate($r->rate_sell) : '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection
