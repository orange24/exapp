<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบเสร็จ {{ $transaction->trns_no }}</title>
    @php
        $isBuy = $transaction->trns_type === 'BUYING';
        // BUYING: details.amount = เงินต่างประเทศที่รับมา, details.total = THB ที่จ่ายลูกค้า
        // SELLING: details.amount = THB ที่รับจากลูกค้า,   details.total = เงินต่างประเทศที่จ่ายลูกค้า
        // ยอดรวมของสลิปจึงต้องอ่านคนละคอลัมน์กัน ไม่งั้น SELLING จะพิมพ์เลข USD ใต้ป้าย (THB)
        $grandThb = $isBuy
            ? $transaction->details->sum('total')
            : $transaction->details->sum('amount');
    @endphp
    <style>
        /* กระดาษความร้อน 80mm — พื้นที่พิมพ์จริง 72mm, ปล่อยความสูงตามเนื้อหาเพื่อให้ตัดม้วนพอดี */
        @page { size: 80mm auto; margin: 0; }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            width: 80mm;
            padding: 3mm 4mm 6mm 4mm;
            font-family: 'TH Sarabun New', 'Noto Sans Thai', 'DejaVu Sans', Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.35;
            color: #000;
            -webkit-font-smoothing: none;
        }

        /* หัวเครื่องพิมพ์ความร้อนเป็นขาวดำ — พื้นทึบจะกลายเป็นแถบดำเลอะ ใช้เส้นคั่นแทน */
        .rule       { border-top: 1pt solid #000; margin: 2mm 0; }
        .rule-thick { border-top: 2.5pt solid #000; margin: 2mm 0; }
        .rule-dash  { border-top: 1pt dashed #000; margin: 2mm 0; }

        .center { text-align: center; }
        .shop   { font-size: 20pt; font-weight: bold; letter-spacing: .3pt; }
        .shop-sub { font-size: 11pt; }

        .kind { font-size: 15pt; font-weight: bold; text-align: center; margin: 1.5mm 0; }

        /* label ซ้าย / ค่าขวา — ชิดขอบทั้งสองข้างเสมอแม้ค่าจะยาว */
        .line { display: flex; justify-content: space-between; align-items: baseline; gap: 3mm; }
        .line .k { font-size: 11pt; white-space: nowrap; }
        .line .v { font-size: 12pt; font-weight: bold; text-align: right; word-break: break-all; }

        table.items { width: 100%; border-collapse: collapse; table-layout: fixed; margin: 1mm 0; }
        table.items th {
            font-size: 9pt; font-weight: bold; text-align: right; padding: 0 0 0.8mm 0;
            border-bottom: 1pt solid #000;
        }
        table.items th.cur { text-align: left; }
        table.items td {
            font-family: 'Courier New', monospace; font-size: 8.5pt; text-align: right;
            padding: 0.4mm 1mm; white-space: nowrap;
        }
        table.items td.cur { font-family: inherit; font-weight: bold; font-size: 10pt; text-align: left; padding-left: 0; }
        table.items col.cur  { width: 11%; }
        table.items col.amt  { width: 28%; }
        table.items col.rate { width: 24%; }
        table.items col.thb  { width: 37%; }

        .total-label { font-size: 12pt; font-weight: bold; }
        .total-value {
            font-family: 'Courier New', monospace;
            font-size: 22pt;
            font-weight: bold;
            text-align: right;
            line-height: 1.1;
        }

        .sig { margin-top: 8mm; }
        .sig-line { border-top: 1pt solid #000; padding-top: 1mm; margin-top: 9mm; font-size: 10pt; text-align: center; }

        .foot { margin-top: 3mm; font-size: 9.5pt; text-align: center; }
        .thanks { font-size: 11pt; font-weight: bold; text-align: center; margin-top: 2mm; }

        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    {{-- ── ร้าน ───────────────────────────────────────────── --}}
    <div class="center">
        <div class="shop">{{ config('app.company_name', 'FX Exchange') }}</div>
        <div class="shop-sub">สาขา {{ $transaction->counter->branch->branch_name ?? '—' }}</div>
        <div class="shop-sub">{{ $transaction->counter_name }}</div>
    </div>

    <div class="rule-thick"></div>
    <div class="kind">{{ $isBuy ? 'ใบรับซื้อเงินตรา (BUY)' : 'ใบขายเงินตรา (SELL)' }}</div>
    <div class="rule-thick"></div>

    {{-- ── หัวเอกสาร ─────────────────────────────────────── --}}
    <div class="line"><span class="k">เลขที่</span><span class="v">{{ $transaction->trns_no }}</span></div>
    <div class="line"><span class="k">วันที่</span><span class="v">{{ $transaction->trns_datetime->format('d/m/Y H:i') }}</span></div>
    <div class="line"><span class="k">ลูกค้า</span><span class="v">{{ $transaction->cust_name ?: '—' }}</span></div>
    @if ($transaction->customer?->id_number)
        <div class="line"><span class="k">Passport</span><span class="v">{{ $transaction->customer->id_number }}</span></div>
    @endif
    @if ($transaction->customer?->nationality)
        <div class="line"><span class="k">สัญชาติ</span><span class="v">{{ $transaction->customer->nationality }}</span></div>
    @endif

    <div class="rule"></div>

    {{-- ── รายการ ────────────────────────────────────────── --}}
    <table class="items">
        <colgroup>
            <col class="cur"><col class="amt"><col class="rate"><col class="thb">
        </colgroup>
        <thead>
            <tr>
                <th class="cur">CUR</th>
                <th>AMOUNT</th>
                <th>RATE</th>
                <th>BAHT</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transaction->details as $detail)
                @php
                    $fcAmount = $isBuy ? $detail->amount : $detail->total;
                    $thbAmount = $isBuy ? $detail->total : $detail->amount;
                @endphp
                <tr>
                    <td class="cur">{{ $detail->currency_code }}</td>
                    <td>{{ number_format($fcAmount, 2) }}</td>
                    <td>{{ number_format($detail->unit_price, 4) }}</td>
                    <td>{{ number_format($thbAmount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="rule-thick"></div>

    {{-- ── ยอดรวม (เป็น THB เสมอ) ────────────────────────── --}}
    <div class="total-label">{{ $isBuy ? 'รวมจ่ายลูกค้า (THB)' : 'รวมรับจากลูกค้า (THB)' }}</div>
    <div class="total-value">{{ number_format($grandThb, 2) }}</div>
    <div class="rule-thick"></div>

    {{-- ── ลายเซ็น ───────────────────────────────────────── --}}
    <div class="sig">
        <div class="sig-line">ลายมือชื่อพนักงาน / Cashier</div>
        <div class="sig-line">ลายมือชื่อลูกค้า / Customer</div>
    </div>

    <div class="rule-dash"></div>
    <div class="foot">
        พิมพ์เมื่อ {{ now()->format('d/m/Y H:i:s') }}<br>
        ผู้พิมพ์ {{ $transaction->createdBy?->name ?? auth()->user()?->name ?? '—' }}
    </div>
    <div class="thanks">ขอบคุณที่ใช้บริการ / Thank you</div>

    <script>
        window.onload = function() {
            @if(request('print') === 'Y')
            window.print();
            @endif
        };
    </script>
</body>
</html>
