@extends('layouts.app')

@section('title', 'ค้นหารายการ (Search Transactions)')

@section('content')
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">ค้นหารายการ (Search Transactions)</h2>
        <p class="text-sm text-gray-500">ค้นหารายการธุรกรรมทุกสาขา กรองตามเคาน์เตอร์/ประเภท/สถานะ และอนุมัติยกเลิกได้</p>
    </div>

    <livewire:transaction.all-transactions />
@endsection
