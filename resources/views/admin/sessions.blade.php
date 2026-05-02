@extends('layouts.app')

@section('title', 'จัดการ Session ผู้ใช้')

@section('content')
<div class="max-w-6xl mx-auto">
    <div class="bg-white rounded-xl shadow border border-gray-200">
        <livewire:admin.session-manager />
    </div>
</div>
@endsection
