<div class="p-4" x-data="buyForm()" x-ref="lwRoot">
<livewire:card-reader.inbox />

{{-- ข้อมูลจากชิปพาสปอร์ต — เชื่อถือได้กว่า OCR ทุกประการ
     รูปนี้มาจากชิป ไม่ใช่รูปที่พิมพ์บนหน้ากระดาษซึ่งอาจถูกเปลี่ยน --}}
@if ($chipAuthenticity !== '')
    <div class="mb-3 p-3 rounded border flex items-start gap-3
        @if ($chipAuthenticity === 'verified') bg-green-50 border-green-300
        @elseif ($chipAuthenticity === 'failed') bg-red-50 border-red-400
        @else bg-gray-50 border-gray-300 @endif">

        @if ($chipPhoto !== '')
            <img src="{{ $chipPhoto }}" alt="รูปจากชิปพาสปอร์ต"
                 style="width:72px; height:96px; object-fit:cover; border-radius:4px; flex-shrink:0;">
        @endif

        <div class="text-sm">
            @if ($chipAuthenticity === 'verified')
                <div class="font-semibold text-green-800">ยืนยันจากชิปแล้ว — ลายเซ็นของประเทศผู้ออกถูกต้อง</div>
                <div class="text-green-700 text-xs mt-1">ข้อมูลด้านล่างมาจากชิป ไม่ใช่จากการอ่านรูป</div>
            @elseif ($chipAuthenticity === 'failed')
                <div class="font-semibold text-red-800">ลายเซ็นของชิปไม่ผ่านการตรวจ — ข้อมูลอาจถูกแก้ไข</div>
                <div class="text-red-700 text-xs mt-1">ต้องให้ผู้จัดการอนุมัติก่อนทำรายการ</div>
            @else
                <div class="font-semibold text-gray-700">อ่านชิปได้ แต่ยืนยันประเทศผู้ออกไม่ได้</div>
                <div class="text-gray-600 text-xs mt-1">
                    ระบบไม่มีใบรับรองของประเทศนี้ ไม่ได้แปลว่าพาสปอร์ตมีปัญหา
                </div>
            @endif

            @if ($chipPhoto === '')
                <div class="text-xs text-gray-500 mt-1">รูปจากชิปอยู่ในรูปแบบที่แสดงไม่ได้</div>
            @endif
        </div>
    </div>
@endif


{{-- Sanction screening — แถบเตือน + ช่องขออนุมัติจากผู้จัดการ --}}
<x-sanction.alert :screening="$sanctionScreening" :matches="$sanctionMatches" />

@if ($showSanctionApproval)
    <div class="mb-4 border rounded p-4 bg-white" wire:key="sanction-approval">
        <div class="font-semibold mb-2">ขออนุมัติทำรายการต่อ</div>

        <div class="grid gap-2 max-w-md">
            <label class="text-sm">ผู้มีสิทธิ์อนุมัติ (อีเมล)
                <input type="email" wire:model="approverEmail" class="w-full border rounded px-2 py-1">
            </label>
            @error('approverEmail') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror

            <label class="text-sm">รหัสผ่าน
                <input type="password" wire:model="approverPassword" class="w-full border rounded px-2 py-1">
            </label>

            <label class="text-sm">เหตุผล (บังคับ อย่างน้อย 20 ตัวอักษร)
                <textarea wire:model="approvalReason" rows="2" class="w-full border rounded px-2 py-1"
                          placeholder="เช่น วันเกิดไม่ตรง ต่างกัน 12 ปี ตรวจพาสปอร์ตเล่มจริงแล้ว"></textarea>
            </label>
            @error('approvalReason') <div class="text-red-600 text-xs">{{ $message }}</div> @enderror

            <div class="flex gap-2 mt-1">
                <button type="button" wire:click="submitSanctionApproval"
                        class="px-3 py-1.5 rounded text-white text-sm" style="background:#0e513a;">
                    ยืนยันอนุมัติ
                </button>
                <button type="button" wire:click="$set('showSanctionApproval', false)"
                        class="px-3 py-1.5 rounded border text-sm">ยกเลิก</button>
            </div>
        </div>
    </div>
@endif

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
        <h2 class="text-xl font-bold text-gray-800">รับซื้อเงินตราต่างประเทศ (Buy)</h2>
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

    {{-- เงินบาทในลิ้นชักไม่พอจ่าย — เตือนหลังบันทึก ไม่บล็อก --}}
    @if ($thbWarning)
        <div class="mb-4 flex items-start gap-2 rounded border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800">
            <svg class="mt-0.5 h-4 w-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <span>{{ $thbWarning }}</span>
        </div>
    @endif

    {{-- Customer + Passport capture --}}
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
                {{-- Autocomplete dropdown --}}
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
            <button type="button"
                    @click="openCamera()"
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
                    {{-- Autocomplete dropdown --}}
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
                    {{--
                        วันเกิดเป็นตัวแยกแยะที่แรงที่สุดเวลาชื่อไปตรงกับรายชื่อ
                        บุคคลต้องห้าม — ชื่ออย่าง MOHAMED/AHMED พบได้ทั่วไป
                        แต่ถ้ารู้วันเกิดด้วย ระบบจะตัดคนที่ไม่ใช่ออกได้เอง
                        โดยพนักงานไม่ต้องขออนุมัติ
                    --}}
                    <label class="text-gray-500 text-xs">วันเกิด (ช่วยลดการขออนุมัติ)</label>
                    <input type="date" wire:model.blur="ocrDob"
                           class="w-full border border-green-300 rounded px-2 py-1 text-sm font-semibold focus:ring-2 focus:ring-green-400 bg-white">
                </div>
                <div>
                    <label class="text-gray-500 text-xs">Expiry</label>
                    <input type="text" wire:model.blur="ocrExpiry"
                           placeholder="วันหมดอายุ"
                           class="w-full border border-green-300 rounded px-2 py-1 text-sm font-semibold focus:ring-2 focus:ring-green-400 bg-white">
                </div>
            </div>
            {{-- Save button (shown after transaction saved) --}}
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
                        <span x-show="step==='crop'">2. ปรับ/Crop รูป — ลากกรอบเลือกเฉพาะส่วน MRZ</span>
                        <span x-show="step==='done'">3. ผลลัพธ์</span>
                    </h3>
                    <button @click="closeCamera()" style="color:#888; font-size:24px; background:none; border:none; cursor:pointer;">&times;</button>
                </div>

                {{-- Step 1: Camera --}}
                <div x-show="step==='camera'">
                    <div style="position:relative; margin-bottom:12px;">
                        <video x-ref="video" autoplay playsinline
                               style="width:100%; border-radius:8px; max-height:360px; background:#000;"></video>
                        <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none;">
                            <div style="width:85%; height:70%; border:4px dashed #facc15; border-radius:8px; opacity:0.5;"></div>
                        </div>
                    </div>
                    <canvas x-ref="canvas" style="display:none;"></canvas>
                    <div style="display:flex; gap:10px;">
                        <button @click="capturePhoto()" type="button"
                                style="flex:1; padding:10px; background:#eab308; color:#000; font-weight:700; border:none; border-radius:8px; cursor:pointer;">
                            ถ่ายรูป
                        </button>
                    </div>
                </div>

                {{-- Step 2: Crop --}}
                <div x-show="step==='crop'">
                    <div style="margin-bottom:12px; background:#000; border-radius:8px; overflow:hidden; max-height:450px;">
                        <img x-ref="cropImg" :src="capturedImage" style="max-width:100%; display:block;">
                    </div>
                    <p style="color:#aaa; font-size:12px; margin-bottom:10px;">
                        ลากกรอบเลือกเฉพาะส่วนที่ต้องการอ่าน OCR (เช่น แถบ MRZ ด้านล่าง หรือหน้าข้อมูลทั้งหมด)
                    </p>
                    <div style="display:flex; gap:10px;">
                        <button @click="applyCrop()" type="button"
                                style="flex:1; padding:10px; background:#16a34a; color:#fff; font-weight:700; border:none; border-radius:8px; cursor:pointer;">
                            Crop & อ่านข้อมูล (OCR)
                        </button>
                        <button @click="destroyCropper(); capturedImage=null; croppedImage=null; step='camera'; $nextTick(() => { navigator.mediaDevices.getUserMedia({video:{facingMode:'environment'}}).then(s=>{stream=s;$refs.video.srcObject=s}).catch(()=>navigator.mediaDevices.getUserMedia({video:true}).then(s=>{stream=s;$refs.video.srcObject=s}).catch(()=>{})) })" type="button"
                                style="padding:10px 16px; background:#444; color:#ccc; font-weight:600; border:none; border-radius:8px; cursor:pointer;">
                            ถ่ายใหม่
                        </button>
                    </div>
                </div>

                {{-- Step 3: Result preview --}}
                <div x-show="step==='done'">
                    <template x-if="croppedImage">
                        <div style="margin-bottom:12px;">
                            <img :src="croppedImage" style="width:100%; border-radius:8px; max-height:200px; object-fit:contain; background:#000;">
                        </div>
                    </template>
                {{-- ผลการสแกน — เดิมสำเร็จแล้วเงียบสนิท พนักงานแยกไม่ออกว่า
                     "อ่านได้แล้ว" กับ "ยังไม่ได้ทำอะไร" ต่างกันตรงไหน --}}
                <template x-if="ocrResult && !ocrLoading">
                    <div style="margin:10px 0 12px; padding:10px 12px; border-radius:8px;"
                         :style="ocrAllChecksPassed()
                            ? 'background:#064e3b; border:1px solid #10b981;'
                            : 'background:#78350f; border:1px solid #f59e0b;'">
                        <div style="font-weight:700; margin-bottom:6px;"
                             :style="ocrAllChecksPassed() ? 'color:#6ee7b7;' : 'color:#fcd34d;'"
                             x-text="ocrAllChecksPassed()
                                ? 'อ่านข้อมูลสำเร็จ — เลขตรวจสอบของพาสปอร์ตถูกต้อง'
                                : 'อ่านได้ แต่บางตัวเลขอาจเพี้ยน — ตรวจกับเล่มจริงก่อนบันทึก'"></div>

                        <table style="width:100%; font-size:13px; color:#e5e7eb;">
                            <tr>
                                <td style="padding:2px 0; width:110px; opacity:.75;">เลขพาสปอร์ต</td>
                                <td style="font-family:monospace;" x-text="ocrResult.passportNo || '-'"></td>
                                <td style="width:24px; text-align:right;" x-html="ocrMark(ocrResult.checks.passportNo)"></td>
                            </tr>
                            <tr>
                                <td style="padding:2px 0; opacity:.75;">ชื่อ</td>
                                <td colspan="2" x-text="((ocrResult.firstName || '') + ' ' + (ocrResult.lastName || '')).trim() || '-'"></td>
                            </tr>
                            <tr>
                                <td style="padding:2px 0; opacity:.75;">สัญชาติ</td>
                                <td colspan="2" x-text="ocrResult.nationality || '-'"></td>
                            </tr>
                            <tr>
                                <td style="padding:2px 0; opacity:.75;">วันเกิด</td>
                                <td style="font-family:monospace;" x-text="ocrResult.dob || '-'"></td>
                                <td style="text-align:right;" x-html="ocrMark(ocrResult.checks.dob)"></td>
                            </tr>
                            <tr>
                                <td style="padding:2px 0; opacity:.75;">วันหมดอายุ</td>
                                <td style="font-family:monospace;" x-text="ocrResult.expiry || '-'"></td>
                                <td style="text-align:right;" x-html="ocrMark(ocrResult.checks.expiry)"></td>
                            </tr>
                        </table>
                    </div>
                </template>

                    <div style="display:flex; gap:10px;">
                        <button @click="step='crop'; initCropper();" type="button"
                                style="flex:1; padding:10px; background:#444; color:#ccc; font-weight:600; border:none; border-radius:8px; cursor:pointer;">
                            Crop ใหม่
                        </button>
                        <button @click="confirmCapture()" type="button"
                                style="flex:1; padding:10px; background:#2563eb; color:#fff; font-weight:700; border:none; border-radius:8px; cursor:pointer;">
                            ยืนยัน
                        </button>
                    </div>
                </div>


                <div x-show="ocrLoading" style="margin-top:10px; padding:8px 12px; background:#1e3a5f; border-radius:8px; text-align:center;">
                    <span style="color:#facc15; font-weight:600;" x-text="ocrError || 'กำลังประมวลผล OCR...'"></span>
                </div>
                <div x-show="ocrError && !ocrLoading" style="margin-top:8px; color:#f87171; font-size:13px;" x-text="ocrError"></div>
            </div>
        </div>
    </template>

    {{-- Add currency row (hide after save) --}}
    @if (! $showPrintSlip)
    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
        <div class="flex flex-wrap gap-3 items-end">
            <div style="width:200px; flex-shrink:0;">
                <label class="block text-sm font-medium text-gray-700 mb-1">สกุลเงิน</label>
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
                    อัตราซื้อ
                    @unless ($this->canEditRate)
                        <span class="text-xs font-normal text-gray-400">(ดูอย่างเดียว)</span>
                    @endunless
                </label>
                @if ($this->canEditRate)
                    <input type="number" wire:model.blur="currentRate" min="0" step="0.000001"
                           class="w-full border border-blue-400 rounded px-2 py-2 text-sm text-right font-mono">
                @else
                    <input type="text" value="{{ $currentRate > 0 ? format_rate($currentRate) : '' }}"
                           readonly disabled
                           class="w-full border border-gray-300 bg-gray-100 text-gray-600 rounded px-2 py-2 text-sm text-right font-mono cursor-not-allowed">
                @endif
            </div>
            <div style="width:120px; flex-shrink:0;">
                <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน</label>
                <x-number-input model="addAmount" :value="$addAmount"
                       x-ref="amountInput"
                       @keydown.enter.prevent="commit($el); $wire.addRow().then(() => { $nextTick(() => $refs.currencySelect.focus()); })"
                       class="w-full border border-gray-300 rounded px-2 py-2 text-sm" />
            </div>
            <div>
                <button wire:click="addRow" type="button"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-bold">
                    + เพิ่ม
                </button>
            </div>
        </div>
    </div>

    @endif

    {{-- Transaction rows table (active or saved) --}}
    @php $displayRows = $showPrintSlip ? $savedRows : $rows; @endphp
    @if (count($displayRows) > 0)
        <div class="mb-4 overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-[#0e513a] text-white">
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">สกุลเงิน</th>
                        <th class="px-3 py-2 text-right">จำนวน</th>
                        <th class="px-3 py-2 text-right">อัตราซื้อ</th>
                        <th class="px-3 py-2 text-right">รวม THB</th>
                        @if (! $showPrintSlip)
                        <th class="px-3 py-2 text-center w-16">ลบ</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @foreach ($displayRows as $i => $row)
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-3 py-2 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-3 py-2 font-semibold">{{ $row['currency_code'] }}
                                <span class="text-gray-500 font-normal text-xs">{{ $row['currency_name'] }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($row['amount'], 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-green-700">{{ format_rate($row['rate']) }}</td>
                            <td class="px-3 py-2 text-right font-mono font-bold">{{ number_format($row['total'], 2) }}</td>
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
                        <td colspan="{{ $showPrintSlip ? 4 : 4 }}" class="px-3 py-2 text-right">รวมทั้งหมด (THB)</td>
                        <td class="px-3 py-2 text-right font-mono text-lg text-blue-800">
                            {{ number_format(collect($displayRows)->sum('total'), 2) }}
                        </td>
                        @if (! $showPrintSlip)<td></td>@endif
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    {{-- Print slip via hidden iframe + auto print --}}
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

    {{-- Save & Print button --}}
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
                                    {{ $info['buy_rate'] > 0 ? format_rate($info['buy_rate']) : '-' }}
                                </div>
                                <div class="font-mono text-red-700 text-right">
                                    {{ $info['sell_rate'] > 0 ? format_rate($info['sell_rate']) : '-' }}
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
        cameraOpen: false,
        capturedImage: null,
        croppedImage: null,
        stream: null,
        cropper: null,
        step: 'camera',
        ocrLoading: false,
        callLw(method, ...args) {
            // ต้องหาคอมโพเนนต์ของฟอร์มนี้ ไม่ใช่ตัวแรกที่เจอในหน้า
            //
            // เดิมใช้ document.querySelector ซึ่งคืนกระดิ่งแจ้งเตือนบน header
            // เพราะมันถูกวาดก่อน @yield('content') ผลคือ OCR ไปเรียก
            // receiveOcrData บนกระดิ่งแล้วได้ 500 ทุกครั้ง
            //
            // $el คือ root ของคอมโพเนนต์นี้เอง ซึ่งเป็นตัวที่ Livewire ใส่ wire:id ให้
            const el = this.$el.closest('[wire\\:id]');
            if (!el) { console.error('ไม่พบคอมโพเนนต์ Livewire ของฟอร์มนี้'); return; }
            const id = el.getAttribute('wire:id');
            Livewire.find(id)[method](...args);
        },
        ocrError: '',
        ocrResult: null,

        /** เลขตรวจสอบผ่านครบทุกช่องที่ตรวจได้ไหม — ช่องที่อ่านไม่ออกไม่นับว่าไม่ผ่าน */
        ocrAllChecksPassed() {
            if (!this.ocrResult) return false;

            return Object.values(this.ocrResult.checks || {}).every(v => v !== false);
        },

        ocrMark(ok) {
            if (ok === true) return '<span style="color:#34d399;">&#10003;</span>';
            if (ok === false) return '<span style="color:#fca5a5;">&#10007;</span>';

            return '<span style="color:#9ca3af;">&ndash;</span>';
        },

        openCamera() {
            this.step = 'camera';
            this.capturedImage = null;
            this.croppedImage = null;
            this.ocrError = '';
            this.cameraOpen = true;
            this.$nextTick(() => {
                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                    .then(s => { this.stream = s; this.$refs.video.srcObject = s; })
                    .catch(() => navigator.mediaDevices.getUserMedia({ video: true })
                        .then(s => { this.stream = s; this.$refs.video.srcObject = s; })
                        .catch(() => {})
                    );
            });
        },

        closeCamera() {
            if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
            this.destroyCropper();
            this.cameraOpen = false;
            this.capturedImage = null;
            this.croppedImage = null;
            this.ocrError = '';
            this.ocrResult = null;
            this.step = 'camera';
        },

        capturePhoto() {
            const video = this.$refs.video, canvas = this.$refs.canvas;
            canvas.width = video.videoWidth; canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            this.capturedImage = canvas.toDataURL('image/jpeg', 0.9);
            if (this.stream) { this.stream.getTracks().forEach(t => t.stop()); this.stream = null; }
            this.step = 'crop';
            this.$nextTick(() => this.initCropper());
        },

        async initCropper() {
            this.destroyCropper();
            // Load Cropper.js if not loaded
            if (typeof Cropper === 'undefined') {
                await new Promise((resolve, reject) => {
                    const s = document.createElement('script');
                    s.src = 'https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js';
                    s.onload = resolve; s.onerror = reject;
                    document.head.appendChild(s);
                });
            }
            await this.$nextTick();
            const img = this.$refs.cropImg;
            if (img) {
                this.cropper = new Cropper(img, {
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 0.8,
                    responsive: true,
                    background: false,
                });
            }
        },

        destroyCropper() {
            if (this.cropper) { this.cropper.destroy(); this.cropper = null; }
        },

        async applyCrop() {
            if (!this.cropper) return;
            const canvas = this.cropper.getCroppedCanvas({ maxWidth: 2000, maxHeight: 2000 });
            this.croppedImage = canvas.toDataURL('image/jpeg', 0.92);
            this.destroyCropper();
            this.step = 'done';
            await this.runOcr(this.croppedImage);
        },

        async skipCrop() {
            this.croppedImage = this.capturedImage;
            this.destroyCropper();
            this.step = 'done';
            await this.runOcr(this.capturedImage);
        },

        async runOcr(imageData) {
            if (!imageData) return;
            this.ocrLoading = true;
            this.ocrError = '';
            this.ocrResult = null;
            try {
                if (typeof Tesseract === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const s = document.createElement('script');
                        s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
                        s.onload = resolve; s.onerror = () => reject(new Error('ไม่สามารถโหลด Tesseract.js ได้'));
                        document.head.appendChild(s);
                    });
                }
                const { data } = await this.recognizeMrz(imageData);
                const parsed = this.parseMRZ(data.text);
                if (parsed.passportNo) {
                    this.callLw('receiveOcrData', parsed);
                    this.callLw('receivePassportImage', this.capturedImage);
                    this.ocrError = '';
                    this.ocrResult = parsed;
                    this.askForChip(parsed);
                } else {
                    this.ocrError = 'ไม่พบข้อมูล MRZ — ลอง Crop เฉพาะแถบตัวอักษรด้านล่าง passport';
                }
            } catch (e) {
                this.ocrError = 'เกิดข้อผิดพลาด: ' + e.message;
            } finally { this.ocrLoading = false; }
        },

        /*
         * เลขตรวจสอบของ MRZ ตาม ICAO 9303 — น้ำหนัก 7,3,1 วนไป
         * A=10 ... Z=35, '<'=0
         *
         * มีไว้บอกว่า OCR อ่านถูกหรือเพี้ยน ซึ่งเป็นสิ่งเดียวที่แยกสองอย่างนี้ออกได้
         * โดยไม่ต้องให้คนไปเทียบกับเล่มจริงทีละตัว — OCR สับสน 0 กับ O และ 1 กับ I
         * เป็นประจำ และตัวเลขที่เพี้ยนไปตัวเดียวทำให้เทียบรายชื่อ ปปง. พลาดทั้งใบ
         */
        mrzCheckDigit(value) {
            const weights = [7, 3, 1];
            let sum = 0;

            for (let i = 0; i < value.length; i++) {
                const c = value[i];
                let v;

                if (c >= '0' && c <= '9') v = c.charCodeAt(0) - 48;
                else if (c >= 'A' && c <= 'Z') v = c.charCodeAt(0) - 55;
                else if (c === '<') v = 0;
                else return null;

                sum += v * weights[i % 3];
            }

            return String(sum % 10);
        },

        /** คืน true/false เมื่อตรวจได้ คืน null เมื่อ OCR อ่านเลขตรวจสอบไม่ออก */
        mrzFieldOk(value, expected) {
            if (!/^\d$/.test(expected)) return null;

            const got = this.mrzCheckDigit(value);

            return got === null ? null : got === expected;
        },

        /*
         * อ่าน MRZ โดยบอก Tesseract ว่ากำลังอ่านอะไรอยู่
         *
         * เดิมเรียก Tesseract.recognize(img, 'eng') เฉย ๆ ซึ่งใช้โมเดลที่ฝึกมา
         * อ่านข้อความร้อยแก้ว มันไม่เคยคาดหวังตัว '<' เลยเลือกตัวอักษรหน้าตา
         * ใกล้เคียงแทน — ได้ C, L, K ปนกันจนชื่อกลายเป็นขยะ
         *
         * whitelist บังคับให้เลือกได้เฉพาะอักขระที่มีจริงใน MRZ
         * psm 6 บอกว่าเป็นบล็อกข้อความสองบรรทัด ไม่ใช่หน้าเอกสารที่ต้องวิเคราะห์เลย์เอาต์
         */
        async recognizeMrz(imageData) {
            const worker = await Tesseract.createWorker('eng', 1, {
                logger: m => {
                    if (m.status === 'recognizing text') {
                        this.ocrError = 'กำลังอ่าน... ' + Math.round(m.progress * 100) + '%';
                    }
                },
            });

            try {
                await worker.setParameters({
                    tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789<',
                    tessedit_pageseg_mode: '6',
                });

                return await worker.recognize(imageData);
            } finally {
                await worker.terminate();
            }
        },

        /*
         * ตำแหน่งที่มาตรฐานกำหนดว่าต้องเป็นตัวเลขล้วน ให้ดัดตัวอักษรที่หน้าตาคล้ายกลับเป็นเลข
         *
         * OCR อ่าน 0 เป็น O และ 8 เป็น B ได้ง่ายมาก และ whitelist ช่วยไม่ได้
         * เพราะตัวอักษรก็เป็นอักขระที่ถูกต้องใน MRZ เหมือนกัน แต่ ICAO กำหนดไว้ว่า
         * ช่องวันเกิดและวันหมดอายุเป็นตัวเลขล้วนเสมอ จึงดัดได้อย่างปลอดภัย
         *
         * ดัดแล้วเลขตรวจสอบจะบอกเองว่าดัดถูกหรือไม่
         */
        mrzDigitsOnly(s) {
            const map = { O: '0', Q: '0', D: '0', I: '1', L: '1', Z: '2', S: '5', B: '8', G: '6', T: '7', A: '4' };

            return s.split('').map(c => map[c] ?? c).join('');
        },

        /*
         * ขอให้ agent เปิดชิป โดยส่งกุญแจที่เพิ่งอ่านได้ไปฝากไว้ที่เซิร์ฟเวอร์
         *
         * ส่งเฉพาะตอนเลขตรวจสอบผ่านครบ เพราะกุญแจที่ผิดแม้ตัวเดียวจะเปิดชิปไม่ได้
         * แล้วพนักงานจะเห็นแค่ "อ่านชิปไม่สำเร็จ" โดยไม่รู้ว่าสาเหตุอยู่ที่การถ่ายรูป
         */
        askForChip(parsed) {
            const c = parsed.checks || {};
            const ready = c.passportNo === true && c.dob === true && c.expiry === true;

            if (!ready || !parsed.passportNo) return;

            const yymmdd = iso => iso ? iso.substring(2).split('-').join('') : '';

            this.callLw('requestPassportChip', parsed.passportNo, yymmdd(parsed.dob), yymmdd(parsed.expiry));
        },

        parseMRZ(text) {
            const result = { firstName: '', lastName: '', nationality: '', dob: '', expiry: '', passportNo: '', checks: {} };

            const lines = text.toUpperCase()
                .replace(/[«»‹›«»‹›\{\}\[\]\|~`\\]/g, '<')
                .split('\n')
                .map(l => l.replace(/\s+/g, ''))
                .filter(l => l.length >= 25);

            // บรรทัดสองคือบรรทัดที่มีตัวเลขหนาแน่น — OCR อ่านได้แม่นกว่าและมีเลขตรวจสอบ
            // กำกับทุกช่อง จึงใช้เป็นหลักยึด แล้วค่อยไปหาชื่อจากบรรทัดหนึ่ง
            const line2 = lines
                .map(l => l.replace(/[^A-Z0-9<]/g, '<'))
                .filter(l => (l.match(/\d/g) || []).length >= 12)
                .sort((a, b) => (b.match(/\d/g) || []).length - (a.match(/\d/g) || []).length)[0];

            if (line2) {
                const l2 = (line2 + '<'.repeat(44)).substring(0, 44);

                result.passportNo = l2.substring(0, 9).replace(/</g, '');
                result.nationality = l2.substring(10, 13).replace(/[^A-Z]/g, '');

                const dob = this.mrzDigitsOnly(l2.substring(13, 19));
                const exp = this.mrzDigitsOnly(l2.substring(21, 27));

                if (/^\d{6}$/.test(dob)) {
                    const yy = parseInt(dob.substring(0, 2), 10);
                    const thisYy = parseInt(String(new Date().getFullYear()).substring(2), 10);
                    result.dob = (yy <= thisYy ? '20' : '19') + dob.substring(0, 2) + '-' + dob.substring(2, 4) + '-' + dob.substring(4, 6);
                }

                if (/^\d{6}$/.test(exp)) {
                    result.expiry = '20' + exp.substring(0, 2) + '-' + exp.substring(2, 4) + '-' + exp.substring(4, 6);
                }

                result.checks = {
                    passportNo: this.mrzFieldOk(l2.substring(0, 9), this.mrzDigitsOnly(l2.charAt(9))),
                    dob: this.mrzFieldOk(dob, this.mrzDigitsOnly(l2.charAt(19))),
                    expiry: this.mrzFieldOk(exp, this.mrzDigitsOnly(l2.charAt(27))),
                };
            }

            const names = this.parseMrzNames(lines, line2, result.nationality);
            result.lastName = names.lastName;
            result.firstName = names.firstName;

            return result;
        },

        /*
         * แกะชื่อจากบรรทัดหนึ่ง โดยยึดรหัสประเทศที่ได้จากบรรทัดสอง
         *
         * เดิมยึดจากตำแหน่งคงที่ แล้วพังทันทีที่ OCR อ่าน '<' ตัวแรกเป็นตัวอักษร
         * เพราะตัวหา nameStart ไปเจอ '<' ตัวแรกในหางของบรรทัดแทน ได้ชื่อเป็นขยะ
         * และสลับนามสกุลกับชื่อ
         *
         * รหัสประเทศจากบรรทัดสองเชื่อถือได้มากกว่า เพราะบรรทัดนั้น OCR อ่านแม่นกว่า
         */
        /*
         * ตัดเศษอักขระเติมเต็มที่ท้ายชื่อ แล้วเก็บส่วนที่เป็นชื่อจริงไว้
         *
         * เดิมเจอตัวซ้ำสามตัวแล้วลบทั้งก้อน ซึ่งทำให้ "WATCHARACCC" กลายเป็นว่าง
         * ทั้งที่ชื่อจริงอยู่ครบ — ตัดที่จุดซ้ำแล้วเหลือ "WATCHARA" ถูกต้องกว่า
         *
         * ที่เหลือสั้นกว่าสองตัวถือว่ากู้ไม่ได้ ปล่อยว่างให้พนักงานพิมพ์เอง
         * ขยะที่ถูกเติมลงไปจะถูกบันทึกเป็นชื่อลูกค้าแล้วเอาไปเทียบรายชื่อ ปปง.
         * โดยไม่มีใครทันสังเกต
         */
        trimMrzNoise(value) {
            const run = value.replace(/\s+/g, ' ').match(/(.)\1{2,}/);
            let out = run ? value.substring(0, run.index) : value;

            out = out.replace(/[^A-Z ]+$/, '').replace(/\s+/g, ' ').trim();

            return out.length >= 2 ? out : '';
        },

        parseMrzNames(lines, line2, nationality) {
            const out = { firstName: '', lastName: '' };

            const line1 = lines.find(l => l !== line2 && /^P/.test(l)) || lines.find(l => l !== line2);
            if (!line1) return out;

            let namePart = null;

            if (nationality && nationality.length === 3) {
                const at = line1.indexOf(nationality);
                if (at > 0) namePart = line1.substring(at + 3);
            }

            if (namePart === null) namePart = line1.substring(5);

            /*
             * ช่องชื่อกว้าง 39 ตัวตามมาตรฐาน (ตำแหน่ง 5 ถึง 43) เติมให้เต็มก่อนเสมอ
             *
             * ถ้าไม่เติม แล้ว OCR อ่านหางมาสั้น ๆ ตัวอักษรในชื่อจริงจะถูกนับเป็น
             * อักขระเติมเต็ม — 'A' ใน WATCHARA โผล่สามครั้งในหางสั้น ๆ แล้วชื่อ
             * กลายเป็น WATCHAR
             */
            namePart = (namePart + '<'.repeat(39)).substring(0, 39);

            /*
             * หา "ชุด" ของอักขระเติมเต็ม ไม่ใช่ตัวเดียว
             *
             * ของจริงที่เจอ: OCR อ่าน '<' เป็น C, S และ L ปนกันในใบเดียวกัน
             * การสมมติว่าเป็นตัวเดียวทำให้หาตัวคั่น '<<' ระหว่างนามสกุลกับชื่อไม่เจอ
             * (มันถูกอ่านเป็น "CS") แล้วชื่อกับนามสกุลติดกันเป็นก้อนเดียว
             *
             * ท้ายบรรทัดยาว 15 ตัวสุดท้ายเป็นส่วนเติมเต็มแทบแน่นอน เพราะชื่อคน
             * ไม่ยาวพอจะกินพื้นที่ 39 ตัวของช่องชื่อ ตัวที่โผล่ซ้ำตรงนั้นคือตัวเติมเต็ม
             */
            const tailZone = namePart.slice(-15);
            const freq = {};
            for (const ch of tailZone) freq[ch] = (freq[ch] ?? 0) + 1;

            const fillers = new Set(
                Object.entries(freq).filter(([, n]) => n >= 3).map(([ch]) => ch),
            );
            fillers.add('<');

            /*
             * แทนเฉพาะที่ติดกันตั้งแต่สองตัวขึ้นไป ตัวเดี่ยวปล่อยไว้
             *
             * WATCHARA มี C อยู่ตัวเดียวโดด ๆ ถ้าแทนทุกตัวจะกลายเป็น WAT HARA
             * ส่วนตัวคั่นในมาตรฐานเป็นสองตัวติดกันเสมอ จึงไม่เสียอะไร
             */
            let cleaned = '';
            for (let i = 0; i < namePart.length; ) {
                let j = i;
                while (j < namePart.length && fillers.has(namePart[j])) j++;

                const run = j - i;
                cleaned += run >= 2 ? '<'.repeat(run) : namePart.substring(i, Math.max(j, i + 1));
                i = Math.max(j, i + 1);
            }

            cleaned = cleaned.replace(/<+$/, '');

            const sep = cleaned.indexOf('<<');
            if (sep !== -1) {
                out.lastName = cleaned.substring(0, sep).replace(/</g, ' ').trim();
                out.firstName = cleaned.substring(sep + 2).replace(/</g, ' ').trim();
            } else {
                const parts = cleaned.split('<').filter(p => p.length > 0);
                out.lastName = parts[0] ?? '';
                out.firstName = parts.slice(1).join(' ');
            }

            out.lastName = this.trimMrzNoise(out.lastName);
            out.firstName = this.trimMrzNoise(out.firstName);

            return out;
        },

        confirmCapture() {
            this.callLw('receivePassportImage', this.capturedImage);
            this.destroyCropper();
            this.closeCamera();
        },

        handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) return;
            const reader = new FileReader();
            reader.onload = (e) => {
                this.capturedImage = e.target.result;
                this.cameraOpen = true;
                this.step = 'crop';
                this.$nextTick(() => this.initCropper());
            };
            reader.readAsDataURL(file);
            event.target.value = '';
        }
    }
}
</script>
</div>
