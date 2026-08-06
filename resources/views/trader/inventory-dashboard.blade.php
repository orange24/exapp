@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-6">
    <div class="mb-6">
        <h1 class="text-2xl font-bold" style="color:#0e513a;">Inventory Dashboard</h1>
        <p class="text-gray-600">ภาพรวม Stock ของสาขาที่ดูแล</p>
    </div>

    @livewire('trader.inventory-dashboard')
</div>
@endsection
