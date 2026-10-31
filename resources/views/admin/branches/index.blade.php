@extends('layouts.app')

@section('title', 'จัดการสาขา (Branches)')

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-semibold text-gray-800">จัดการสาขา (Branch Management)</h2>
        <p class="text-sm text-gray-500">รายการสาขาและเคาน์เตอร์ทั้งหมดในระบบ</p>
    </div>
    {{-- มีบริษัทแล้วปุ่มเพิ่มสาขาอยู่ประจำแต่ละบริษัทแทน --}}
    @if ($companies->isEmpty())
        <a href="{{ route('admin.branches.create') }}"
           class="bg-[#0e513a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
            + เพิ่มสาขา
        </a>
    @endif
</div>

@if (session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
        {{ session('success') }}
    </div>
@endif

@php
    // กลุ่มละบริษัท สาขาที่ยังไม่มีบริษัทอยู่ท้ายสุด
    $groups = $companies->map(fn ($company) => [
        'company' => $company,
        'branches' => $branches->get($company->id, collect()),
    ]);
    if ($looseBranches->isNotEmpty()) {
        $groups->push(['company' => null, 'branches' => $looseBranches]);
    }
@endphp

<div class="space-y-6">
@foreach ($groups as $group)
    <section>
        <div class="flex items-baseline gap-3 mb-3 pb-2 border-b border-gray-200">
            @if ($group['company'])
                <h3 class="text-base font-semibold text-[#0e513a]">{{ $group['company']->company_name }}</h3>
                <span class="text-xs text-gray-400 font-mono">{{ $group['company']->company_code }}</span>
                @unless ($group['company']->is_active)
                    <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">ปิดใช้งาน</span>
                @endunless
            @else
                <h3 class="text-base font-semibold text-amber-700">ยังไม่ได้ผูกบริษัท</h3>
            @endif
            <span class="text-xs text-gray-500">{{ $group['branches']->count() }} สาขา</span>
            @if ($group['company']?->is_active)
                <a href="{{ route('admin.branches.create', ['company_id' => $group['company']->id]) }}"
                   class="ml-auto self-center bg-[#0e513a] text-white px-3 py-1.5 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
                    + เพิ่มสาขา
                </a>
            @endif
        </div>

        @if ($group['branches']->isEmpty())
            <p class="text-sm text-gray-400">ยังไม่มีสาขาในบริษัทนี้</p>
        @else
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($group['branches'] as $branch)
                <div class="bg-white rounded-lg shadow border border-gray-200 p-5">
                    <div class="flex justify-between items-start mb-3">
                        <div>
                            <h3 class="font-bold text-gray-800 text-base">{{ $branch->branch_name }}</h3>
                            <p class="text-xs text-gray-400 font-mono">{{ $branch->branch_code }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2 py-0.5 rounded-full {{ $branch->type === 'hq' ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700' }}">
                                {{ $branch->type === 'hq' ? 'สำนักงานใหญ่' : 'สาขา' }}
                            </span>
                            @if ($branch->is_active)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">เปิด</span>
                            @else
                                <span class="text-xs px-2 py-0.5 rounded-full bg-red-100 text-red-700">ปิด</span>
                            @endif
                        </div>
                    </div>

                    <div class="text-sm text-gray-600 space-y-1 mb-3">
                        @if ($branch->city)
                            <div>เมือง: {{ $branch->city }}</div>
                        @endif
                        @if ($branch->phone)
                            <div>โทร: {{ $branch->phone }}</div>
                        @endif
                        {{-- แสดงเฉพาะสาขาที่ตั้งค่าเอง เพื่อให้เห็นได้ทันทีว่าสาขาไหนต่างจากค่ากลาง --}}
                        @if ($branch->cut_off_time)
                            <div>ตัดรอบ: {{ substr($branch->cut_off_time, 0, 5) }}</div>
                        @endif
                        <div class="flex gap-4 text-xs text-gray-400">
                            <span>{{ $branch->counters_count }} เคาน์เตอร์</span>
                            <span>{{ $branch->users_count }} ผู้ใช้</span>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <a href="{{ route('admin.branches.edit', $branch) }}"
                           class="flex-1 text-center px-3 py-1.5 bg-[#0e513a] text-white text-sm rounded hover:bg-[#0a3d2d]">
                            แก้ไข
                        </a>
                        <form action="{{ route('admin.branches.toggle-active', $branch) }}" method="POST" class="flex-1">
                            @csrf @method('PATCH')
                            <button type="submit"
                                    class="w-full px-3 py-1.5 text-sm rounded {{ $branch->is_active ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100' }}">
                                {{ $branch->is_active ? 'ปิดใช้งาน' : 'เปิดใช้งาน' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
        @endif
    </section>
@endforeach
</div>
@endsection
