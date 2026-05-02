@extends('layouts.app')

@section('title', 'รับซื้อเงินตราต่างประเทศ')

@section('content')
<div class="max-w-5xl mx-auto">
    <div class="bg-white rounded-xl shadow border border-gray-200">
        <livewire:transaction.buy-form />
    </div>
</div>
@endsection
