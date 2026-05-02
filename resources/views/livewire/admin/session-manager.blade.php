<div class="p-4">
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-gray-800">จัดการ Session ผู้ใช้</h2>
        <div class="flex items-center gap-2">
            <input type="text" wire:model.live.debounce.300ms="search"
                   placeholder="ค้นหาชื่อ/อีเมล..."
                   class="border border-gray-300 rounded px-3 py-1 text-sm w-48">
            <span class="text-sm text-gray-500">
                ทั้งหมด: <strong class="text-blue-700">{{ $this->activeSessions->count() }}</strong> session
            </span>
        </div>
    </div>

    {{-- Terminate confirm modal --}}
    @if ($confirmTerminateId)
        <div class="fixed inset-0 z-50 bg-black bg-opacity-40 flex items-center justify-center">
            <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
                <h3 class="text-lg font-bold text-red-700 mb-3">ยืนยันการยกเลิก Session</h3>
                <p class="text-sm text-gray-600 mb-4">กรุณาระบุเหตุผลในการยกเลิก Session นี้</p>
                <textarea wire:model="terminateReason"
                          class="w-full border border-gray-300 rounded px-3 py-2 text-sm h-20 resize-none"
                          placeholder="เหตุผล (ไม่บังคับ)"></textarea>
                <div class="flex gap-3 mt-4">
                    <button wire:click="terminate"
                            class="flex-1 py-2 bg-red-600 text-white rounded font-bold hover:bg-red-700">
                        ยืนยัน — ยกเลิก Session
                    </button>
                    <button wire:click="cancelTerminate"
                            class="flex-1 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">
                        ยกเลิก
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- Session list --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm border-collapse">
            <thead>
                <tr class="bg-[#0e513a] text-white">
                    <th class="px-3 py-2 text-left">ผู้ใช้</th>
                    <th class="px-3 py-2 text-left">สาขา / บทบาท</th>
                    <th class="px-3 py-2 text-left">IP Address</th>
                    <th class="px-3 py-2 text-left">Browser / OS</th>
                    <th class="px-3 py-2 text-left">ใช้งานล่าสุด</th>
                    <th class="px-3 py-2 text-center">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($this->activeSessions as $session)
                    <tr class="border-b border-gray-200 hover:bg-gray-50">
                        <td class="px-3 py-2">
                            <div class="font-semibold text-gray-800">{{ $session->user->name }}</div>
                            <div class="text-gray-500 text-xs">{{ $session->user->email }}</div>
                        </td>
                        <td class="px-3 py-2">
                            <div class="text-gray-700">{{ $session->user->branch?->branch_name ?? '—' }}</div>
                            <span class="text-xs px-2 py-0.5 rounded-full
                                {{ $session->user->role?->name === 'admin' || $session->user->role?->name === 'superadmin'
                                    ? 'bg-purple-100 text-purple-700'
                                    : 'bg-blue-100 text-blue-700' }}">
                                {{ $session->user->role?->display_name ?? $session->user->role?->name ?? '—' }}
                            </span>
                        </td>
                        <td class="px-3 py-2 font-mono text-xs">
                            {{ $session->ip_address }}
                            @if ($session->country)
                                <div class="text-gray-400">{{ $session->city }}, {{ $session->country }}</div>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-xs">
                            <div>{{ $session->browser }}</div>
                            <div class="text-gray-400">{{ $session->os }}</div>
                            <div class="text-gray-400">{{ $session->device_type }}</div>
                        </td>
                        <td class="px-3 py-2 text-xs">
                            @if ($session->last_activity)
                                <div>{{ $session->last_activity->format('d/m/Y H:i:s') }}</div>
                                <div class="text-gray-400">{{ $session->last_activity->diffForHumans() }}</div>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-center">
                            <div class="flex flex-col gap-1 items-center">
                                <button wire:click="askTerminate({{ $session->id }})"
                                        class="px-3 py-1 bg-red-600 text-white text-xs rounded hover:bg-red-700 whitespace-nowrap">
                                    เตะออก
                                </button>
                                <button wire:click="terminateAllForUser({{ $session->user_id }})"
                                        class="px-3 py-1 bg-orange-500 text-white text-xs rounded hover:bg-orange-600 whitespace-nowrap">
                                    เตะทั้งหมด
                                </button>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-3 py-8 text-center text-gray-400">
                            ไม่มี Session ที่ active อยู่
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
