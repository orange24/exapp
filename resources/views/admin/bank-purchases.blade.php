@extends('layouts.app')

@section('title', 'ซื้อจากธนาคาร (Buy from Bank)')

@section('content')
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">ซื้อเงินตราจากธนาคาร (Buy from Bank)</h2>
        <p class="text-sm text-gray-500">สร้างรายการ → ยืนยันรับของ → สต็อกเข้าเคาน์เตอร์ปลายทาง + คำนวณ Avg Cost ใหม่ + GL Journal</p>
    </div>

    <livewire:rate.bank-sale-manager direction="buy" />
@endsection
