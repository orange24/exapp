@extends('layouts.app')
@section('title', 'สมุดรายวัน (Journal)')

@section('content')
<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6" style="color:#0e513a;">สมุดรายวัน (Journal Entries)</h1>
    <livewire:accounting.journal-entry-manager />
</div>
@endsection
