<div>
    <div class="flex items-center justify-between mb-3">
        <div class="flex gap-2 text-sm">
            @php
                $tabs = [
                    'all' => 'ทั้งหมด',
                    'unread' => 'ยังไม่อ่าน' . ($this->unreadCount ? ' (' . $this->unreadCount . ')' : ''),
                    'sanction' => 'รายชื่อต้องห้าม',
                    'system' => 'ระบบ',
                ];
            @endphp

            @foreach ($tabs as $key => $label)
                <button type="button" wire:click="setFilter('{{ $key }}')"
                        class="px-3 py-1 rounded border {{ $filter === $key ? 'text-white' : 'bg-white' }}"
                        @if ($filter === $key) style="background:#0e513a; border-color:#0e513a;" @endif>
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <button type="button" wire:click="markAllAsRead" class="px-3 py-1 rounded border text-sm bg-white">
            ทำเครื่องหมายอ่านแล้ว
        </button>
    </div>

    <div class="bg-white border rounded divide-y">
        @forelse ($this->notifications as $n)
            @php
                $d = is_array($n->data) ? $n->data : json_decode($n->data, true);
                $d = is_array($d) ? $d : [];
                $icon = match ($d['severity'] ?? '') {
                    'blocked' => '🔴',
                    'approved' => '🟠',
                    'error' => '⚫',
                    default => '🔵',
                };
            @endphp

            <div class="p-3 {{ $n->read_at ? '' : 'bg-blue-50/40' }}">
                <div class="flex items-start gap-2">
                    <span>{{ $icon }}</span>
                    <div class="flex-1 text-sm">
                        <div class="font-medium">
                            @unless ($n->read_at) <span class="text-blue-600">●</span> @endunless
                            {{ $d['title'] ?? 'การแจ้งเตือน' }}
                        </div>

                        <div class="text-xs text-gray-600 mt-0.5">
                            @if (!empty($d['transaction_id'])) ธุรกรรม #{{ $d['transaction_id'] }} · @endif
                            @if (!empty($d['branch_name'])) {{ $d['branch_name'] }} · @endif
                            @if (!empty($d['customer_name'])) {{ $d['customer_name'] }} @endif
                        </div>

                        @if (!empty($d['approved_by']))
                            <div class="text-xs text-gray-600">อนุมัติโดย: {{ $d['approved_by'] }}</div>
                        @endif
                        @if (!empty($d['reason']))
                            <div class="text-xs text-gray-500 italic">"{{ $d['reason'] }}"</div>
                        @endif
                        @if (!empty($d['error_message']))
                            <div class="text-xs text-red-600">{{ $d['error_message'] }}</div>
                        @endif

                        <div class="flex items-center gap-3 mt-1">
                            <span class="text-xs text-gray-400">{{ $n->created_at->format('d/m/Y H:i') }}</span>

                            {{--
                                หน้ารายการธุรกรรมคือ route `admin.transactions` (ไม่มี `transactions.index`
                                ในโปรเจกต์นี้) และหน้านั้นไม่รองรับ query `highlight` จึงลิงก์ไปหน้ารายการเฉย ๆ
                                ตามที่แผนระบุไว้เป็นทางเลือกสำรอง
                            --}}
                            @if (!empty($d['transaction_id']))
                                <a href="{{ route('admin.transactions') }}"
                                   class="text-xs underline" style="color:#0e513a;"
                                   wire:click="markAsRead('{{ $n->id }}')">
                                    → ไปที่ธุรกรรม
                                </a>
                            @endif

                            @if (!empty($d['link_route']) && \Illuminate\Support\Facades\Route::has($d['link_route']))
                                <a href="{{ route($d['link_route'], $d['link_params'] ?? []) }}"
                                   class="text-xs underline" style="color:#0e513a;"
                                   wire:click="markAsRead('{{ $n->id }}')">
                                    → ดูรายละเอียด
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="p-6 text-center text-sm text-gray-500">ไม่มีการแจ้งเตือน</div>
        @endforelse
    </div>

    <div class="mt-3">{{ $this->notifications->links() }}</div>
</div>
