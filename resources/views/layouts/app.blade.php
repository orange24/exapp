<!DOCTYPE html>
<html lang="th" class="h-full bg-gray-100">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Exchange System') — Exchange System</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <script src="https://cdn.tailwindcss.com"></script>
    @livewireStyles
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background-color 0.15s;
            white-space: nowrap;
        }
        .sidebar-link { color: #0e513a; }
        .sidebar-link:hover { background-color: #f0f0f0; }
        .sidebar-link.active { background-color: #e0efe8; }
    </style>
</head>
<body class="h-full flex" x-data="{ sidebarOpen: true }">

    {{-- Sidebar --}}
    <aside class="w-64 bg-white flex flex-col"
           :class="sidebarOpen ? 'w-64' : 'w-16'" style="transition: width 0.2s; min-height:100vh; border-right:1px solid #e5e7eb;">
        {{-- Logo / Brand --}}
        <div class="p-3 flex items-center justify-center" style="border-bottom:1px solid #e5e7eb;">
            <img x-show="sidebarOpen" src="{{ asset('images/logo-mini.png') }}" alt="Logo" style="height:40px; object-fit:contain;">
            <img x-show="!sidebarOpen" src="{{ asset('images/logo-mini.png') }}" alt="Logo" class="w-8 h-8" style="object-fit:contain;">
        </div>

        {{-- Working Counter Selector --}}
        @php
            $workingName = session('working_counter_name');
            if (!$workingName) {
                $defaultCounter = \App\Models\Counter::where('branch_id', auth()->user()->branch_id)->where('is_active', true)->first();
                $workingName = $defaultCounter?->counter_name ?? auth()->user()->branch?->branch_name ?? '—';
            }
            $canSwitchCounter = auth()->user()->isAdmin();
        @endphp
        <div x-show="sidebarOpen" class="px-3 py-2" x-data="{ showSwitch: false }" style="border-bottom:1px solid #e5e7eb;">
            <div class="text-xs mb-1" style="color:#888;">เคาน์เตอร์ทำงาน</div>

            @if ($canSwitchCounter)
                {{-- Admin: can switch counter --}}
                <button @click="showSwitch = !showSwitch"
                        class="w-full text-left flex items-center justify-between gap-2 px-2 py-1.5 rounded text-sm font-semibold hover:bg-gray-100 transition-colors" style="color:#0e513a;">
                    <span>{{ $workingName }}</span>
                    <svg class="w-4 h-4 text-[#0e513a] transition-transform" :class="showSwitch && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="showSwitch" x-cloak x-transition class="mt-1">
                    <form method="POST" action="{{ route('switch-counter') }}">
                        @csrf
                        <select name="counter_id"
                                onchange="var opt=this.options[this.selectedIndex]; var d=new Date(); d.setTime(d.getTime()+365*24*60*60*1000); document.cookie='working_counter_id='+this.value+';expires='+d.toUTCString()+';path=/;SameSite=Lax'; document.cookie='working_counter_name='+encodeURIComponent(opt.dataset.name)+';expires='+d.toUTCString()+';path=/;SameSite=Lax'; this.form.submit();"
                                class="w-full text-xs text-gray-800 bg-white border border-gray-300 rounded px-2 py-1.5">
                            @foreach (\App\Models\Counter::where('is_active', true)->with('branch')->orderBy('branch_id')->get() as $c)
                                <option value="{{ $c->id }}"
                                    data-name="{{ $c->counter_name }}"
                                    {{ session('working_counter_id') == $c->id ? 'selected' : '' }}>
                                    {{ $c->counter_name }} — {{ $c->branch->branch_name ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                </div>
            @else
                {{-- Staff: show counter name only, no switching --}}
                <div class="px-2 py-1.5 text-sm font-semibold" style="color:#0e513a;">
                    {{ $workingName }}
                </div>
            @endif
        </div>

        {{-- Nav --}}
        @php
            $hiddenMenus = config('app.hidden_menus', '');
            $hiddenList = $hiddenMenus ? array_map('trim', preg_split('/[,|]/', $hiddenMenus)) : [];
            // Helper: check if a route name is visible (not hidden)
            $menuOn = function(string $route) use ($hiddenList) {
                return !in_array($route, $hiddenList);
            };
            // Helper: check if any route in a section is visible
            $sectionOn = function(array $routes) use ($hiddenList) {
                foreach ($routes as $r) { if (!in_array($r, $hiddenList)) return true; }
                return true;
            };
        @endphp
        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            <a href="{{ route('dashboard') }}"
               class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span x-show="sidebarOpen">หน้าหลัก</span>
            </a>

            {{-- Transactions --}}
            @if($sectionOn(['transaction.buy', 'transaction.sell', 'transaction.my']))
            <div class="pt-2" x-data="{ open: {{ request()->routeIs('transaction.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" x-show="sidebarOpen" type="button"
                        class="w-full flex items-center justify-between px-2 py-1 text-xs font-semibold text-[#0e513a] uppercase tracking-wider hover:text-[#137050] transition-colors">
                    <span>ธุรกรรม</span>
                    <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition>
                @if($menuOn('transaction.buy'))
                <a href="{{ route('transaction.buy') }}"
                   class="sidebar-link {{ request()->routeIs('transaction.buy') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4v16m8-8H4"/>
                    </svg>
                    <span x-show="sidebarOpen">รับซื้อ (Buy)</span>
                </a>
                @endif
                @if($menuOn('transaction.sell'))
                <a href="{{ route('transaction.sell') }}"
                   class="sidebar-link {{ request()->routeIs('transaction.sell') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20 12H4"/>
                    </svg>
                    <span x-show="sidebarOpen">จ่ายขาย (Sell)</span>
                </a>
                @endif
                @if($menuOn('transaction.my'))
                <a href="{{ route('transaction.my') }}"
                   class="sidebar-link {{ request()->routeIs('transaction.my') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0 text-[#0e513a]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                    <span x-show="sidebarOpen">รายการของฉัน (My Trans)</span>
                </a>
                @endif
                </div>
            </div>
            @endif

            {{-- Rates --}}
            @if($sectionOn(['admin.rate.index', 'admin.rate-settings.index', 'rate.board']))
            <div class="pt-2" x-data="{ open: {{ request()->routeIs('admin.rate.*') || request()->routeIs('admin.rate-settings.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" x-show="sidebarOpen" type="button"
                        class="w-full flex items-center justify-between px-2 py-1 text-xs font-semibold text-[#0e513a] uppercase tracking-wider hover:text-[#137050] transition-colors">
                    <span>อัตราแลกเปลี่ยน</span>
                    <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition>
                @if($menuOn('admin.rate.index'))
                <a href="{{ route('admin.rate.index') }}"
                   class="sidebar-link {{ request()->routeIs('admin.rate.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    <span x-show="sidebarOpen">ตั้งราคา</span>
                </a>
                @endif
                @if($menuOn('admin.rate-settings.index'))
                <a href="{{ route('admin.rate-settings.index') }}"
                   class="sidebar-link {{ request()->routeIs('admin.rate-settings.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.573-1.066z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span x-show="sidebarOpen">ตั้งค่าคำนวณ (Rate Calc)</span>
                </a>
                @endif
                @if($menuOn('rate.board'))
                @php
                    $workingCounterCode = null;
                    $wcId = session('working_counter_id');
                    if ($wcId) {
                        $workingCounterCode = \App\Models\Counter::where('id', $wcId)->value('counter_code');
                    }
                @endphp
                @if ($workingCounterCode)
                <a href="{{ route('rate.board', $workingCounterCode) }}" target="_blank"
                   class="sidebar-link">
                    <svg class="w-5 h-5 flex-shrink-0 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span x-show="sidebarOpen">ดูเรทสาขา</span>
                </a>
                @endif
                @endif
                </div>
            </div>
            @endif

            {{-- Inventory --}}
            @if($sectionOn(['inventory.index', 'inventory.movements', 'inventory.borrow-return', 'inventory.disbursement', 'inventory.intraday-return', 'inventory.open-close-day', 'inventory.booking', 'inventory.adjustment']))
            <div class="pt-2" x-data="{ open: {{ request()->routeIs('inventory.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" x-show="sidebarOpen" type="button"
                        class="w-full flex items-center justify-between px-2 py-1 text-xs font-semibold text-[#0e513a] uppercase tracking-wider hover:text-[#137050] transition-colors">
                    <span>คลังเงินตรา</span>
                    <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition>
                @if($menuOn('inventory.index'))
                <a href="{{ route('inventory.index') }}"
                   class="sidebar-link {{ request()->routeIs('inventory.index') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span x-show="sidebarOpen">สต็อกเงินตรา (Inventory)</span>
                </a>
                @endif
                @if($menuOn('inventory.movements'))
                <a href="{{ route('inventory.movements') }}"
                   class="sidebar-link {{ request()->routeIs('inventory.movements') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                    </svg>
                    <span x-show="sidebarOpen">ประวัติเคลื่อนไหว (Movements)</span>
                </a>
                @endif
                @if($menuOn('inventory.borrow-return'))
                <a href="{{ route('inventory.borrow-return') }}"
                   class="sidebar-link {{ request()->routeIs('inventory.borrow-return') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    <span x-show="sidebarOpen">ยืม/คืนสินค้า (Borrow)</span>
                </a>
                @endif
                @if($menuOn('inventory.disbursement'))
                <a href="{{ route('inventory.disbursement') }}"
                   class="sidebar-link {{ request()->routeIs('inventory.disbursement') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/>
                    </svg>
                    <span x-show="sidebarOpen">เบิกจ่าย (Disbursement)</span>
                </a>
                @endif
                @if($menuOn('inventory.intraday-return'))
                <a href="{{ route('inventory.intraday-return') }}"
                   class="sidebar-link {{ request()->routeIs('inventory.intraday-return') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/>
                    </svg>
                    <span x-show="sidebarOpen">คืนระหว่างวัน (Return)</span>
                </a>
                @endif
                @if($menuOn('inventory.open-close-day'))
                <a href="{{ route('inventory.open-close-day') }}"
                   class="sidebar-link {{ request()->routeIs('inventory.open-close-day') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span x-show="sidebarOpen">เปิด/ปิดวัน (Open/Close)</span>
                </a>
                @endif
                @if($menuOn('inventory.booking'))
                <a href="{{ route('inventory.booking') }}"
                   class="sidebar-link {{ request()->routeIs('inventory.booking') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span x-show="sidebarOpen">จอง/Hold (Booking)</span>
                </a>
                @endif
                @if($menuOn('inventory.adjustment'))
                <a href="{{ route('inventory.adjustment') }}"
                   class="sidebar-link {{ request()->routeIs('inventory.adjustment') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>
                    </svg>
                    <span x-show="sidebarOpen">ปรับปรุงสต็อก (Adjust)</span>
                </a>
                @endif
                </div>
            </div>
            @endif

            {{-- Accounting --}}
            @if($sectionOn(['accounting.journal', 'accounting.ledger', 'admin.accounts.index']))
            <div class="pt-2" x-data="{ open: {{ request()->routeIs('accounting.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" x-show="sidebarOpen" type="button"
                        class="w-full flex items-center justify-between px-2 py-1 text-xs font-semibold text-[#0e513a] uppercase tracking-wider hover:text-[#137050] transition-colors">
                    <span>บัญชี</span>
                    <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition>
                @if($menuOn('accounting.journal'))
                <a href="{{ route('accounting.journal') }}"
                   class="sidebar-link {{ request()->routeIs('accounting.journal') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span x-show="sidebarOpen">สมุดรายวัน (Journal)</span>
                </a>
                @endif
                @if($menuOn('accounting.ledger'))
                <a href="{{ route('accounting.ledger') }}"
                   class="sidebar-link {{ request()->routeIs('accounting.ledger') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    <span x-show="sidebarOpen">บัญชีแยกประเภท (Ledger)</span>
                </a>
                @endif
                @if($menuOn('admin.accounts.index'))
                <a href="{{ route('admin.accounts.index') }}"
                   class="sidebar-link {{ request()->routeIs('admin.accounts.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 6h16M4 10h16M4 14h16M4 18h16"/>
                    </svg>
                    <span x-show="sidebarOpen">ผังบัญชี (Chart)</span>
                </a>
                @endif
                </div>
            </div>
            @endif

            {{-- Reports --}}
            @if($sectionOn(['admin.transactions', 'reports.daily', 'reports.accounting-summary', 'reports.avg-rate', 'reports.stock-movement', 'reports.transfer', 'reports.profit-loss', 'reports.stock-valuation', 'reports.cashier', 'reports.customer-transaction', 'reports.bot-staging']))
            <div class="pt-2" x-data="{ open: {{ request()->routeIs('reports.*') || request()->routeIs('admin.transactions') ? 'true' : 'false' }} }">

                <button @click="open = !open" x-show="sidebarOpen" type="button"
                        class="w-full flex items-center justify-between px-2 py-1 text-xs font-semibold text-[#0e513a] uppercase tracking-wider hover:text-[#137050] transition-colors">
                    <span>รายงาน</span>
                    <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition>
                @if($menuOn('admin.transactions'))
                <a href="{{ route('admin.transactions') }}"
                   class="sidebar-link {{ request()->routeIs('admin.transactions') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span x-show="sidebarOpen">ค้นหารายการ (Search Trans)</span>
                </a>
                @endif
                @if($menuOn('reports.daily'))
                <a href="{{ route('reports.daily') }}"
                   class="sidebar-link {{ request()->routeIs('reports.daily') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    <span x-show="sidebarOpen">สรุปประจำวัน (Daily)</span>
                </a>
                @endif
                @if (auth()->user()->isAdmin() || auth()->user()->role?->name === 'auditor')
                @if($menuOn('reports.accounting-summary'))
                <a href="{{ route('reports.accounting-summary') }}"
                   class="sidebar-link {{ request()->routeIs('reports.accounting-summary') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                    </svg>
                    <span x-show="sidebarOpen">สรุปยอดบัญชี (Acc Summary)</span>
                </a>
                @endif
                @if($menuOn('reports.avg-rate'))
                <a href="{{ route('reports.avg-rate') }}"
                   class="sidebar-link {{ request()->routeIs('reports.avg-rate') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                    </svg>
                    <span x-show="sidebarOpen">สรุป Avg Rate</span>
                </a>
                @endif
                @endif
                @if($menuOn('reports.stock-movement'))
                <a href="{{ route('reports.stock-movement') }}"
                   class="sidebar-link {{ request()->routeIs('reports.stock-movement') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                    </svg>
                    <span x-show="sidebarOpen">เคลื่อนไหวสต็อก (Movement)</span>
                </a>
                @endif
                @if($menuOn('reports.transfer'))
                <a href="{{ route('reports.transfer') }}"
                   class="sidebar-link {{ request()->routeIs('reports.transfer') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/>
                    </svg>
                    <span x-show="sidebarOpen">โอน/ยืม/คืน (Transfer)</span>
                </a>
                @endif
                @if($menuOn('reports.profit-loss'))
                <a href="{{ route('reports.profit-loss') }}"
                   class="sidebar-link {{ request()->routeIs('reports.profit-loss') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                    </svg>
                    <span x-show="sidebarOpen">กำไร-ขาดทุน (P&L)</span>
                </a>
                @endif
                @if($menuOn('reports.stock-valuation'))
                <a href="{{ route('reports.stock-valuation') }}"
                   class="sidebar-link {{ request()->routeIs('reports.stock-valuation') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                    </svg>
                    <span x-show="sidebarOpen">มูลค่าสต็อก (Valuation)</span>
                </a>
                @endif
                @if($menuOn('reports.cashier'))
                <a href="{{ route('reports.cashier') }}"
                   class="sidebar-link {{ request()->routeIs('reports.cashier') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    <span x-show="sidebarOpen">ผลงานพนักงาน (Cashier)</span>
                </a>
                @endif
                @if($menuOn('reports.customer-transaction'))
                <a href="{{ route('reports.customer-transaction') }}"
                   class="sidebar-link {{ request()->routeIs('reports.customer-transaction') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span x-show="sidebarOpen">ธุรกรรมลูกค้า (Customer)</span>
                </a>
                @endif
                @if (auth()->user()->isAdmin())
                @if($menuOn('reports.bot-staging'))
                <a href="{{ route('reports.bot-staging') }}"
                   class="sidebar-link {{ request()->routeIs('reports.bot-staging') || request()->routeIs('reports.bot-monthly*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span x-show="sidebarOpen">รายงาน ธปท. (BOT)</span>
                </a>
                @endif
                @endif
                </div>
            </div>
            @endif

            {{-- Master Data --}}
            @if($sectionOn(['admin.currencies.index', 'admin.customers.index']))
            <div class="pt-2" x-data="{ open: {{ request()->routeIs('admin.currencies.*') || request()->routeIs('admin.customers.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" x-show="sidebarOpen" type="button"
                        class="w-full flex items-center justify-between px-2 py-1 text-xs font-semibold text-[#0e513a] uppercase tracking-wider hover:text-[#137050] transition-colors">
                    <span>ข้อมูลหลัก</span>
                    <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition>
                @if($menuOn('admin.currencies.index'))
                <a href="{{ route('admin.currencies.index') }}"
                   class="sidebar-link {{ request()->routeIs('admin.currencies.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span x-show="sidebarOpen">สกุลเงิน (Currencies)</span>
                </a>
                @endif
                @if($menuOn('admin.customers.index'))
                <a href="{{ route('admin.customers.index') }}"
                   class="sidebar-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span x-show="sidebarOpen">ลูกค้า (Customers)</span>
                </a>
                @endif
                </div>
            </div>
            @endif

            {{-- Admin --}}
            @if (auth()->user()?->isAdmin())
            @if($sectionOn(['admin.branches.index', 'admin.users.index', 'admin.permissions.index', 'admin.sessions']))
            <div class="pt-2" x-data="{ open: {{ request()->routeIs('admin.users.*') || request()->routeIs('admin.permissions.*') || request()->routeIs('admin.sessions') || request()->routeIs('admin.branches.*') ? 'true' : 'false' }} }">
                <button @click="open = !open" x-show="sidebarOpen" type="button"
                        class="w-full flex items-center justify-between px-2 py-1 text-xs font-semibold text-[#0e513a] uppercase tracking-wider hover:text-[#137050] transition-colors">
                    <span>Setting</span>
                    <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" x-transition>
                @if($menuOn('admin.branches.index'))
                <a href="{{ route('admin.branches.index') }}"
                   class="sidebar-link {{ request()->routeIs('admin.branches.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                    <span x-show="sidebarOpen">จัดการสาขา (Branches)</span>
                </a>
                @endif
                @if($menuOn('admin.users.index'))
                <a href="{{ route('admin.users.index') }}"
                   class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                    <span x-show="sidebarOpen">จัดการผู้ใช้ (Users)</span>
                </a>
                @endif
                @if($menuOn('admin.permissions.index'))
                <a href="{{ route('admin.permissions.index') }}"
                   class="sidebar-link {{ request()->routeIs('admin.permissions.*') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                    <span x-show="sidebarOpen">สิทธิ์ (Permissions)</span>
                </a>
                @endif
                @if($menuOn('admin.sessions'))
                <a href="{{ route('admin.sessions') }}"
                   class="sidebar-link {{ request()->routeIs('admin.sessions') ? 'active' : '' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    <span x-show="sidebarOpen">จัดการ Session</span>
                </a>
                @endif
                </div>
            </div>
            @endif
            @endif
        </nav>

        {{-- User info + Logout --}}
        <div class="p-3 border-t border-gray-200">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-[#0e513a] flex items-center justify-center text-sm font-bold flex-shrink-0 text-white">
                    {{ substr(auth()->user()->name, 0, 1) }}
                </div>
                <div x-show="sidebarOpen" class="flex-1 min-w-0">
                    <p class="text-sm font-medium truncate" style="color:#333;">{{ auth()->user()->name }}</p>
                    <p class="text-xs truncate" style="color:#888;">{{ auth()->user()->role?->display_name ?? '' }}</p>
                </div>
            </div>
            <div x-show="sidebarOpen" class="mt-2 space-y-1">
                <a href="{{ route('user.change-password') }}"
                   class="block w-full text-left px-3 py-1.5 text-xs text-[#0e513a] hover:text-[#137050] rounded hover:bg-gray-100 transition-colors">
                    เปลี่ยนรหัสผ่าน
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full text-left px-3 py-1.5 text-xs text-[#0e513a] hover:text-[#137050] rounded hover:bg-gray-100 transition-colors">
                        ออกจากระบบ
                    </button>
                </form>
            </div>
        </div>
    </aside>

    {{-- Main content --}}
    <div class="flex-1 flex flex-col min-h-screen overflow-hidden">
        {{-- Top bar --}}
        <header class="px-4 py-3 flex items-center gap-3" style="background:#0e513a; color:#fff;">
            <button @click="sidebarOpen = !sidebarOpen"
                    class="p-1 rounded hover:bg-white/20 text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <h1 class="text-white font-semibold text-sm">@yield('title', 'Dashboard')</h1>
            <div class="ml-auto text-sm text-white/70">{{ now()->format('d/m/Y H:i') }}</div>
        </header>

        {{-- Flash messages --}}
        @if (session('error'))
            <div class="mx-4 mt-3 p-3 bg-red-50 border border-red-300 text-red-700 rounded text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- Page content --}}
        <main class="flex-1 overflow-y-auto p-4">
            @yield('content')
        </main>
    </div>

    {{-- Alpine.js is included by Livewire 4 automatically — do NOT load CDN separately --}}
    @livewireScripts
</body>
</html>
