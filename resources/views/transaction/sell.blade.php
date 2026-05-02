@extends('layouts.app')

@section('title', 'จ่ายขายเงินตราต่างประเทศ')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-xl shadow border border-gray-200">
        <livewire:transaction.sell-form />
    </div>
</div>
@endsection
