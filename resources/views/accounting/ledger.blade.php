@extends('layouts.app')
@section('title', 'บัญชีแยกประเภท (General Ledger)')

@section('content')
<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold mb-6" style="color:#0e513a;">บัญชีแยกประเภท / งบทดลอง (Ledger / Trial Balance)</h1>
    <livewire:accounting.general-ledger />
</div>
@endsection
