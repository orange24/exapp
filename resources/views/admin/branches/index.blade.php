@extends('layouts.app')

@section('title', 'จัดการสาขา (Branches)')

@section('content')
<div class="flex items-center justify-between mb-4">
    <div>
        <h2 class="text-lg font-semibold text-gray-800">จัดการสาขา (Branch Management)</h2>
        <p class="text-sm text-gray-500">รายการสาขาและเคาน์เตอร์ทั้งหมดในระบบ</p>
    </div>
    <a href="{{ route('admin.branches.create') }}"
       class="bg-[#0e513a] text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
        + เพิ่มสาขา
    </a>
</div>

@if (session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">
        {{ session('success') }}
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @foreach ($branches as $branch)
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
@endsection
