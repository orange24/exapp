@extends('layouts.app')

@section('title', 'สต็อกเงินตรา (Inventory)')

@section('content')
<div class="max-w-7xl mx-auto">
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">สรุปรายการเคลื่อนไหวของสินค้า (Inventory)</h2>
    </div>
    <livewire:inventory.inventory-dashboard />
</div>
@endsection
