@extends('layouts.app')

@section('title', 'รายงานสรุปประจำวัน (Daily Summary Report)')

@section('content')
<div class="max-w-5xl mx-auto">

    {{-- Filter form --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">รายงานสรุปประจำวัน (Daily Summary Report)</h2>

        <div class="flex flex-wrap items-end gap-4 mb-4">
            <div class="text-sm text-gray-600">
                เคาน์เตอร์: <strong class="text-gray-900">{{ $counter?->counter_name ?? '—' }}</strong>
            </div>
        </div>

        <form method="GET" action="{{ route('reports.daily') }}" class="flex flex-wrap items-end gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่</label>
                <input type="date" name="date" value="{{ $date }}"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors">
                    แสดงข้อมูล
                </button>
            </div>
            {{-- Export button inline --}}
            <div>
                <button type="submit" formmethod="POST" formaction="{{ route('reports.daily.export') }}"
                        class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors inline-flex items-center gap-2">
                    @csrf
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    ดาวน์โหลด Excel
                </button>
            </div>
        </form>
    </div>

    {{-- Preview: BUYING section --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-base font-semibold text-gray-800 mb-1">ยอดรับซื้อ (Buying)</h3>
        <p class="text-sm text-gray-500 mb-3">
            ยอดประจำวันที่ {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
            @if ($counter) — {{ $counter->counter_name }} @endif
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border border-gray-200 px-4 py-2 text-left font-semibold text-gray-700">สกุลเงิน</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">ยอดรับซื้อ</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">รับซื้อ</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">ยอดรวม (THB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($buying as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-200 px-4 py-2">{{ $item->currency_name }}</td>
                            <td class="border border-gray-200 px-4 py-2 text-right">{{ number_format($item->total_amount, 2) }}</td>
                            <td class="border border-gray-200 px-4 py-2 text-right">{{ number_format($item->unit_price, 6) }}</td>
                            <td class="border border-gray-200 px-4 py-2 text-right">{{ number_format($item->total_thb, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="border border-gray-200 px-4 py-4 text-center text-gray-400">ไม่มีข้อมูล</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($buying->count())
                <tfoot>
                    <tr class="bg-gray-50 font-bold">
                        <td class="border border-gray-200 px-4 py-2">รวมยอดรับซื้อ</td>
                        <td class="border border-gray-200 px-4 py-2"></td>
                        <td class="border border-gray-200 px-4 py-2"></td>
                        <td class="border border-gray-200 px-4 py-2 text-right">{{ number_format($buying->sum('total_thb'), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

    {{-- Preview: SELLING section --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-base font-semibold text-gray-800 mb-1">ยอดนำส่ง/ขาย (Selling)</h3>
        <p class="text-sm text-gray-500 mb-3">
            ยอดประจำวันที่ {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
            @if ($counter) — {{ $counter->counter_name }} @endif
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border border-gray-200 px-4 py-2 text-left font-semibold text-gray-700">สกุลเงิน</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">ยอดนำส่ง/ขาย</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">ขาย</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">ยอดรวม (THB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($selling as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-200 px-4 py-2">{{ $item->currency_name }}</td>
                            <td class="border border-gray-200 px-4 py-2 text-right">{{ number_format($item->total_amount, 2) }}</td>
                            <td class="border border-gray-200 px-4 py-2 text-right">{{ number_format($item->unit_price, 6) }}</td>
                            <td class="border border-gray-200 px-4 py-2 text-right">{{ number_format($item->total_thb, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="border border-gray-200 px-4 py-4 text-center text-gray-400">ไม่มีข้อมูล</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($selling->count())
                <tfoot>
                    <tr class="bg-gray-50 font-bold">
                        <td class="border border-gray-200 px-4 py-2">รวมยอดขาย</td>
                        <td class="border border-gray-200 px-4 py-2"></td>
                        <td class="border border-gray-200 px-4 py-2"></td>
                        <td class="border border-gray-200 px-4 py-2 text-right">{{ number_format($selling->sum('total_thb'), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
@endsection
