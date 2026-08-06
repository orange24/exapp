@props([
    'model',                 // ชื่อ property ของ Livewire เช่น "addAmount"
    'value' => null,         // ค่าปัจจุบัน (ส่งมาจาก component)
    'decimals' => 2,         // ทศนิยมสูงสุดที่พิมพ์ได้
    'onCommit' => null,      // JS ที่จะรันต่อหลัง commit — ใช้ตัวแปร `cleaned`
])

{{--
    ช่องกรอกตัวเลขที่ใส่ , คั่นหลักให้ระหว่างพิมพ์

    ทำไมไม่ใช้ type="number": browser ไม่ยอมให้มี , ใน input[type=number]
    ค่าจะกลายเป็นว่างทันที จึงต้องใช้ type="text" + inputmode="decimal"
    (มือถือยังเด้งแป้นตัวเลขให้อยู่)

    สำคัญ: ค่าที่ส่งกลับ Livewire ต้องถอด , ออกก่อนเสมอ เพราะ property
    หลายตัวเป็น float — ถ้าส่ง "500,000" ไป PHP จะ cast เป็น 500.0 เงียบๆ
    ซึ่งคือข้อมูลผิดแบบไม่มีใครรู้
--}}
@php
    // ค่าว่างและ 0 แสดงเป็นช่องว่าง เพื่อให้ placeholder ทำงานและไม่ต้องลบเลข 0 ทิ้งก่อนพิมพ์
    $display = '';
    if ($value !== null && $value !== '' && (float) $value != 0.0) {
        $display = number_format((float) $value, (int) $decimals, '.', ',');
        if ((int) $decimals > 0 && str_contains($display, '.')) {
            $display = rtrim(rtrim($display, '0'), '.'); // 500,000.00 → 500,000 แต่ 1,234.50 → 1,234.5
        }
    }
@endphp
<input
    type="text"
    inputmode="decimal"
    autocomplete="off"
    value="{{ $display }}"
    x-data="{
        decimals: {{ (int) $decimals }},
        clean(v) {
            // เก็บเฉพาะตัวเลขกับจุดทศนิยมจุดแรก
            let s = String(v ?? '').replace(/[^0-9.]/g, '');
            const parts = s.split('.');
            if (parts.length > 2) s = parts[0] + '.' + parts.slice(1).join('');
            if (this.decimals === 0) s = s.split('.')[0];
            else {
                const [i, d] = s.split('.');
                if (d !== undefined) s = i + '.' + d.slice(0, this.decimals);
            }
            return s;
        },
        format(v) {
            if (v === '' || v === null) return '';
            const [i, d] = String(v).split('.');
            const withCommas = (i === '' ? '0' : i).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            // ระหว่างพิมพ์ต้องคงจุดท้ายไว้ ไม่งั้นพิมพ์ทศนิยมต่อไม่ได้
            return d !== undefined ? withCommas + '.' + d : withCommas;
        },
        onInput(el) {
            const cleaned = this.clean(el.value);
            el.value = this.format(cleaned);
        },
        commit(el) {
            const cleaned = this.clean(el.value);
            el.value = this.format(cleaned);
            $wire.set('{{ $model }}', cleaned === '' ? 0 : cleaned);
            @if ($onCommit)
                {!! $onCommit !!}
            @endif
        },
    }"
    x-on:input="onInput($el)"
    x-on:blur="commit($el)"
    {{ $attributes->merge(['class' => 'text-right font-mono']) }}
/>
