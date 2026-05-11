@extends('layouts.app')
@section('title', 'มูลค่าสต็อกคงเหลือ')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">มูลค่าสต็อกคงเหลือ (Stock Valuation)</h2>
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เคาน์เตอร์</label>
                <select name="counter_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">ทั้งหมด (ทุกสาขา)</option>
                    @foreach ($counters as $c)
                        <option value="{{ $c->id }}" {{ $counterId == $c->id ? 'selected' : '' }}>{{ $c->counter_name }} ({{ $c->branch->branch_name ?? '' }})</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">แสดง</button>
            <button type="submit" formmethod="POST" formaction="{{ route('reports.stock-valuation.export') }}" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 inline-flex items-center gap-1">
                @csrf <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Excel
            </button>
        </form>
        <p class="text-xs text-gray-400 mt-2">ณ วันที่ {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr style="background:#0e513a;" class="text-white">
                    <th class="px-3 py-3 text-left font-semibold">เคาน์เตอร์</th>
                    <th class="px-3 py-3 text-left font-semibold">สาขา</th>
                    <th class="px-3 py-3 text-left font-semibold">สกุลเงิน</th>
                    <th class="px-3 py-3 text-left font-semibold">ธนบัตร</th>
                    <th class="px-3 py-3 text-right font-semibold">จำนวน</th>
                    <th class="px-3 py-3 text-right font-semibold">ต้นทุนเฉลี่ย</th>
                    <th class="px-3 py-3 text-right font-semibold">มูลค่า (THB)</th>
                </tr></thead>
                <tbody>
                    @php $totalValue = 0; @endphp
                    @forelse ($data as $idx => $d)
                    @php $value = (float)$d->quantity * (float)$d->avg_cost; $totalValue += $value; @endphp
                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                        <td class="px-3 py-2">{{ $d->counter?->counter_name ?? '-' }}</td>
                        <td class="px-3 py-2 text-xs text-gray-500">{{ $d->counter?->branch?->branch_name ?? '-' }}</td>
                        <td class="px-3 py-2 font-medium">{{ $d->currency_code }}</td>
                        <td class="px-3 py-2">{{ $d->denomination?->denom_label ?? '-' }}</td>
                        <td class="px-3 py-2 text-right font-mono">{{ number_format($d->quantity, 2) }}</td>
                        <td class="px-3 py-2 text-right font-mono text-gray-500">{{ number_format($d->avg_cost, 4) }}</td>
                        <td class="px-3 py-2 text-right font-mono font-medium">{{ number_format($value, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-gray-400">ไม่มีสต็อก</td></tr>
                    @endforelse
                </tbody>
                @if ($data->count())
                <tfoot><tr class="bg-gray-100 font-bold border-t-2">
                    <td class="px-3 py-2" colspan="6">รวมมูลค่าทั้งหมด</td>
                    <td class="px-3 py-2 text-right text-lg" style="color:#0e513a;">{{ number_format($totalValue, 2) }}</td>
                </tr></tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
