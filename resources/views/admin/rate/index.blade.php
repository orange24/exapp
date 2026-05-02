@extends('layouts.app')

@section('title', 'จัดการอัตราแลกเปลี่ยน')

@section('content')
<div class="max-w-5xl mx-auto py-6 px-4">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">จัดการอัตราแลกเปลี่ยน</h1>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach ($counters as $counter)
            @php
                $rateCount   = $counter->rates->count();
                $hasRates    = $rateCount > 0;
            @endphp
            <div class="bg-white rounded-lg shadow border border-gray-200 p-4">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <h3 class="font-bold text-gray-800">{{ $counter->counter_name }}</h3>
                        <p class="text-sm text-gray-500">{{ $counter->branch->branch_name ?? '' }}</p>
                        <p class="text-xs text-gray-400 font-mono">{{ $counter->counter_code }}</p>
                    </div>
                    <span class="{{ $hasRates ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }} text-xs px-2 py-1 rounded-full">
                        {{ $hasRates ? $rateCount . ' สกุลเงิน' : 'ยังไม่มีราคา' }}
                    </span>
                </div>
                <div class="flex gap-2 mt-3">
                    <a href="{{ route('admin.rate.setup', $counter) }}"
                       class="flex-1 text-center px-3 py-2 bg-[#0e513a] text-white text-sm rounded hover:bg-[#0a3d2d]">
                        ตั้งราคา
                    </a>
                    <a href="{{ route('rate.board', $counter->counter_code) }}" target="_blank"
                       class="flex-1 text-center px-3 py-2 bg-gray-700 text-white text-sm rounded hover:bg-gray-600">
                        จอแสดงราคา
                    </a>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
