@extends('layouts.app')

@section('title', 'รายการของฉัน (My Transactions)')

@section('content')
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">รายการของฉัน (My Transactions)</h2>
        <p class="text-sm text-gray-500">แสดงรายการธุรกรรมที่คุณบันทึก สามารถค้นหาและขอยกเลิกได้</p>
    </div>

    <livewire:transaction.my-transactions />
@endsection
