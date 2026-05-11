@extends('layouts.app')
@section('title', 'เบิกจ่ายเงิน (Disbursement)')

@section('content')
<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6" style="color:#0e513a;">เบิกจ่ายเงินให้เคาน์เตอร์ (Cash Disbursement)</h1>
    <livewire:inventory.stock-transfer-form type="disbursement" />
</div>
@endsection
