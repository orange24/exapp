@extends('layouts.app')
@section('title', 'ยืม/คืนสินค้า (Borrow/Return)')

@section('content')
<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6" style="color:#0e513a;">ยืม/คืนสินค้า (Borrow/Return)</h1>
    <livewire:inventory.stock-transfer-form type="borrow" />
</div>
@endsection
