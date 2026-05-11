@extends('layouts.app')
@section('title', 'จัดการข้อมูลรายงาน ธปท.')

@section('content')
<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6" style="color:#0e513a;">จัดการข้อมูลรายงาน ธปท. (BOT Report Staging)</h1>
    <livewire:report.bot-report-manager />
</div>
@endsection
