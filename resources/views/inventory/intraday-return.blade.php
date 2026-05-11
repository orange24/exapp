@extends('layouts.app')
@section('title', 'คืนสินค้าระหว่างวัน (Intra-day Return)')

@section('content')
<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6" style="color:#0e513a;">คืนสินค้าระหว่างวัน (Intra-day Return)</h1>
    <livewire:inventory.stock-transfer-form type="intraday_return" />
</div>
@endsection
