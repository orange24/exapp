@extends('layouts.app')

@section('title', 'ขายธนาคาร (Sell to Bank)')

@section('content')
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">ขายเงินตราให้ธนาคาร (Sell to Bank)</h2>
        <p class="text-sm text-gray-500">Reserve สต็อกจากหลายสาขา → ขนส่ง → ยืนยันขาย → ตัดสต็อก + GL Journal + P&L</p>
    </div>

    <livewire:rate.bank-sale-manager />
@endsection
