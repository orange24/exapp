<div wire:poll.30s="loadInventory">
    <div class="mb-4 flex items-center justify-between">
        <div class="text-sm text-gray-600">
            <span class="inline-flex items-center">
                <svg class="w-4 h-4 mr-1 animate-pulse" fill="currentColor" viewBox="0 0 20 20">
                    <circle cx="10" cy="10" r="8" class="text-green-500"/>
                </svg>
                Auto-refresh ทุก 30 วินาที
            </span>
        </div>
        <button wire:click="loadInventory" class="px-3 py-1 text-sm bg-white border rounded hover:bg-gray-50" style="border-color:#0e513a; color:#0e513a;">
            <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
            </svg>
            Refresh
        </button>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Per Branch Cards -->
        @foreach($managedBranches as $branch)
        <div class="bg-white rounded-lg shadow border border-gray-200">
            <div class="px-4 py-3 border-b" style="background:#0e513a;">
                <h3 class="font-bold text-white">{{ $branch->branch_name }}</h3>
                <p class="text-xs text-gray-200">{{ $branch->branch_code }}</p>
            </div>

            <div class="p-3">
                @if(isset($inventoryData[$branch->id]) && count($inventoryData[$branch->id]) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="border-b" style="border-color:#0e513a;">
                            <tr>
                                <th class="text-left py-2 px-1" style="color:#0e513a;">สกุลเงิน</th>
                                <th class="text-right py-2 px-1" style="color:#0e513a;">Avg Cost</th>
                                <th class="text-right py-2 px-1" style="color:#0e513a;">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($inventoryData[$branch->id] as $item)
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-2 px-1 font-medium" style="color:#0e513a;">
                                    {{ $item['currency_name'] }}
                                    <span class="text-gray-400 text-xs ml-1">({{ $item['currency_code'] }})</span>
                                </td>
                                <td class="text-right py-2 px-1 font-mono text-gray-700">
                                    {{ number_format($item['avg_cost'], 4) }}
                                </td>
                                <td class="text-right py-2 px-1 font-mono font-bold" style="color:#0e513a;">
                                    {{ number_format($item['balance'], 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-center text-gray-400 py-4 text-sm">ไม่มี Stock</p>
                @endif
            </div>
        </div>
        @endforeach

        <!-- Combined Card -->
        <div class="bg-green-50 rounded-lg shadow border-2 border-green-600">
            <div class="px-4 py-3 border-b-2 border-green-600" style="background:#0e513a;">
                <h3 class="font-bold text-white flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    รวมทั้งหมด (Average)
                </h3>
                <p class="text-xs text-gray-200">ค่าเฉลี่ยจากทุกสาขาที่ดูแล</p>
            </div>

            <div class="p-3">
                @if(count($combinedData) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="border-b-2 border-green-600">
                            <tr>
                                <th class="text-left py-2 px-1" style="color:#0e513a;">สกุลเงิน</th>
                                <th class="text-right py-2 px-1" style="color:#0e513a;">Avg Cost</th>
                                <th class="text-right py-2 px-1" style="color:#0e513a;">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($combinedData as $item)
                            <tr class="border-b border-green-200 hover:bg-green-100">
                                <td class="py-2 px-1 font-medium" style="color:#0e513a;">
                                    {{ $item['currency_name'] }}
                                    <span class="text-gray-500 text-xs ml-1">({{ $item['currency_code'] }})</span>
                                </td>
                                <td class="text-right py-2 px-1 font-mono text-gray-700 font-bold">
                                    {{ number_format($item['avg_cost'], 4) }}
                                </td>
                                <td class="text-right py-2 px-1 font-mono font-bold text-green-700">
                                    {{ number_format($item['balance'], 2) }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @else
                <p class="text-center text-gray-400 py-4 text-sm">ไม่มี Stock</p>
                @endif
            </div>
        </div>
    </div>
</div>
