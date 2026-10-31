{{-- หน้าต่างแก้บิลที่ส่งแบงค์ชาติไม่ได้ — ใช้ภายใน x-data="botFix(...)" ของตารางรายการที่ข้าม --}}
@php
    $idTypes = ['national_id' => 'บัตรประชาชน', 'passport' => 'หนังสือเดินทาง (Passport)'];
@endphp

{{-- แก้ข้อมูลลูกค้าที่ผูกอยู่ --}}
<div x-show="mode === 'customer'" x-transition.opacity style="display: none; position: fixed; inset: 0; z-index: 50; background: rgba(0,0,0,.4);"
     class="flex items-center justify-center p-4" @click.self="close()" @keydown.escape.window="close()">
    <form method="POST" :action="action"
          class="bg-white rounded-lg shadow-xl w-full max-w-md p-5 space-y-4">
        @csrf
        @method('PATCH')
        <div>
            <h3 class="font-semibold text-gray-800">แก้ข้อมูลลูกค้า</h3>
            <p class="text-xs text-gray-500">บิล <span class="font-mono" x-text="trnsNo"></span> · <span x-text="name"></span></p>
            <p class="text-xs text-amber-700 mt-1">แก้ที่ตัวลูกค้า — บิลอื่นของลูกค้าคนนี้จะได้ข้อมูลใหม่ไปด้วย</p>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">สัญชาติ</label>
            <select name="nationality" x-model="form.nationality" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">— เลือกสัญชาติ —</option>
                @foreach ($countries as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1" x-show="raw">ค่าเดิม: <span class="font-mono" x-text="raw"></span></p>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">ประเภทบัตร</label>
                <select name="id_type" x-model="form.id_type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @foreach ($idTypes as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">เลขบัตร</label>
                <input type="text" name="id_number" x-model="form.id_number" required maxlength="50"
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
            </div>
        </div>
        <div class="flex justify-end gap-2 pt-2">
            <button type="button" @click="close()" class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">ยกเลิก</button>
            <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-[#0e513a] text-white font-medium hover:bg-[#0a3d2d]">บันทึก</button>
        </div>
    </form>
</div>

{{-- ผูกลูกค้าเข้าบิล — เลือกคนเดิม หรือสร้างใหม่ --}}
<div x-show="mode === 'link'" x-transition.opacity style="display: none; position: fixed; inset: 0; z-index: 50; background: rgba(0,0,0,.4);"
     class="flex items-center justify-center p-4" @click.self="close()" @keydown.escape.window="close()">
    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg p-5 space-y-4">
        <div>
            <h3 class="font-semibold text-gray-800">ผูกลูกค้าเข้าบิล</h3>
            <p class="text-xs text-gray-500">บิล <span class="font-mono" x-text="trnsNo"></span></p>
        </div>

        <div x-show="!creating">
            <label class="block text-xs font-medium text-gray-600 mb-1">ค้นหาลูกค้า (ชื่อ หรือเลขบัตร)</label>
            <input type="text" x-model="query" @input.debounce.300ms="search()" placeholder="พิมพ์อย่างน้อย 2 ตัวอักษร"
                   class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <ul class="mt-2 max-h-64 overflow-y-auto divide-y divide-gray-100 border border-gray-100 rounded-lg" x-show="results.length">
                <template x-for="c in results" :key="c.id">
                    <li class="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                        <div>
                            <div class="font-medium text-gray-800" x-text="c.name || '—'"></div>
                            <div class="text-xs text-gray-500 font-mono" x-text="[c.id_number, c.nationality].filter(Boolean).join(' · ')"></div>
                        </div>
                        <form method="POST" :action="action">
                            @csrf
                            <input type="hidden" name="customer_id" :value="c.id">
                            <button type="submit" class="text-sm text-[#0e513a] font-medium hover:underline">เลือก</button>
                        </form>
                    </li>
                </template>
            </ul>
            <p class="text-xs text-gray-400 mt-2" x-show="searched && !results.length">ไม่พบลูกค้า</p>
            <button type="button" @click="creating = true" class="mt-3 text-sm text-[#0e513a] font-medium hover:underline">
                + ไม่มีในระบบ สร้างลูกค้าใหม่
            </button>
        </div>

        <form method="POST" :action="action" x-show="creating" class="space-y-3">
            @csrf
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อลูกค้า</label>
                <input type="text" name="name" required maxlength="255" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ประเภทบัตร</label>
                    <select name="id_type" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        @foreach ($idTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">เลขบัตร</label>
                    <input type="text" name="id_number" required maxlength="50" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm font-mono">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">สัญชาติ</label>
                <select name="nationality" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">— เลือกสัญชาติ —</option>
                    @foreach ($countries as $code => $label)
                        <option value="{{ $code }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex justify-between gap-2 pt-2">
                <button type="button" @click="creating = false" class="text-sm text-gray-600 hover:underline">← กลับไปค้นหา</button>
                <button type="submit" class="px-4 py-2 text-sm rounded-lg bg-[#0e513a] text-white font-medium hover:bg-[#0a3d2d]">สร้างและผูกเข้าบิล</button>
            </div>
        </form>

        <div class="flex justify-end" x-show="!creating">
            <button type="button" @click="close()" class="px-4 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">ปิด</button>
        </div>
    </div>
</div>

<script>
    function botFix(customers) {
        return {
            mode: null,
            action: '',
            trnsNo: '',
            name: '',
            raw: '',
            form: { nationality: '', id_type: 'passport', id_number: '' },
            creating: false,
            query: '',
            results: [],
            searched: false,

            openCustomer(trnsNo, name, customerId, action) {
                const c = customers[customerId] || {};
                const nationality = (c.nationality || '').toUpperCase();
                const select = this.$root.querySelector('select[name=nationality]');
                const known = [...select.options].some(o => o.value === nationality);

                Object.assign(this, { mode: 'customer', trnsNo, name, action, raw: known ? '' : (c.nationality || '') });
                this.form = {
                    // ค่าที่อ่านไม่ออก (เช่น "SAR") ไม่อยู่ในรายการ — ให้เลือกใหม่ โดยโชว์ค่าเดิมไว้ข้างล่าง
                    nationality: known ? nationality : '',
                    id_type: c.id_type === 'national_id' ? 'national_id' : 'passport',
                    id_number: c.id_number || '',
                };
            },

            openLink(trnsNo, action) {
                Object.assign(this, { mode: 'link', trnsNo, action, creating: false, query: '', results: [], searched: false });
            },

            close() {
                this.mode = null;
            },

            async search() {
                if (this.query.trim().length < 2) {
                    this.results = [];
                    this.searched = false;
                    return;
                }
                const url = @js(route('reports.bot-submission.customers')) + '?q=' + encodeURIComponent(this.query.trim());
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                this.results = response.ok ? await response.json() : [];
                this.searched = true;
            },
        };
    }
</script>
