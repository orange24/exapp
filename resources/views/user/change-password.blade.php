@extends('layouts.app')

@section('title', 'เปลี่ยนรหัสผ่าน (Change Password)')

@section('content')
    <div class="mb-4">
        <h2 class="text-lg font-semibold text-gray-800">เปลี่ยนรหัสผ่าน (Change Password)</h2>
        <p class="text-sm text-gray-500">กรุณากรอกข้อมูลด้านล่างเพื่อเปลี่ยนรหัสผ่านของคุณ</p>
    </div>

    <livewire:user.change-password />
@endsection
