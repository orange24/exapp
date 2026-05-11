@extends('layouts.app')

@section('title', 'ประวัติเคลื่อนไหว (Stock Movements)')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">ประวัติการเคลื่อนไหวสินค้า (Stock Movements)</h2>
    </div>
    <livewire:inventory.stock-movements />
</div>
@endsection
