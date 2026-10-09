<div class="max-w-5xl">

    @if (session('success'))
        <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-800 rounded text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- token แสดงครั้งเดียวตอนนี้เท่านั้น ฐานข้อมูลเก็บแค่ hash
         ถ้าปิดกล่องนี้ไปแล้วยังไม่ได้คัดลอก ต้องสร้างเครื่องใหม่ --}}
    @if ($issuedToken !== '')
        <div class="mb-5 p-4 bg-amber-50 border-2 border-amber-400 rounded">
            <div class="font-semibold text-amber-900">คัดลอก token ของ "{{ $issuedFor }}" ตอนนี้</div>
            <p class="text-sm text-amber-800 mt-1">
                ค่านี้แสดงครั้งเดียว ปิดไปแล้วดูไม่ได้อีก ระบบเก็บไว้แค่ลายนิ้วมือของมัน
                ถ้าทำหายให้สร้างเครื่องใหม่แล้วเพิกถอนตัวเก่า
            </p>
            <div x-data="{ copied: false }">
                <div class="mt-3 p-3 bg-white border rounded font-mono text-sm break-all"
                     x-ref="token">{{ $issuedToken }}</div>

                <div class="mt-3 flex items-center gap-2">
                    {{-- คัดลอกให้จริง ไม่ใช่ให้ลากเลือกเอง — token ยาว 44 ตัวอักษร
                         ลากพลาดไปตัวเดียวแล้วเครื่องจะต่อไม่ได้โดยไม่รู้สาเหตุ --}}
                    <button type="button"
                            @click="
                                navigator.clipboard.writeText($refs.token.textContent.trim())
                                    .then(() => { copied = true; setTimeout(() => copied = false, 2000) })
                                    .catch(() => {
                                        // บางเบราว์เซอร์ไม่ให้ใช้ clipboard — เลือกข้อความให้กด Cmd+C เอง
                                        const r = document.createRange();
                                        r.selectNodeContents($refs.token);
                                        getSelection().removeAllRanges();
                                        getSelection().addRange(r);
                                    })
                            "
                            class="px-4 py-1.5 text-sm rounded text-white font-medium"
                            style="background-color:#0e513a">
                        <span x-show="!copied">คัดลอก token</span>
                        <span x-show="copied" x-cloak>คัดลอกแล้ว</span>
                    </button>

                    <button type="button" wire:click="dismissToken"
                            class="px-4 py-1.5 text-sm rounded border border-amber-600 text-amber-900">
                        ปิด
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="bg-white rounded shadow p-5 mb-5">
        <h2 class="font-semibold mb-3" style="color:#0e513a">เพิ่มเครื่องอ่านบัตร</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
            <div>
                <label class="text-xs text-gray-500">ชื่อเครื่อง</label>
                <input type="text" wire:model.blur="name" placeholder="เช่น สีลม เคาน์เตอร์ 1"
                       class="w-full border rounded px-3 py-2 text-sm">
                @error('name') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>
            <div>
                <label class="text-xs text-gray-500">เคาน์เตอร์ที่เครื่องตั้งอยู่</label>
                <select wire:model.blur="counterId" class="w-full border rounded px-3 py-2 text-sm">
                    <option value="">— เลือก —</option>
                    @foreach ($this->counters as $c)
                        <option value="{{ $c->id }}">{{ $c->branch?->branch_name }} — {{ $c->counter_name }}</option>
                    @endforeach
                </select>
                @error('counterId') <div class="text-xs text-red-600 mt-1">{{ $message }}</div> @enderror
            </div>
            <div>
                <button wire:click="create" class="px-5 py-2 rounded text-white text-sm" style="background-color:#0e513a">
                    สร้างเครื่องและออก token
                </button>
            </div>
        </div>

        <p class="text-xs text-gray-500 mt-3">
            เครื่องผูกกับเคาน์เตอร์ ไม่ได้ผูกกับคนที่ล็อกอิน — เครื่องอ่านตั้งอยู่ที่เคาน์เตอร์นั้น
            ทางกายภาพ ใครมานั่งเวรก็ไม่เปลี่ยนความจริงข้อนี้
        </p>
    </div>

    <div class="bg-white rounded shadow p-5">
        <h2 class="font-semibold mb-3" style="color:#0e513a">เครื่องที่ลงทะเบียนไว้</h2>

        @if ($this->devices->isEmpty())
            <p class="text-sm text-gray-500">ยังไม่มีเครื่องอ่านบัตรในระบบ</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 border-b">
                            <th class="py-2 pr-3">เครื่อง</th>
                            <th class="py-2 pr-3">เคาน์เตอร์</th>
                            <th class="py-2 pr-3">สถานะ</th>
                            <th class="py-2 pr-3">ได้ยินล่าสุด</th>
                            <th class="py-2 pr-3">รุ่น</th>
                            <th class="py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($this->devices as $d)
                            <tr class="border-b last:border-0">
                                <td class="py-2 pr-3">{{ $d->name }}</td>
                                <td class="py-2 pr-3 text-xs text-gray-600">
                                    {{ $d->counter?->branch?->branch_name }} — {{ $d->counter?->counter_name }}
                                </td>
                                <td class="py-2 pr-3">
                                    @if ($d->health() === 'ready')
                                        <span class="text-green-700">● พร้อม</span>
                                    @elseif ($d->health() === 'error')
                                        <span class="text-red-700" title="{{ $d->last_error }}">● มีปัญหา</span>
                                    @else
                                        <span class="text-gray-500">● ไม่ได้ยิน</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-3 text-xs">{{ $d->last_seen_at?->format('d/m/Y H:i:s') ?? '-' }}</td>
                                <td class="py-2 pr-3 text-xs">{{ $d->agent_version ?? '-' }}</td>
                                <td class="py-2 text-right">
                                    <button wire:click="revoke({{ $d->id }})"
                                            wire:confirm="เพิกถอนเครื่องนี้? เครื่องจะส่งข้อมูลเข้าระบบไม่ได้อีกทันที กู้คืนไม่ได้ และจะหายไปจากรายการนี้"
                                            class="text-xs text-red-700 border border-red-300 rounded px-3 py-1">
                                        เพิกถอน
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
