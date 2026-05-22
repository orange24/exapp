@extends('layouts.app')

@section('title', 'SuperRich อัตราอ้างอิง')

@section('content')
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">อัตราอ้างอิง SuperRich (SuperRich Reference Rates)</h2>
        <p class="text-sm text-gray-500">ดึงราคาจาก SuperRich Thailand → ปรับ Adjustment → สร้างชุดราคา → อนุมัติ → กระจายไปสาขา</p>
    </div>

    <livewire:rate.superrich-rate-manager />
@endsection
