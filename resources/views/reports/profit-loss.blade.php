@extends('layouts.app')
@section('title', 'กำไร-ขาดทุน FX')
@section('content')
<div class="max-w-7xl mx-auto">
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">กำไร-ขาดทุนจากอัตราแลกเปลี่ยน (FX Profit/Loss)</h2>
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
                <label class="block text-sm font-medium text-gray-700 mb-1">เคาน์เตอร์</label>
                <select name="counter_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">ทั้งหมด</option>
                    @foreach ($counters as $c)
                        <option value="{{ $c->id }}" {{ $counterId == $c->id ? 'selected' : '' }}>{{ $c->counter_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">แสดง</button>
            <button type="submit" formmethod="POST" formaction="{{ route('reports.profit-loss.export') }}" class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 inline-flex items-center gap-1">
                @csrf <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg> Excel
            </button>
        </form>
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr style="background:#0e513a;" class="text-white">
                    <th class="px-3 py-3 text-left font-semibold">สกุลเงิน</th>
                    <th class="px-3 py-3 text-right font-semibold">ซื้อ (จำนวน)</th>
                    <th class="px-3 py-3 text-right font-semibold">ซื้อ (THB)</th>
                    <th class="px-3 py-3 text-right font-semibold">ขาย (จำนวน)</th>
                    <th class="px-3 py-3 text-right font-semibold">ขาย (THB)</th>
                    <th class="px-3 py-3 text-right font-semibold">สุทธิ (จำนวน)</th>
                    <th class="px-3 py-3 text-right font-semibold">กำไร/ขาดทุน</th>
                    <th class="px-3 py-3 text-right font-semibold">Margin %</th>
                </tr></thead>
                <tbody>
                    @php $totalProfit = 0; @endphp
                    @forelse ($data as $idx => $d)
                    @php
                        $profit = (float)$d->sell_thb - (float)$d->buy_thb;
                        $margin = (float)$d->buy_thb > 0 ? ($profit / (float)$d->buy_thb * 100) : 0;
                        $totalProfit += $profit;
                    @endphp
                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                        <td class="px-3 py-2 font-medium">{{ $d->currency_code }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($d->buy_amount, 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($d->buy_thb, 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($d->sell_amount, 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format($d->sell_thb, 2) }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float)$d->sell_amount - (float)$d->buy_amount, 2) }}</td>
                        <td class="px-3 py-2 text-right font-bold {{ $profit >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($profit, 2) }}</td>
                        <td class="px-3 py-2 text-right {{ $margin >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($margin, 2) }}%</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-400">ไม่มีข้อมูล</td></tr>
                    @endforelse
                </tbody>
                @if ($data->count())
                <tfoot><tr class="bg-gray-100 font-bold border-t-2">
                    <td class="px-3 py-2" colspan="6">รวมกำไร/ขาดทุน</td>
                    <td class="px-3 py-2 text-right text-lg {{ $totalProfit >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($totalProfit, 2) }}</td>
                    <td></td>
                </tr></tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
