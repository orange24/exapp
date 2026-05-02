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

<div class="bg-white rounded-xl shadow border border-gray-200 p-6">
    <h2 class="text-lg font-bold text-gray-800 mb-2">ยินดีต้อนรับ, {{ auth()->user()->name }}</h2>
    <p class="text-sm text-gray-500">
        สาขา: <strong>{{ auth()->user()->branch?->branch_name ?? '—' }}</strong>
        &nbsp;|&nbsp; เคาน์เตอร์: <strong>{{ session('working_counter_name', '—') }}</strong>
        &nbsp;|&nbsp; บทบาท: <strong>{{ auth()->user()->role?->display_name ?? '—' }}</strong>
        &nbsp;|&nbsp; วันที่: <strong>{{ now()->format('d/m/Y H:i') }}</strong>
    </p>
</div>
@endsection
