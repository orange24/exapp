@extends('layouts.app')

@section('title', 'หน้าหลัก')

@section('content')
{{-- Counter Selection Modal --}}
<div x-data="counterSelector()" x-init="init()">
    {{-- Modal overlay --}}
    <div x-show="showModal" x-cloak
         style="position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center;">
        <div style="background:#fff; border-radius:16px; padding:32px; width:100%; max-width:480px; box-shadow:0 20px 60px rgba(0,0,0,0.3);"
             @click.away="">
            {{-- Header --}}
            <div style="text-align:center; margin-bottom:24px;">
                <div style="width:56px; height:56px; background:#EEF2FF; border-radius:14px; display:flex; align-items:center; justify-content:center; margin:0 auto 12px;">
                    <svg style="width:28px; height:28px; color:#0e513a;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <h2 style="font-size:20px; font-weight:700; color:#0e513a;">เลือกเคาน์เตอร์ทำงาน</h2>
                <p style="font-size:13px; color:#888; margin-top:4px;">กรุณาเลือกเคาน์เตอร์ที่คุณจะใช้งานวันนี้</p>
            </div>

            {{-- Counter list --}}
            <div style="display:flex; flex-direction:column; gap:8px; max-height:360px; overflow-y:auto; margin-bottom:20px;">
                @php
                    $availableCounters = auth()->user()->isAdmin()
                        ? \App\Models\Counter::where('is_active', true)->with('branch')->orderBy('branch_id')->get()
                        : \App\Models\Counter::where('is_active', true)->where('branch_id', auth()->user()->branch_id)->with('branch')->get();
                @endphp
                @foreach ($availableCounters as $c)
                    <button type="button"
                            @click="selectCounter({{ $c->id }}, '{{ addslashes($c->counter_name) }}', '{{ addslashes($c->branch->branch_name ?? '') }}')"
                            :style="selectedId == {{ $c->id }}
                                ? 'background:#0e513a; color:#fff; border:2px solid #0e513a; border-radius:10px; padding:12px 16px; text-align:left; cursor:pointer; transition:all 0.15s;'
                                : 'background:#fff; color:#333; border:2px solid #e5e7eb; border-radius:10px; padding:12px 16px; text-align:left; cursor:pointer; transition:all 0.15s;'"
                            onmouseover="if(this.style.background!='rgb(30, 58, 95)')this.style.borderColor='#0e513a'"
                            onmouseout="if(this.style.background!='rgb(30, 58, 95)')this.style.borderColor='#e5e7eb'">
                        <div style="font-weight:600; font-size:15px;">{{ $c->counter_name }}</div>
                        <div style="font-size:12px; opacity:0.7;">{{ $c->branch->branch_name ?? '' }}</div>
                    </button>
                @endforeach
            </div>

            {{-- Confirm button --}}
            <button type="button" @click="confirm()"
                    :disabled="!selectedId"
                    :style="selectedId
                        ? 'width:100%; padding:12px; background:#0e513a; color:#fff; border:none; border-radius:10px; font-size:16px; font-weight:600; cursor:pointer;'
                        : 'width:100%; padding:12px; background:#ccc; color:#888; border:none; border-radius:10px; font-size:16px; font-weight:600; cursor:not-allowed;'">
                ยืนยัน
            </button>
        </div>
    </div>
</div>

<script>
function counterSelector() {
    return {
        showModal: false,
        selectedId: null,
        selectedName: '',

        init() {
            // Check cookie — if working_counter cookie exists, don't show modal
            const cookie = this.getCookie('working_counter_id');
            if (cookie) {
                // Already selected, set session via AJAX silently
                this.syncSession(parseInt(cookie), this.getCookie('working_counter_name') || '');
                return;
            }
            // No cookie — check role
            @if (!auth()->user()->isAdmin())
                // Staff: if only 1 counter in their branch, auto-select without modal
                @php
                    $staffCounters = \App\Models\Counter::where('is_active', true)->where('branch_id', auth()->user()->branch_id)->get();
                @endphp
                @if ($staffCounters->count() === 1)
                    // Auto-select the only counter
                    this.selectedId = {{ $staffCounters->first()->id }};
                    this.selectedName = '{{ addslashes($staffCounters->first()->counter_name) }}';
                    this.confirm();
                @else
                    this.showModal = true;
                @endif
            @else
                // Admin: show modal to choose
                @if (!session('working_counter_id'))
                    this.showModal = true;
                @endif
            @endif
        },

        selectCounter(id, name, branch) {
            this.selectedId = id;
            this.selectedName = name;
        },

        confirm() {
            if (!this.selectedId) return;

            // Save to cookie (365 days)
            this.setCookie('working_counter_id', this.selectedId, 365);
            this.setCookie('working_counter_name', this.selectedName, 365);

            // Save to session via form POST
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route("switch-counter") }}';
            form.innerHTML = '<input type="hidden" name="_token" value="{{ csrf_token() }}">'
                           + '<input type="hidden" name="counter_id" value="' + this.selectedId + '">';
            document.body.appendChild(form);
            form.submit();
        },

        syncSession(id, name) {
            // If session already matches cookie, skip
            @if (session('working_counter_id'))
                return;
            @endif
            // POST to sync cookie → session
            fetch('{{ route("switch-counter") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ counter_id: id })
            }).then(() => {
                // Reload to reflect in sidebar
                window.location.reload();
            });
        },

        getCookie(name) {
            const v = document.cookie.match('(^|;)\\s*' + name + '\\s*=\\s*([^;]+)');
            return v ? decodeURIComponent(v.pop()) : null;
        },

        setCookie(name, value, days) {
            const d = new Date();
            d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + '=' + encodeURIComponent(value) + ';expires=' + d.toUTCString() + ';path=/;SameSite=Lax';
        }
    }
}
</script>

@php
    $counterId = session('working_counter_id');
    $cutoff = \App\Models\Setting::get('WORKING_CUT_OFF', '03:00:00');
    $today = now()->format('Y-m-d');
    $dateStart = "{$today} {$cutoff}";
    $dateEnd = now()->addDay()->format('Y-m-d') . " {$cutoff}";

    // Today's transactions for this counter
    $todayQuery = \App\Models\TransactionMaster::where('flag_cancel', 'N')
        ->where('trns_datetime', '>', $dateStart)
        ->where('trns_datetime', '<=', $dateEnd);
    if ($counterId) {
        $todayQuery->where('counter_id', $counterId);
    }

    $buyCount = (clone $todayQuery)->where('trns_type', 'BUYING')->count();
    $sellCount = (clone $todayQuery)->where('trns_type', 'SELLING')->count();

    $buyTotal = \App\Models\TransactionDetail::whereIn('transaction_id',
        (clone $todayQuery)->where('trns_type', 'BUYING')->pluck('id')
    )->sum('total');

    $sellTotal = \App\Models\TransactionDetail::whereIn('transaction_id',
        (clone $todayQuery)->where('trns_type', 'SELLING')->pluck('id')
    )->sum('total');

    // Pending cancellations
    $pendingCancel = \App\Models\TransactionMaster::where('flag_cancel', 'R')->count();

    // Active bookings
    $activeBookings = \App\Models\Booking::where('status', 'pending')
        ->where('expires_at', '>', now())->count();

    // Stock value for this counter
    $stockValue = 0;
    if ($counterId) {
        $stockValue = \App\Models\CounterStock::where('counter_id', $counterId)
            ->whereNotNull('denomination_id')
            ->selectRaw('SUM(quantity * avg_cost) as total')
            ->value('total') ?? 0;
    }

    // Top currencies by volume today
    $topCurrencies = collect();
    if ($counterId) {
        $topCurrencies = \App\Models\TransactionDetail::whereIn('transaction_id',
            (clone $todayQuery)->pluck('id')
        )
        ->selectRaw('currency_code, SUM(amount) as total_amount, SUM(total) as total_thb')
        ->groupBy('currency_code')
        ->orderByDesc('total_thb')
        ->limit(5)
        ->get();
    }
@endphp

{{-- Welcome bar --}}
<div class="bg-white rounded-xl shadow border border-gray-200 p-4 mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-lg font-bold text-gray-800">{{ auth()->user()->name }}</h2>
            <p class="text-sm text-gray-500">
                สาขา: <strong>{{ auth()->user()->branch?->branch_name ?? '—' }}</strong>
                &nbsp;|&nbsp; เคาน์เตอร์: <strong>{{ session('working_counter_name', '—') }}</strong>
                &nbsp;|&nbsp; บทบาท: <strong>{{ auth()->user()->role?->display_name ?? '—' }}</strong>
            </p>
        </div>
        <div class="text-right text-sm text-gray-500">
            <div class="text-lg font-bold" style="color:#0e513a;">{{ now()->format('d/m/Y') }}</div>
            <div>{{ now()->format('H:i') }} น.</div>
        </div>
    </div>
</div>

{{-- Quick Actions --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <a href="{{ route('transaction.buy') }}"
       class="bg-white rounded-xl shadow border border-gray-200 p-6 flex items-center gap-4 hover:shadow-md transition-shadow group">
        <div class="w-12 h-12 bg-green-100 rounded-xl flex items-center justify-center group-hover:bg-green-200 transition-colors">
            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-green-700">รับซื้อ</p>
            <p class="text-sm text-gray-500">Buy Foreign Currency</p>
        </div>
    </a>

    <a href="{{ route('transaction.sell') }}"
       class="bg-white rounded-xl shadow border border-gray-200 p-6 flex items-center gap-4 hover:shadow-md transition-shadow group">
        <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center group-hover:bg-red-200 transition-colors">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-red-700">จ่ายขาย</p>
            <p class="text-sm text-gray-500">Sell Foreign Currency</p>
        </div>
    </a>

    <a href="{{ route('admin.rate.index') }}"
       class="bg-white rounded-xl shadow border border-gray-200 p-6 flex items-center gap-4 hover:shadow-md transition-shadow group">
        <div class="w-12 h-12 bg-blue-100 rounded-xl flex items-center justify-center group-hover:bg-blue-200 transition-colors">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
        </div>
        <div>
            <p class="text-2xl font-bold text-blue-700">อัตราแลกเปลี่ยน</p>
            <p class="text-sm text-gray-500">Exchange Rates</p>
        </div>
    </a>
</div>

{{-- Metrics Cards --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    {{-- Buy Today --}}
    <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm text-gray-500">ซื้อวันนี้</span>
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-green-100">
                <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
            </span>
        </div>
        <div class="text-2xl font-bold text-green-700">{{ $buyCount }}</div>
        <div class="text-xs text-gray-400 mt-1">{{ number_format($buyTotal, 2) }} THB</div>
    </div>

    {{-- Sell Today --}}
    <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm text-gray-500">ขายวันนี้</span>
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-red-100">
                <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>
                </svg>
            </span>
        </div>
        <div class="text-2xl font-bold text-red-700">{{ $sellCount }}</div>
        <div class="text-xs text-gray-400 mt-1">{{ number_format($sellTotal, 2) }} THB</div>
    </div>

    {{-- Stock Value --}}
    <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm text-gray-500">มูลค่าสต็อก</span>
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-blue-100">
                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
            </span>
        </div>
        <div class="text-2xl font-bold text-blue-700">{{ number_format($stockValue, 0) }}</div>
        <div class="text-xs text-gray-400 mt-1">THB</div>
    </div>

    {{-- Alerts --}}
    <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
        <div class="flex items-center justify-between mb-2">
            <span class="text-sm text-gray-500">แจ้งเตือน</span>
            <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-yellow-100">
                <svg class="w-4 h-4 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
            </span>
        </div>
        <div class="flex flex-col gap-1">
            @if ($pendingCancel > 0)
                <a href="{{ route('admin.transactions') }}" class="text-xs text-yellow-700 hover:underline">
                    รอยกเลิก: <strong>{{ $pendingCancel }}</strong> รายการ
                </a>
            @endif
            @if ($activeBookings > 0)
                <a href="{{ route('inventory.booking') }}" class="text-xs text-purple-700 hover:underline">
                    Booking: <strong>{{ $activeBookings }}</strong> รายการ
                </a>
            @endif
            @if ($pendingCancel == 0 && $activeBookings == 0)
                <span class="text-xs text-gray-400">ไม่มีรายการค้าง</span>
            @endif
        </div>
    </div>
</div>

{{-- Top currencies today --}}
@if ($topCurrencies->isNotEmpty())
<div class="bg-white rounded-xl shadow border border-gray-200 p-5">
    <h3 class="text-sm font-semibold text-gray-700 mb-3">สกุลเงินที่มีปริมาณสูงสุดวันนี้</h3>
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
        @foreach ($topCurrencies as $tc)
            <div class="text-center p-3 bg-gray-50 rounded-lg">
                <div class="text-lg font-bold" style="color:#0e513a;">{{ $tc->currency_code }}</div>
                <div class="text-sm text-gray-600">{{ number_format($tc->total_amount, 2) }}</div>
                <div class="text-xs text-gray-400">{{ number_format($tc->total_thb, 0) }} THB</div>
            </div>
        @endforeach
    </div>
</div>
@endif
@endsection
