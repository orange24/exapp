@extends('layouts.app')

@section('title', 'สรุปยอด Average Rate (Summary Report Average Rate)')

@section('content')
<div class="max-w-6xl mx-auto">

    {{-- Filter form --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">สรุปยอด Average Rate (Summary Report Average Rate)</h2>

        <form method="GET" action="{{ route('reports.avg-rate') }}" id="filterForm">
            <div class="flex flex-wrap items-end gap-4 mb-4">
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
                <div>
                    <button type="submit" formmethod="POST" formaction="{{ route('reports.avg-rate.export') }}"
                            class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 transition-colors inline-flex items-center gap-2">
                        @csrf
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        ดาวน์โหลด Excel
                    </button>
                </div>
            </div>

            {{-- Counter checkboxes --}}
            <div class="border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">เลือกเคาน์เตอร์</label>
                    <div class="flex gap-2">
                        <button type="button" onclick="document.querySelectorAll('input[name=\'counters[]\']').forEach(c => c.checked = true)"
                                class="text-xs text-blue-600 hover:text-blue-800">เลือกทั้งหมด</button>
                        <span class="text-gray-300">|</span>
                        <button type="button" onclick="document.querySelectorAll('input[name=\'counters[]\']').forEach(c => c.checked = false)"
                                class="text-xs text-blue-600 hover:text-blue-800">ไม่เลือก</button>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2">
                    @foreach ($counters as $counter)
                    <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer hover:bg-gray-50 rounded px-2 py-1">
                        <input type="checkbox" name="counters[]" value="{{ $counter->id }}"
                               {{ in_array((string) $counter->id, $selectedCounters) ? 'checked' : '' }}
                               class="rounded border-gray-300 text-[#0e513a] focus:ring-[#0e513a]">
                        <span>{{ $counter->counter_name }}</span>
                        <span class="text-xs text-gray-400">({{ $counter->branch->branch_name ?? '' }})</span>
                    </label>
                    @endforeach
                </div>
            </div>
        </form>
        <p class="text-xs text-gray-400 mt-2">Excel แยก 3 Sheet: BUYING, SELLING, SUMMARY — ใช้ Average Rate ต่อสกุลเงิน</p>
    </div>

    {{-- ============================================================ --}}
    {{-- BUYING SECTION --}}
    {{-- ============================================================ --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-base font-semibold text-gray-800 mb-1">BUYING — ยอดรับซื้อ (Average Rate)</h3>
        <p class="text-sm text-gray-500 mb-3">
            ยอดประจำวันที่ {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border border-gray-200 px-4 py-2 text-left font-semibold text-gray-700">สกุลเงิน</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">ยอดรับซื้อ</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">เลท (Avg)</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">ยอดรวม (THB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($buying as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-200 px-4 py-1.5">{{ $item->currency_name }}</td>
                            <td class="border border-gray-200 px-4 py-1.5 text-right">{{ number_format($item->total_amount, 2) }}</td>
                            <td class="border border-gray-200 px-4 py-1.5 text-right">{{ number_format($item->avg_rate, 6) }}</td>
                            <td class="border border-gray-200 px-4 py-1.5 text-right">{{ number_format($item->total_thb, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="border border-gray-200 px-4 py-4 text-center text-gray-400">ไม่มีข้อมูล</td></tr>
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

    {{-- ============================================================ --}}
    {{-- SELLING SECTION --}}
    {{-- ============================================================ --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-base font-semibold text-gray-800 mb-1">SELLING — ยอดนำส่ง/ขาย (Average Rate)</h3>
        <p class="text-sm text-gray-500 mb-3">
            ยอดประจำวันที่ {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border border-gray-200 px-4 py-2 text-left font-semibold text-gray-700">สกุลเงิน</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">ยอดนำส่ง/ขาย</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">เลท (Avg)</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">ยอดรวม (THB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($selling as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-200 px-4 py-1.5">{{ $item->currency_name }}</td>
                            <td class="border border-gray-200 px-4 py-1.5 text-right">{{ number_format($item->total_amount, 2) }}</td>
                            <td class="border border-gray-200 px-4 py-1.5 text-right">{{ number_format($item->avg_rate, 6) }}</td>
                            <td class="border border-gray-200 px-4 py-1.5 text-right">{{ number_format($item->total_thb, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="border border-gray-200 px-4 py-4 text-center text-gray-400">ไม่มีข้อมูล</td></tr>
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

    {{-- ============================================================ --}}
    {{-- SUMMARY SECTION --}}
    {{-- ============================================================ --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h3 class="text-base font-semibold text-gray-800 mb-1">SUMMARY — สรุปยอดรวม</h3>
        <p class="text-sm text-gray-500 mb-3">
            ยอดประจำวันที่ {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
            — ซื้อ (บวก) / ขาย (ลบ) net ต่อสกุลเงิน, คำนวณ THB ด้วย Buying Avg Rate
        </p>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm border border-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="border border-gray-200 px-4 py-2 text-left font-semibold text-gray-700">สกุลเงิน</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">รวมเงินต่างประเทศ</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">Rate (Buying)</th>
                        <th class="border border-gray-200 px-4 py-2 text-right font-semibold text-gray-700">ยอดรวม (THB)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($summary as $item)
                        <tr class="hover:bg-gray-50">
                            <td class="border border-gray-200 px-4 py-1.5">{{ $item['currency_name'] }}</td>
                            <td class="border border-gray-200 px-4 py-1.5 text-right">{{ number_format($item['net_amount'], 2) }}</td>
                            <td class="border border-gray-200 px-4 py-1.5 text-right">{{ $item['buying_avg_rate'] > 0 ? number_format($item['buying_avg_rate'], 6) : '—' }}</td>
                            <td class="border border-gray-200 px-4 py-1.5 text-right">{{ number_format($item['net_thb'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="border border-gray-200 px-4 py-4 text-center text-gray-400">ไม่มีข้อมูล</td></tr>
                    @endforelse
                </tbody>
                @if ($summary->count())
                <tfoot>
                    <tr class="bg-gray-50 font-bold">
                        <td class="border border-gray-200 px-4 py-2">รวมยอด</td>
                        <td class="border border-gray-200 px-4 py-2"></td>
                        <td class="border border-gray-200 px-4 py-2"></td>
                        <td class="border border-gray-200 px-4 py-2 text-right">{{ number_format($summary->sum('net_thb'), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>

</div>
@endsection
