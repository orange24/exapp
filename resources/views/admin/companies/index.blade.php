@extends('layouts.app')

@section('title', 'จัดการบริษัท (Companies)')

@section('content')
<div class="flex items-start justify-between mb-5">
    <div>
        <h1 class="text-2xl font-bold" style="color:#0e513a">จัดการบริษัท (Company Management)</h1>
        <p class="text-sm text-gray-500 mt-1">
            บริษัทเป็นเจ้าของสาขา สต็อก และบัญชี — ข้อมูลของแต่ละบริษัทแยกกันคนละชุด
        </p>
    </div>
    <a href="{{ route('admin.companies.create') }}"
       class="px-4 py-2 rounded text-white text-sm font-medium" style="background-color:#0e513a">
        + เพิ่มบริษัท
    </a>
</div>

@if ($companies->isEmpty())
    <div class="bg-white rounded-xl shadow border border-gray-200 p-8 text-center">
        <p class="text-gray-500">ยังไม่มีบริษัทในระบบ</p>
    </div>
@else
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    @foreach ($companies as $company)
        <div class="bg-white rounded-xl shadow border border-gray-200 p-4">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0">
                    <div class="font-bold truncate" style="color:#0e513a">{{ $company->company_name }}</div>
                    <div class="text-xs text-gray-500">{{ $company->company_code }}</div>
                </div>
                <span class="text-xs px-2 py-0.5 rounded-full whitespace-nowrap
                    {{ $company->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-600' }}">
                    {{ $company->is_active ? 'เปิด' : 'ปิด' }}
                </span>
            </div>

            <div class="mt-3 text-sm text-gray-600 space-y-1">
                @if ($company->tax_id)
                    <div>เลขผู้เสียภาษี: {{ $company->tax_id }}</div>
                @endif
                @if ($company->license_no)
                    <div>เลขใบอนุญาต: {{ $company->license_no }}</div>
                @endif
                <div class="text-xs text-gray-500">
                    {{ $company->branches_count }} สาขา
                    @if ($company->branches_count !== $company->active_branches_count)
                        <span class="text-gray-400">(เปิดอยู่ {{ $company->active_branches_count }})</span>
                    @endif
                </div>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <a href="{{ route('admin.companies.edit', $company) }}"
                   class="text-center px-3 py-2 rounded text-white text-sm" style="background-color:#0e513a">
                    แก้ไข
                </a>
                <form method="POST" action="{{ route('admin.companies.toggle-active', $company) }}">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="w-full px-3 py-2 rounded text-sm
                                {{ $company->is_active ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' }}">
                        {{ $company->is_active ? 'ปิดใช้งาน' : 'เปิดใช้งาน' }}
                    </button>
                </form>
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection
