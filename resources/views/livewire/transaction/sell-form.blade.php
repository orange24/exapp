<div class="p-4" x-data="buyForm()">

{{-- Counter Selection Modal --}}
@if($showCounterModal)
<div style="position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:16px; padding:32px; width:100%; max-width:480px; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
        <div style="text-align:center; margin-bottom:24px;">
            <div style="width:56px; height:56px; background:#EEF2FF; border-radius:14px; display:flex; align-items:center; justify-content:center; margin:0 auto 12px;">
                <svg style="width:28px; height:28px; color:#0e513a;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                </svg>
            </div>
            <h2 style="font-size:20px; font-weight:700; color:#0e513a;">เลือกเคาน์เตอร์ก่อนทำรายการ</h2>
            <p style="font-size:13px; color:#888; margin-top:4px;">กรุณาเลือกเคาน์เตอร์ที่คุณจะใช้งาน</p>
        </div>

        <div style="display:flex; flex-direction:column; gap:8px; max-height:360px; overflow-y:auto; margin-bottom:20px;">
            @foreach ($this->availableCounters as $c)
                <button type="button"
                        wire:click="selectCounterFromModal({{ $c->id }})"
                        style="background:#fff; color:#333; border:2px solid #e5e7eb; border-radius:10px; padding:12px 16px; text-align:left; cursor:pointer; transition:all 0.15s;"
                        onmouseover="this.style.borderColor='#0e513a'"
                        onmouseout="this.style.borderColor='#e5e7eb'">
                    <div style="font-weight:600; font-size:15px;">{{ $c->counter_name }}</div>
                    <div style="font-size:12px; opacity:0.7;">{{ $c->branch->branch_name ?? '' }}</div>
                </button>
            @endforeach
        </div>
    </div>
</div>
@endif

<div class="flex gap-4">
{{-- Main Form Area --}}
<div class="flex-1 min-w-0">
    {{-- Header --}}
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-bold text-gray-800">จ่ายขายเงินตราต่างประเทศ (Sell)</h2>
        <div class="flex items-center gap-2">
            <label class="text-sm text-gray-600">เคาน์เตอร์:</label>
            <select wire:model.live="counterId"
                    class="text-sm border border-gray-300 rounded px-2 py-1">
                <option value="">-- เลือก --</option>
                @foreach ($this->availableCounters as $c)
                    <option value="{{ $c->id }}">{{ $c->counter_name }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Customer + Passport --}}
    <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48 relative" x-data="{ showNameDrop: false }">
                <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อลูกค้า</label>
                <input type="text" wire:model.blur="custName"
                       x-on:input.debounce.300ms="$wire.searchCustomers($event.target.value); showNameDrop = true"
                       x-on:focus="if($event.target.value.length >= 2) { $wire.searchCustomers($event.target.value); showNameDrop = true }"
                       x-on:click.away="showNameDrop = false"
                       placeholder="ชื่อลูกค้า"
                       autocomplete="off"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
                @if ($showSuggestions && count($customerSuggestions) > 0)
                <div x-show="showNameDrop" x-cloak
                     style="position:absolute; z-index:50; left:0; right:0; top:100%; margin-top:2px; max-height:240px; overflow-y:auto; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                    @foreach ($customerSuggestions as $sug)
                    <button type="button"
                            wire:click="selectCustomer({{ $sug['id'] }})"
                            x-on:click="showNameDrop = false"
                            class="w-full text-left px-3 py-2 hover:bg-blue-50 border-b border-gray-100 last:border-0 transition-colors"
                            style="display:block;">
                        <div class="font-semibold text-sm text-gray-800">{{ $sug['name'] }}</div>
                        <div class="text-xs text-gray-500">{{ $sug['id_number'] }} &middot; {{ $sug['nationality'] }}</div>
                    </button>
                    @endforeach
                </div>
                @endif
            </div>
            {{-- Booth-to-Booth discount toggle --}}
            <div class="flex items-center gap-2 mt-1">
                <input type="checkbox" wire:model.live="isDiscountBooth" id="discountBooth"
                       class="w-4 h-4">
                <label for="discountBooth" class="text-sm text-gray-700">ขายให้สาขาอื่น (ลดราคา)</label>
            </div>
            @if ($isDiscountBooth)
                <div>
                    <select wire:model="discountBoothCode"
                            class="text-sm border border-gray-300 rounded px-2 py-1">
                        <option value="">-- เลือกเคาน์เตอร์ปลายทาง --</option>
                        @foreach ($this->otherCounters as $oc)
                            <option value="{{ $oc->counter_code }}">{{ $oc->counter_name }}</option>
                        @endforeach
                    </select>
                </div>
            @endif
            <button type="button" @click="openCamera()"
                    class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                ถ่าย Passport
            </button>
            <button type="button"
                    @click="$refs.fileInput.click()"
                    class="px-4 py-2 bg-gray-600 text-white text-sm rounded hover:bg-gray-700 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                เลือกรูป
            </button>
            <input type="file" x-ref="fileInput" accept="image/*" class="hidden" @change="handleFileSelect($event)">
        </div>

        {{-- Passport info (always visible, editable) --}}
        <div class="mt-3 p-2 bg-green-50 border border-green-200 rounded text-sm"
             x-data="{ showDropdown: false, saved: false }"
             x-on:customer-updated.window="saved = true; setTimeout(() => saved = false, 3000)">
            <div class="grid grid-cols-3 gap-2">
                <div class="relative">
                    <label class="text-gray-500 text-xs">Passport No</label>
                    <input type="text" wire:model.blur="ocrPassportNo"
                           x-on:input.debounce.300ms="$wire.searchCustomers($event.target.value); showDropdown = true"
                           x-on:focus="if($event.target.value.length >= 2) { $wire.searchCustomers($event.target.value); showDropdown = true }"
                           x-on:click.away="showDropdown = false"
                           placeholder="เลขพาสปอร์ต / ค้นหา"
                           autocomplete="off"
                           class="w-full border border-green-300 rounded px-2 py-1 text-sm font-semibold focus:ring-2 focus:ring-green-400 bg-white">
                    @if ($showSuggestions && count($customerSuggestions) > 0)
                    <div x-show="showDropdown" x-cloak
                         style="position:absolute; z-index:50; left:0; right:0; top:100%; margin-top:2px; max-height:240px; overflow-y:auto; background:#fff; border:1px solid #d1d5db; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.15);">
                        @foreach ($customerSuggestions as $sug)
                        <button type="button"
                                wire:click="selectCustomer({{ $sug['id'] }})"
                                x-on:click="showDropdown = false"
                                class="w-full text-left px-3 py-2 hover:bg-green-50 border-b border-gray-100 last:border-0 transition-colors"
                                style="display:block;">
                            <div class="font-semibold text-sm text-gray-800">{{ $sug['id_number'] }}</div>
                            <div class="text-xs text-gray-500">{{ $sug['name'] }} &middot; {{ $sug['nationality'] }}</div>
                        </button>
                        @endforeach
                    </div>
                    @endif
                </div>
                <div>
                    <label class="text-gray-500 text-xs">Nationality</label>
                    <input type="text" wire:model.blur="ocrNationality"
                           placeholder="สัญชาติ"
                           class="w-full border border-green-300 rounded px-2 py-1 text-sm font-semibold focus:ring-2 focus:ring-green-400 bg-white">
                </div>
                <div>
                    <label class="text-gray-500 text-xs">Expiry</label>
                    <input type="text" wire:model.blur="ocrExpiry"
                           placeholder="วันหมดอายุ"
                           class="w-full border border-green-300 rounded px-2 py-1 text-sm font-semibold focus:ring-2 focus:ring-green-400 bg-white">
                </div>
            </div>
            @if ($showPrintSlip && $savedTransactionId)
            <div class="mt-2 flex items-center gap-2">
                <button type="button" wire:click="updateCustomerInfo" wire:loading.attr="disabled"
                        class="px-4 py-1.5 bg-green-600 text-white text-xs rounded hover:bg-green-700 font-semibold disabled:opacity-60">
                    <span wire:loading.remove wire:target="updateCustomerInfo">บันทึกข้อมูลลูกค้า</span>
                    <span wire:loading wire:target="updateCustomerInfo">กำลังบันทึก...</span>
                </button>
                <span x-show="saved" x-cloak x-transition class="text-green-600 text-xs font-semibold">บันทึกแล้ว!</span>
            </div>
            @endif
        </div>
    </div>

    {{-- Passport Camera + Crop Modal --}}
    <template x-if="cameraOpen">
        <div style="position:fixed; inset:0; z-index:50; background:rgba(0,0,0,0.9); display:flex; align-items:center; justify-content:center; overflow-y:auto; padding:20px 0;">
            <div style="background:#1a1a2e; border-radius:12px; padding:20px; width:100%; max-width:700px; margin:auto;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h3 style="color:#fff; font-weight:600; font-size:16px;">
                        <span x-show="step==='camera'">1. ถ่ายรูป / เลือกรูป</span>
                        <span x-show="step==='crop'">2. ปรับ/Crop รูป</span>
                        <span x-show="step==='done'">3. ผลลัพธ์</span>
                    </h3>
                    <button @click="closeCamera()" style="color:#888; font-size:24px; background:none; border:none; cursor:pointer;">&times;</button>
                </div>
                <div x-show="step==='camera'">
                    <div style="position:relative; margin-bottom:12px;">
                        <video x-ref="video" autoplay playsinline style="width:100%; border-radius:8px; max-height:360px; background:#000;"></video>
                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none;">
                            <div style="width:85%; height:70%; border:4px dashed #facc15; border-radius:8px; opacity:0.5;"></div>
                        </div>
                    </div>
                    <canvas x-ref="canvas" style="display:none;"></canvas>
                    <button @click="capturePhoto()" type="button" style="width:100%; padding:10px; background:#eab308; color:#000; font-weight:700; border:none; border-radius:8px; cursor:pointer;">ถ่ายรูป</button>
                </div>
                <div x-show="step==='crop'">
                    <div style="margin-bottom:12px; background:#000; border-radius:8px; overflow:hidden; max-height:450px;">
                        <img x-ref="cropImg" :src="capturedImage" style="max-width:100%; display:block;">
                    </div>
                    <p style="color:#aaa; font-size:12px; margin-bottom:10px;">ลากกรอบเลือกเฉพาะส่วนที่ต้องการอ่าน OCR</p>
                    <div style="display:flex; gap:10px;">
                        <button @click="applyCrop()" type="button" style="flex:1; padding:10px; background:#16a34a; color:#fff; font-weight:700; border:none; border-radius:8px; cursor:pointer;">Crop & อ่าน OCR</button>
                        <button @click="destroyCropper(); capturedImage=null; croppedImage=null; step='camera'; $nextTick(() => { navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}).then(s=>{stream=s;$refs.video.srcObject=s}).catch(()=>navigator.mediaDevices.getUserMedia({video:true}).then(s=>{stream=s;$refs.video.srcObject=s}).catch(()=>{})) })" type="button" style="padding:10px 16px; background:#444; color:#ccc; font-weight:600; border:none; border-radius:8px; cursor:pointer;">ถ่ายใหม่</button>
                    </div>
                </div>
                <div x-show="step==='done'">
                    <template x-if="croppedImage">
                        <div style="margin-bottom:12px;"><img :src="croppedImage" style="width:100%; border-radius:8px; max-height:200px; object-fit:contain; background:#000;"></div>
                    </template>
                    <div style="display:flex; gap:10px;">
                        <button @click="step='crop'; initCropper();" type="button" style="flex:1; padding:10px; background:#444; color:#ccc; font-weight:600; border:none; border-radius:8px; cursor:pointer;">Crop ใหม่</button>
                        <button @click="confirmCapture()" type="button" style="flex:1; padding:10px; background:#2563eb; color:#fff; font-weight:700; border:none; border-radius:8px; cursor:pointer;">ยืนยัน</button>
                    </div>
                </div>
                <div x-show="ocrLoading" style="margin-top:10px; padding:8px 12px; background:#1e3a5f; border-radius:8px; text-align:center;">
                    <span style="color:#facc15; font-weight:600;" x-text="ocrError || 'กำลังประมวลผล OCR...'"></span>
                </div>
                <div x-show="ocrError && !ocrLoading" style="margin-top:8px; color:#f87171; font-size:13px;" x-text="ocrError"></div>
            </div>
        </div>
    </template>

    {{-- Stock guard — เพิ่มแถวไม่ได้ / บันทึกไม่ผ่านเพราะเงินในบูธไม่พอ --}}
    @error('stock')
        <div class="mb-4 p-3 bg-red-100 border border-red-400 text-red-800 rounded text-sm font-medium">
            {{ $message }}
        </div>
    @enderror

    {{-- Add currency row — Sell: ลูกค้าให้ THB → แลกเงินต่างประเทศ --}}
    @if (! $showPrintSlip)
    <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded">
        <div class="flex flex-wrap gap-3 items-end">
            <div style="width:200px; flex-shrink:0;">
                <label class="block text-sm font-medium text-gray-700 mb-1">สกุลเงินที่ขาย</label>
                <select wire:model.live="selectedCurrency"
                        x-ref="currencySelect"
                        class="w-full border border-gray-300 rounded px-2 py-2 text-sm">
                    <option value="">-- เลือกสกุลเงิน --</option>
                    @php $prevCode = ''; @endphp
                    @foreach ($this->availableCurrencies as $cr)
                        @if ($cr->currency_code !== $prevCode)
                            @if ($prevCode !== '') </optgroup> @endif
                            <optgroup label="{{ $cr->currency_code }} — {{ $cr->currency?->currency_name ?? '' }}">
                            @php $prevCode = $cr->currency_code; @endphp
                        @endif
                        <option value="{{ $cr->denomination_id }}">
                            {{ $cr->denomination?->display_name ?? $cr->currency_code }}
                        </option>
                    @endforeach
                    @if ($prevCode !== '') </optgroup> @endif
                </select>
            </div>
            <div style="width:130px; flex-shrink:0;">
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    อัตราขาย
                    @unless ($this->canEditRate)
                        <span class="text-xs font-normal text-gray-400">(ดูอย่างเดียว)</span>
                    @endunless
                </label>
                @if ($this->canEditRate)
                    <input type="number" wire:model.blur="currentRate" min="0" step="0.0001"
                           class="w-full border border-red-400 rounded px-2 py-2 text-sm text-right font-mono">
                @else
                    <input type="text" value="{{ $currentRate > 0 ? number_format($currentRate, 4) : '' }}"
                           readonly disabled
                           class="w-full border border-gray-300 bg-gray-100 text-gray-600 rounded px-2 py-2 text-sm text-right font-mono cursor-not-allowed">
                @endif
            </div>
            <div style="width:120px; flex-shrink:0;" x-data>
                <label class="block text-sm font-medium text-gray-700 mb-1">ลูกค้าให้ (THB)</label>
                <x-number-input model="addAmount" :value="$addAmount" :decimals="0"
                       x-ref="amountInput"
                       @keydown.enter.prevent="commit($el); $wire.addRow().then(() => { $nextTick(() => $refs.currencySelect.focus()); })"
                       class="w-full border border-gray-300 rounded px-2 py-2 text-sm" />
            </div>
            <div>
                <button wire:click="addRow" type="button"
                        class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 text-sm font-bold">
                    + เพิ่ม
                </button>
            </div>
        </div>
    </div>

    {{-- Adjust Modal — เงินในบูธไม่พอ --}}
    @if ($showAdjustModal)
        <div style="position:fixed; inset:0; z-index:50; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center;">
            <div style="background:#fff; border-radius:12px; padding:24px; width:100%; max-width:420px; box-shadow:0 20px 60px rgba(0,0,0,0.3);">
                <h3 style="font-size:16px; font-weight:700; color:#dc2626; margin-bottom:16px;">เงินในบูธไม่เพียงพอ</h3>
                <div style="font-size:14px; color:#333; line-height:1.8;">
                    <div>ลูกค้าให้ = <strong>{{ number_format($addAmount, 0) }} THB</strong></div>
                    <div>อัตราขาย = <strong>{{ number_format($currentRate, 4) }}</strong></div>
                    <div>คำนวณได้ = <strong>{{ number_format($currentTotal, 0) }}</strong> (ต่างประเทศ)</div>
                    <div style="color:#dc2626;">เงินในบูธมี = <strong>{{ number_format($boothAmount, 0) }}</strong></div>
                    <hr style="margin:12px 0; border-color:#eee;">
                    <div style="color:#16a34a; font-weight:600;">ปรับเป็น:</div>
                    <div>ให้ลูกค้า = <strong>{{ number_format($adjustedTotal, 0) }}</strong> (เท่าที่มีในบูธ)</div>
                    <div>รับ THB = <strong>{{ number_format($adjustedThb, 0) }} THB</strong></div>
                </div>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <button wire:click="cancelAdjust" type="button"
                            style="flex:1; padding:10px; background:#e5e7eb; color:#333; font-weight:600; border:none; border-radius:8px; cursor:pointer;">
                        ยกเลิก
                    </button>
                    <button wire:click="confirmAdjust" type="button"
                            style="flex:1; padding:10px; background:#1E3A5F; color:#fff; font-weight:700; border:none; border-radius:8px; cursor:pointer;">
                        ตกลง
                    </button>
                </div>
            </div>
        </div>
    @endif

    @endif

    {{-- Transaction rows --}}
    @php $displayRows = $showPrintSlip ? $savedRows : $rows; @endphp
    @if (count($displayRows) > 0)
        <div class="mb-4 overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-[#0e513a] text-white">
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">สกุลเงิน</th>
                        <th class="px-3 py-2 text-right">ลูกค้าได้ (ต่างประเทศ)</th>
                        <th class="px-3 py-2 text-right">อัตราขาย</th>
                        <th class="px-3 py-2 text-right">รับจากลูกค้า (THB)</th>
                        @if (! $showPrintSlip)
                        <th class="px-3 py-2 text-center w-16">ลบ</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($displayRows as $i => $row)
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-3 py-2 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-3 py-2 font-semibold">
                                <span class="text-gray-500 font-normal text-xs">{{ $row['currency_name'] }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono font-bold text-red-700">{{ number_format($row['total'], 0) }}</td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($row['rate'], 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono font-bold">{{ number_format($row['amount'], 0) }}</td>
                            @if (! $showPrintSlip)
                            <td class="px-3 py-2 text-center">
                                <button wire:click="removeRow({{ $i }})" type="button"
                                        class="text-red-500 hover:text-red-700 font-bold">✕</button>
                            </td>
                            @endif
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-bold">
                        <td colspan="4" class="px-3 py-2 text-right">รวม THB รับจากลูกค้า</td>
                        <td class="px-3 py-2 text-right font-mono text-lg text-red-800">
                            {{ number_format(collect($displayRows)->sum('amount'), 0) }}
                        </td>
                        @if (! $showPrintSlip)<td></td>@endif
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    @if ($showPrintSlip && $savedTransactionId)
        <div class="mb-4 p-3 bg-green-50 border border-green-300 rounded flex items-center justify-between">
            <span class="text-green-700 font-semibold">บันทึกสำเร็จ!</span>
            <div class="flex gap-2">
                <button type="button" wire:click="newTransaction"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-semibold">
                    + สร้างใหม่
                </button>
                <button type="button"
                        onclick="document.getElementById('print-iframe').src = '{{ route('transaction.print', $savedTransactionId) }}?print=Y&t=' + Date.now();"
                        class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
                    พิมพ์อีกครั้ง
                </button>
            </div>
        </div>
        <iframe id="print-iframe" src="{{ route('transaction.print', $savedTransactionId) }}?print=Y"
                style="position:absolute; width:0; height:0; border:none; overflow:hidden;"
                onload="setTimeout(() => { @this.newTransaction(); }, 1500);"></iframe>
    @endif

    @if (count($rows) > 0 && ! $showPrintSlip)
        <div class="flex justify-end gap-3">
            <button wire:click="saveTransaction" wire:loading.attr="disabled" type="button"
                    class="px-6 py-2 bg-[#0e513a] text-white rounded font-bold hover:bg-[#0a3d2d] disabled:opacity-60">
                <span wire:loading.remove>บันทึก & พิมพ์</span>
                <span wire:loading>กำลังบันทึก...</span>
            </button>
        </div>
    @endif

</div>{{-- end Main Form Area --}}

{{-- Side Panel: Stock Info --}}
<div class="w-64 flex-shrink-0 hidden lg:block">
    <div class="sticky top-4">
        <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
            <div class="px-4 py-3" style="background:#0e513a;">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                    Stock Info
                </h3>
            </div>

            <div class="p-3">
                <div class="text-center mb-3 pb-2 border-b" style="border-color:#0e513a;">
                    <div class="text-sm font-bold" style="color:#0e513a;">อัตราแลกเปลี่ยนเฉลี่ย</div>
                    <div class="text-xs text-gray-500 mt-1">จากการทำธุรกรรมของสาขา (วันนี้)</div>
                </div>

                <div class="overflow-y-auto" style="max-height: 450px;">
                    @php $prevCurrency = ''; @endphp
                    @foreach ($this->stockInfo as $info)
                        @if ($prevCurrency !== $info['currency_code'])
                            {{-- Currency Header --}}
                            @if ($prevCurrency !== '')
                                <div class="mt-2"></div>
                            @endif
                            <div class="px-2 py-1.5 text-xs font-semibold text-white border-b border-gray-300" style="background:#0e513a; position: sticky; top: 0; z-index: 10;">
                                {{ $info['currency_code'] }} — {{ $info['currency_name'] }}
                            </div>
                            @php $prevCurrency = $info['currency_code']; @endphp
                        @endif

                        {{-- Rate Row --}}
                        <div class="px-2 py-1.5 border-b border-gray-100 hover:bg-gray-50 text-xs">
                            <div class="text-gray-600 font-semibold mb-1">{{ $info['denomination_label'] }}</div>
                            <div class="grid grid-cols-2 gap-2">
                                <div class="font-mono text-green-700 text-right">
                                    {{ $info['buy_rate'] > 0 ? number_format($info['buy_rate'], 4) : '-' }}
                                </div>
                                <div class="font-mono text-red-700 text-right">
                                    {{ $info['sell_rate'] > 0 ? number_format($info['sell_rate'], 4) : '-' }}
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</div>
</div>{{-- end flex --}}

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css">
<script>
function buyForm() {
    return {
        cameraOpen: false, capturedImage: null, croppedImage: null, stream: null, cropper: null,
        step: 'camera', ocrLoading: false, ocrError: '',

        callLw(method, ...args) {
            const el = document.querySelector('[wire\\:id]');
            if (!el) return;
            Livewire.find(el.getAttribute('wire:id'))[method](...args);
        },

        openCamera() {
            this.step = 'camera'; this.capturedImage = null; this.croppedImage = null; this.ocrError = '';
            this.cameraOpen = true;
            this.$nextTick(() => {
                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                    .then(s => { this.stream = s; this.$refs.video.srcObject = s; })
                    .catch(() => navigator.mediaDevices.getUserMedia({ video: true })
                        .then(s => { this.stream = s; this.$refs.video.srcObject = s; }).catch(() => {}));
            });
        },
        closeCamera() {
            if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
            this.destroyCropper(); this.cameraOpen = false; this.capturedImage = null; this.croppedImage = null; this.ocrError = ''; this.step = 'camera';
        },
        capturePhoto() {
            const v = this.$refs.video, c = this.$refs.canvas;
            c.width = v.videoWidth; c.height = v.videoHeight; c.getContext('2d').drawImage(v, 0, 0);
            this.capturedImage = c.toDataURL('image/jpeg', 0.9);
            if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
            this.step = 'crop'; this.$nextTick(() => this.initCropper());
        },
        async initCropper() {
            this.destroyCropper();
            if (typeof Cropper === 'undefined') {
                await new Promise((r, j) => { const s = document.createElement('script'); s.src = 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js'; s.onload = r; s.onerror = j; document.head.appendChild(s); });
            }
            await this.$nextTick();
            if (this.$refs.cropImg) this.cropper = new Cropper(this.$refs.cropImg, { viewMode: 1, dragMode: 'move', autoCropArea: 0.8, responsive: true, background: false });
        },
        destroyCropper() { if (this.cropper) { this.cropper.destroy(); this.cropper = null; } },
        async applyCrop() {
            if (!this.cropper) return;
            this.croppedImage = this.cropper.getCroppedCanvas({ maxWidth: 2000, maxHeight: 2000 }).toDataURL('image/jpeg', 0.92);
            this.destroyCropper(); this.step = 'done'; await this.runOcr(this.croppedImage);
        },
        async skipCrop() { this.croppedImage = this.capturedImage; this.destroyCropper(); this.step = 'done'; await this.runOcr(this.capturedImage); },
        async runOcr(imageData) {
            if (!imageData) return; this.ocrLoading = true; this.ocrError = '';
            try {
                if (typeof Tesseract === 'undefined') { await new Promise((r, j) => { const s = document.createElement('script'); s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js'; s.onload = r; s.onerror = () => j(new Error('ไม่สามารถโหลด Tesseract.js')); document.head.appendChild(s); }); }
                const { data } = await Tesseract.recognize(imageData, 'eng', { logger: m => { if (m.status === 'recognizing text') this.ocrError = 'กำลังอ่าน... ' + Math.round(m.progress * 100) + '%'; } });
                const parsed = this.parseMRZ(data.text);
                if (parsed.passportNo) { this.callLw('receiveOcrData', parsed); this.callLw('receivePassportImage', this.capturedImage); this.ocrError = ''; }
                else { this.ocrError = 'ไม่พบข้อมูล MRZ — ลอง Crop เฉพาะแถบตัวอักษรด้านล่าง passport'; }
            } catch(e) { this.ocrError = 'เกิดข้อผิดพลาด: ' + e.message; } finally { this.ocrLoading = false; }
        },
        parseMRZ(text) {
            const result = { firstName: '', lastName: '', nationality: '', dob: '', expiry: '', passportNo: '' };
            const rawLines = text.split('\n').map(l => l.trim()).filter(l => l.length > 10);
            let rawLine1 = null;
            for (const rl of rawLines) {
                const upper = rl.toUpperCase().replace(/\s+/g, '');
                if (/^P.{0,2}[A-Z]{3}/.test(upper)) { rawLine1 = upper; break; }
            }
            let cleaned = text.toUpperCase().replace(/[«»‹›\u00AB\u00BB\u2039\u203A\{\}\[\]\|~`\\]/g, '<');
            const lines = cleaned.split('\n').map(l => l.replace(/\s+/g, '')).map(l => l.replace(/[^A-Z0-9<]/g, '<')).filter(l => l.length >= 20);
            let line2 = lines.find(l => /^\d{2,}/.test(l) && l.length >= 28) || lines.find(l => /\d{6,}/.test(l));
            let line1 = rawLine1 ? rawLine1.replace(/[^A-Z0-9<]/g, '<') : (lines.find(l => /^P</.test(l)) || lines.find(l => l.includes('<<') && /[A-Z]{4,}/.test(l)));
            if (line1) {
                const l1 = (line1 + '<'.repeat(44)).substring(0, 44);
                if (l1.match(/^P/)) {
                    const cc = l1.substring(2, 5).replace(/</g, ''); if (cc.length >= 2) result.nationality = cc;
                    const ns = l1.startsWith('P<') ? 5 : (l1.indexOf('<') > 0 ? l1.indexOf('<') + 1 : 5);
                    const np = l1.substring(ns), sep = np.indexOf('<<');
                    if (sep !== -1) { result.lastName = np.substring(0,sep).replace(/</g,' ').trim(); result.firstName = np.substring(sep+2).replace(/</g,' ').trim(); }
                    else { const pts = np.split('<').filter(p => p.length > 0); if (pts.length >= 2) { result.lastName = pts[0]; result.firstName = pts.slice(1).join(' '); } else if (pts.length === 1) result.lastName = pts[0]; }
                }
            }
            if (line2) {
                const l2 = (line2 + '<'.repeat(44)).substring(0, 44);
                result.passportNo = l2.substring(0,9).replace(/</g,'').replace(/[^A-Z0-9]/g,'');
                if (!result.nationality) result.nationality = l2.substring(10,13).replace(/</g,'');
                const dob = l2.substring(13,19), exp = l2.substring(21,27);
                if (/^\d{6}$/.test(dob)) { const yy = parseInt(dob.substring(0,2)); result.dob = (yy <= parseInt(new Date().getFullYear().toString().substring(2))?'20':'19') + dob.substring(0,2)+'-'+dob.substring(2,4)+'-'+dob.substring(4,6); }
                if (/^\d{6}$/.test(exp)) result.expiry = '20'+exp.substring(0,2)+'-'+exp.substring(2,4)+'-'+exp.substring(4,6);
            }
            return result;
        },
        confirmCapture() { this.callLw('receivePassportImage', this.capturedImage); this.destroyCropper(); this.closeCamera(); },
        handleFileSelect(event) {
            const file = event.target.files[0]; if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => { this.capturedImage = e.target.result; this.cameraOpen = true; this.step = 'crop'; this.$nextTick(() => this.initCropper()); };
            reader.readAsDataURL(file); event.target.value = '';
        }
    }
}
</script>
</div>
