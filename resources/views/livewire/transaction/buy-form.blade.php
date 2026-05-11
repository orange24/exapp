<div class="p-4" x-data="buyForm()" x-ref="lwRoot">
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

    {{-- Customer + Passport capture --}}
    <div class="mb-4 p-3 bg-gray-50 border border-gray-200 rounded">
        <div class="flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-48">
                <label class="block text-sm font-medium text-gray-700 mb-1">ชื่อลูกค้า</label>
                <input type="text" wire:model.blur="custName"
                       placeholder="ชื่อ-นามสกุล / Name"
                       class="w-full border border-gray-300 rounded px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500">
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

        {{-- OCR result preview --}}
        @if ($ocrPassportNo)
            <div class="mt-3 p-2 bg-green-50 border border-green-200 rounded text-sm">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-2">
                    <div><span class="text-gray-500">Passport No:</span> <strong>{{ $ocrPassportNo }}</strong></div>
                    <div><span class="text-gray-500">Name:</span> <strong>{{ $ocrFirstName }} {{ $ocrLastName }}</strong></div>
                    <div><span class="text-gray-500">Nationality:</span> <strong>{{ $ocrNationality }}</strong></div>
                    <div><span class="text-gray-500">Expiry:</span> <strong>{{ $ocrExpiry }}</strong></div>
                </div>
            </div>
        @endif
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

    {{-- Add currency row --}}
    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
        <div class="flex flex-wrap gap-3 items-end">
            <div style="width:200px; flex-shrink:0;">
                <label class="block text-sm font-medium text-gray-700 mb-1">สกุลเงิน</label>
                <select wire:model.live="selectedCurrency"
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
            <div style="width:120px; flex-shrink:0;">
                <label class="block text-sm font-medium text-gray-700 mb-1">จำนวน</label>
                <input type="number" wire:model.blur="addAmount" min="0" step="0.01"
                       x-on:input="$nextTick(() => { let r = {{ $currentRate ?: 0 }}; let t = $el.closest('.bg-blue-50').querySelector('[data-thb-result]'); if(t) t.value = ($el.value * r).toFixed(2); })"
                       class="w-full border border-gray-300 rounded px-2 py-2 text-sm text-right font-mono">
            </div>
            <div style="width:120px; flex-shrink:0;">
                <label class="block text-sm font-medium text-gray-700 mb-1">อัตราซื้อ</label>
                <input type="text" value="{{ number_format($currentRate, 4) }}" readonly
                       class="w-full border border-gray-200 bg-gray-100 rounded px-2 py-2 text-sm text-right font-mono text-green-700">
            </div>
            <div style="width:120px; flex-shrink:0;">
                <label class="block text-sm font-medium text-gray-700 mb-1">รวม THB</label>
                <input type="text" value="{{ $currentTotal > 0 ? number_format($currentTotal, 2) : '0.00' }}" readonly
                       data-thb-result
                       class="w-full border border-gray-200 bg-gray-100 rounded px-2 py-2 text-sm text-right font-mono font-bold">
            </div>
            <div>
                <button wire:click="addRow" type="button"
                        class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm font-bold">
                    + เพิ่ม
                </button>
            </div>
        </div>
    </div>

    {{-- Transaction rows table --}}
    @if (count($rows) > 0)
        <div class="mb-4 overflow-x-auto">
            <table class="w-full text-sm border-collapse">
                <thead>
                    <tr class="bg-[#0e513a] text-white">
                        <th class="px-3 py-2 text-left">#</th>
                        <th class="px-3 py-2 text-left">สกุลเงิน</th>
                        <th class="px-3 py-2 text-right">จำนวน</th>
                        <th class="px-3 py-2 text-right">อัตราซื้อ</th>
                        <th class="px-3 py-2 text-right">รวม THB</th>
                        <th class="px-3 py-2 text-center w-16">ลบ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $row)
                        <tr class="border-b border-gray-200 hover:bg-gray-50">
                            <td class="px-3 py-2 text-gray-400">{{ $i + 1 }}</td>
                            <td class="px-3 py-2 font-semibold">{{ $row['currency_code'] }}
                                <span class="text-gray-500 font-normal text-xs">{{ $row['currency_name'] }}</span>
                            </td>
                            <td class="px-3 py-2 text-right font-mono">{{ number_format($row['amount'], 2) }}</td>
                            <td class="px-3 py-2 text-right font-mono text-green-700">{{ number_format($row['rate'], 4) }}</td>
                            <td class="px-3 py-2 text-right font-mono font-bold">{{ number_format($row['total'], 2) }}</td>
                            <td class="px-3 py-2 text-center">
                                <button wire:click="removeRow({{ $i }})" type="button"
                                        class="text-red-500 hover:text-red-700 font-bold">✕</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="bg-gray-100 font-bold">
                        <td colspan="4" class="px-3 py-2 text-right">รวมทั้งหมด (THB)</td>
                        <td class="px-3 py-2 text-right font-mono text-lg text-blue-800">
                            {{ number_format($this->grandTotal, 2) }}
                        </td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    {{-- Print slip via hidden iframe + auto print --}}
    @if ($showPrintSlip && $savedTransactionId)
        <div class="mb-4 p-3 bg-green-50 border border-green-300 rounded flex items-center justify-between">
            <span class="text-green-700 font-semibold">บันทึกสำเร็จ!</span>
            <button type="button"
                    onclick="document.getElementById('print-iframe').src = '{{ route('transaction.print', $savedTransactionId) }}?print=Y&t=' + Date.now();"
                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 text-sm">
                พิมพ์อีกครั้ง
            </button>
        </div>
        <iframe id="print-iframe" src="{{ route('transaction.print', $savedTransactionId) }}?print=Y"
                style="position:absolute; width:0; height:0; border:none; overflow:hidden;"></iframe>
    @endif

    {{-- Save & Print button --}}
    @if (count($rows) > 0)
        <div class="flex justify-end gap-3">
            <button wire:click="saveTransaction" wire:loading.attr="disabled" type="button"
                    class="px-6 py-2 bg-[#0e513a] text-white rounded font-bold hover:bg-[#0a3d2d] disabled:opacity-60">
                <span wire:loading.remove>บันทึก & พิมพ์</span>
                <span wire:loading>กำลังบันทึก...</span>
            </button>
        </div>
    @endif

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
            const el = document.querySelector('[wire\\:id]');
            if (!el) { console.error('No Livewire element found'); return; }
            const id = el.getAttribute('wire:id');
            Livewire.find(id)[method](...args);
        },
        ocrError: '',

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
            try {
                if (typeof Tesseract === 'undefined') {
                    await new Promise((resolve, reject) => {
                        const s = document.createElement('script');
                        s.src = 'https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js';
                        s.onload = resolve; s.onerror = () => reject(new Error('ไม่สามารถโหลด Tesseract.js ได้'));
                        document.head.appendChild(s);
                    });
                }
                const { data } = await Tesseract.recognize(imageData, 'eng', {
                    logger: m => { if (m.status === 'recognizing text') this.ocrError = 'กำลังอ่าน... ' + Math.round(m.progress * 100) + '%'; }
                });
                const parsed = this.parseMRZ(data.text);
                if (parsed.passportNo) {
                    this.callLw('receiveOcrData', parsed);
                    this.callLw('receivePassportImage', this.capturedImage);
                    this.ocrError = '';
                } else {
                    this.ocrError = 'ไม่พบข้อมูล MRZ — ลอง Crop เฉพาะแถบตัวอักษรด้านล่าง passport';
                }
            } catch (e) {
                this.ocrError = 'เกิดข้อผิดพลาด: ' + e.message;
            } finally { this.ocrLoading = false; }
        },

        parseMRZ(text) {
            const result = { firstName: '', lastName: '', nationality: '', dob: '', expiry: '', passportNo: '' };
            console.log('OCR raw text:', text);

            // Step 1: Work with RAW text first — find P< line before heavy cleaning
            // OCR often reads < as various chars, so look for P followed by country code pattern
            const rawLines = text.split('\n').map(l => l.trim()).filter(l => l.length > 10);

            // Try to find P< line from raw text (before cleaning destroys it)
            let rawLine1 = null;
            for (const rl of rawLines) {
                const upper = rl.toUpperCase().replace(/\s+/g, '');
                // Match: starts with P, then has recognizable name pattern
                if (/^P.{0,2}(RUS|USA|GBR|FRA|DEU|JPN|CHN|KOR|THA|IND|AUS|CAN|SGP|MYS|VNM|IDN|PHL|TWN|HKG|NZL|ARE|SAU|QAT|OMN|BHR|KWT|JOR|TUR|ISR|ZAF|PAK|BRN|MAC|RUB|MEX|BRA|ARG|ITA|ESP|NLD|SWE|NOR|DNK|CHE|[A-Z]{3})/.test(upper)) {
                    rawLine1 = upper;
                    break;
                }
            }

            // Step 2: Clean all lines for line 2 (numbers)
            let cleaned = text.toUpperCase()
                .replace(/[«»‹›\u00AB\u00BB\u2039\u203A\{\}\[\]\|~`\\]/g, '<');

            const lines = cleaned.split('\n')
                .map(l => l.replace(/\s+/g, ''))
                .map(l => l.replace(/[^A-Z0-9<]/g, '<'))
                .filter(l => l.length >= 20);

            console.log('Cleaned lines:', lines);

            // Find line 2 (starts with digits — passport number)
            let line2 = lines.find(l => /^\d{2,}/.test(l) && l.length >= 28);
            if (!line2) line2 = lines.find(l => /\d{6,}/.test(l));

            // Find line 1: use rawLine1 if found, otherwise try cleaned lines
            let line1 = null;
            if (rawLine1) {
                line1 = rawLine1.replace(/[^A-Z0-9<]/g, '<');
            } else {
                line1 = lines.find(l => /^P</.test(l) || /^P[A-Z]{3}/.test(l));
                if (!line1) line1 = lines.find(l => l.includes('<<') && /[A-Z]{4,}/.test(l));
            }

            console.log('MRZ L1:', line1);
            console.log('MRZ L2:', line2);

            // Parse line 1 (name + nationality)
            if (line1) {
                const l1 = (line1 + '<'.repeat(44)).substring(0, 44);
                if (l1.match(/^P/)) {
                    // Find country code (positions 2-5)
                    const countryMatch = l1.substring(2, 5).replace(/</g, '');
                    if (countryMatch.length >= 2) result.nationality = countryMatch;

                    // Find name: everything after P<COUNTRY until end
                    const nameStart = l1.startsWith('P<') ? 5 : (l1.indexOf('<') > 0 ? l1.indexOf('<') + 1 : 5);
                    const namePart = l1.substring(nameStart);
                    const sep = namePart.indexOf('<<');
                    if (sep !== -1) {
                        result.lastName = namePart.substring(0, sep).replace(/</g, ' ').trim();
                        result.firstName = namePart.substring(sep + 2).replace(/</g, ' ').trim();
                    } else {
                        // No << found, try single < as separator
                        const parts = namePart.split('<').filter(p => p.length > 0);
                        if (parts.length >= 2) {
                            result.lastName = parts[0];
                            result.firstName = parts.slice(1).join(' ');
                        } else if (parts.length === 1) {
                            result.lastName = parts[0];
                        }
                    }
                }
            }

            // Parse line 2 (passport no, DOB, expiry)
            if (line2) {
                const l2 = (line2 + '<'.repeat(44)).substring(0, 44);
                result.passportNo = l2.substring(0, 9).replace(/</g, '').replace(/[^A-Z0-9]/g, '');
                if (!result.nationality) result.nationality = l2.substring(10, 13).replace(/</g, '');

                const dob = l2.substring(13, 19), exp = l2.substring(21, 27);
                if (/^\d{6}$/.test(dob)) {
                    const yy = parseInt(dob.substring(0,2));
                    result.dob = (yy <= parseInt(new Date().getFullYear().toString().substring(2)) ? '20':'19') + dob.substring(0,2) + '-' + dob.substring(2,4) + '-' + dob.substring(4,6);
                }
                if (/^\d{6}$/.test(exp)) {
                    result.expiry = '20' + exp.substring(0,2) + '-' + exp.substring(2,4) + '-' + exp.substring(4,6);
                }
            }

            console.log('Parsed result:', result);
            return result;
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
