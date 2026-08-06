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

        {{-- Working Branch Selector (Staff only) --}}
        @if(auth()->user()->role?->name === 'staff')
        @php
            $workingBranchId = session('working_branch_id', auth()->user()->branch_id);
            $workingBranch = \App\Models\Branch::find($workingBranchId);
            $allBranches = \App\Models\Branch::orderBy('branch_code')->get();
        @endphp
        <div x-show="sidebarOpen" class="px-3 py-2" x-data="{ showBranchSwitch: false }" style="border-bottom:1px solid #e5e7eb;">
            <div class="text-xs mb-1" style="color:#888;">สาขาทำงาน</div>
            <button @click="showBranchSwitch = !showBranchSwitch"
                    class="w-full text-left flex items-center justify-between gap-2 px-2 py-1.5 rounded text-sm font-semibold hover:bg-gray-100 transition-colors" style="color:#0e513a;">
                <span>{{ $workingBranch?->branch_name ?? '—' }}</span>
                <svg class="w-4 h-4 text-[#0e513a] transition-transform" :class="showBranchSwitch && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>
            <div x-show="showBranchSwitch" x-cloak x-transition class="mt-1">
                <form method="POST" action="{{ route('switch-branch') }}">
                    @csrf
                    <select name="branch_id" onchange="this.form.submit();"
                            class="w-full text-xs text-gray-800 bg-white border border-gray-300 rounded px-2 py-1.5">
                        @foreach ($allBranches as $branch)
                            <option value="{{ $branch->id }}" {{ $workingBranchId == $branch->id ? 'selected' : '' }}>
                                {{ $branch->branch_name }} ({{ $branch->branch_code }})
                            </option>
                        @endforeach
                    </select>
                </form>
            </div>
        </div>
        @endif

        {{-- Working Counter Selector --}}
        @php
            $workingName = session('working_counter_name');
            if (!$workingName) {
                $workingBranchId = session('working_branch_id', auth()->user()->branch_id);
                $defaultCounter = \App\Models\Counter::where('branch_id', $workingBranchId)->where('is_active', true)->first();
                $workingName = $defaultCounter?->counter_name ?? auth()->user()->branch?->branch_name ?? '—';
            }
            $canSwitchCounter = auth()->user()->canSwitchBranch();
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
                {{-- Staff/Branch Manager: show counter name only, no switching --}}
                <div class="px-2 py-1.5 text-sm font-semibold" style="color:#0e513a;">
                    {{ $workingName }}
                </div>
            @endif
        </div>

        {{-- Nav --}}
        @php
            // Get accessible menus for current user (cached)
            $accessibleMenus = auth()->user()->getAccessibleMenus();

            // Build menu tree: parent → children
            $parents = $accessibleMenus->whereNull('parent_id')->sortBy('order');
            $menuTree = $parents->map(function($parent) use ($accessibleMenus) {
                return [
                    'parent' => $parent,
                    'children' => $accessibleMenus->where('parent_id', $parent->id)->sortBy('order'),
                ];
            });

            // Helper: build a menu URL.
            // Some routes take parameters (rate.board needs the working counter
            // code), so route($name) alone throws UrlGenerationException. Returns
            // null when no URL can be built so the caller skips the item rather
            // than taking down every page that renders this layout.
            $menuUrl = function ($menu) {
                if (empty($menu->route)) {
                    return null;
                }

                if ($menu->route === 'rate.board') {
                    $code = \App\Models\Counter::where('id', session('working_counter_id'))->value('counter_code');
                    return $code ? route('rate.board', $code) : null;
                }

                try {
                    return route($menu->route);
                } catch (\Throwable $e) {
                    return null;
                }
            };

            // Rate board opens on a separate customer-facing screen
            $menuTarget = fn ($menu) => $menu->route === 'rate.board' ? '_blank' : null;

            // Helper: SVG icons (simple mapping)
            $iconSvg = function(string $icon) {
                $icons = [
                    'home' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>',
                    'document-text' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
                    'plus' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>',
                    'minus' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"/>',
                    'clipboard-list' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>',
                    'building-library' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>',
                    'chart-bar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>',
                    'currency-dollar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
                    'pencil' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>',
                    'cog' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                    'star' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>',
                    'tv' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
                    'document-chart-bar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>',
                    'magnifying-glass' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>',
                    'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>',
                    'archive-box' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>',
                    'squares-2x2' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM14 5a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1V5zM4 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1H5a1 1 0 01-1-1v-4zM14 15a1 1 0 011-1h4a1 1 0 011 1v4a1 1 0 01-1 1h-4a1 1 0 01-1-1v-4z"/>',
                    'bookmark' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/>',
                    'arrow-path' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>',
                    'adjustments-horizontal' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/>',
                    'database' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/>',
                    'banknotes' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2 8a2 2 0 012-2h16a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V8z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 16h12M6 12h12M6 8h12"/>',
                    'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
                    'book-open' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>',
                    'cog-6-tooth' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>',
                    'building-office' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>',
                    'user-group' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>',
                    'shield-check' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>',
                    'computer-desktop' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25"/>',
                ];
                return $icons[$icon] ?? $icons['home'];
            };
        @endphp
        <nav class="flex-1 p-3 space-y-1 overflow-y-auto">
            {{-- Dynamic Menu from Database --}}
            @foreach ($menuTree as $item)
                @php
                    $parent = $item['parent'];
                    $children = $item['children'];
                    $hasChildren = $children->isNotEmpty();
                @endphp

                @if (!$hasChildren && ($parentUrl = $menuUrl($parent)))
                    {{-- Single menu item (no children) --}}
                    <a href="{{ $parentUrl }}"
                       @if ($menuTarget($parent)) target="{{ $menuTarget($parent) }}" rel="noopener" @endif
                       class="sidebar-link {{ request()->routeIs($parent->route) ? 'active' : '' }}">
                        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            {!! $iconSvg($parent->icon) !!}
                        </svg>
                        <span x-show="sidebarOpen">{{ $parent->label_th }}</span>
                    </a>
                @elseif ($hasChildren)
                    {{-- Parent menu with children --}}
                    <div class="pt-2" x-data="{ open: {{ $children->contains(fn($m) => $m->route && request()->routeIs($m->route)) ? 'true' : 'false' }} }">
                        <button @click="open = !open" x-show="sidebarOpen" type="button"
                                class="w-full flex items-center justify-between px-2 py-1 text-xs font-semibold text-[#0e513a] uppercase tracking-wider hover:text-[#137050] transition-colors">
                            <span>{{ $parent->label_th }}</span>
                            <svg class="w-3 h-3 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open" x-transition>
                            @foreach ($children as $child)
                                @if ($childUrl = $menuUrl($child))
                                <a href="{{ $childUrl }}"
                                   @if ($menuTarget($child)) target="{{ $menuTarget($child) }}" rel="noopener" @endif
                                   class="sidebar-link {{ request()->routeIs($child->route) ? 'active' : '' }}">
                                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        {!! $iconSvg($child->icon) !!}
                                    </svg>
                                    <span x-show="sidebarOpen">{{ $child->label_th }}</span>
                                </a>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforeach
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
