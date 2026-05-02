@extends('layouts.app')

@section('title', 'ตั้งราคา — ' . $counter->counter_name)

@section('content')
<div class="max-w-5xl mx-auto py-6 px-4">
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ route('admin.rate.index') }}" class="text-blue-600 hover:underline text-sm">
            ← กลับหน้ารายการ
        </a>
        <span class="text-gray-400">/</span>
        <h1 class="text-2xl font-bold text-gray-800">ตั้งราคา — {{ $counter->counter_name }}</h1>
    </div>

    <div class="bg-white rounded-lg shadow border border-gray-200">
        <livewire:rate.rate-setup-board :counter-id="$counter->id" />
    </div>
</div>
@endsection
