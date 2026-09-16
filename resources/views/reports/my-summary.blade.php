@extends('layouts.app')

@section('title', 'สรุปยอดของฉัน (Summary Report)')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">สรุปยอดของฉัน (Summary Report)</h2>
        <p class="text-sm text-gray-500">ดาวน์โหลดสรุปยอดซื้อ/ขายที่คุณทำเองในแต่ละวัน</p>
    </div>

    @if (session('error'))
        <div class="mb-4 p-3 bg-red-50 border border-red-300 text-red-700 rounded text-sm">
            {{ session('error') }}
        </div>
    @endif

    <div class="bg-white rounded-lg shadow p-6">
        <div class="text-sm text-gray-600 mb-4">
            เคาน์เตอร์: <strong class="text-gray-900">{{ $counter?->counter_name ?? '—' }}</strong>
        </div>

        <form id="mySummaryForm" method="POST" action="{{ route('reports.my-summary.export') }}" class="flex flex-wrap items-end gap-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">วันที่ทำรายการ</label>
                <input type="date" name="date" value="{{ $date }}"
                       class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>
            <div class="flex items-end gap-2">
                <button type="submit"
                        class="bg-[#0e513a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors inline-flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Export
                </button>
                <button type="reset" class="bg-white text-gray-700 border border-gray-300 px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-50">
                    Clear
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
