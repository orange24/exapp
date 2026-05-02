<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>ใบเสร็จ {{ $transaction->trns_no }}</title>
    <style>
        @page { size: A5 portrait; margin: 10mm 8mm 10mm 8mm; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'TH Sarabun New', 'Noto Sans Thai', 'DejaVu Sans', Arial, sans-serif;
            font-size: 10pt;
            color: #000;
        }
        .header { border-bottom: 2pt solid #1E3A5F; padding-bottom: 6px; margin-bottom: 8px; }
        .company-name { font-size: 16pt; font-weight: bold; color: #1E3A5F; }
        .doc-info { font-size: 9pt; color: #444; }
        table { width: 100%; border-collapse: collapse; margin: 6px 0; }
        th { background: #1E3A5F; color: #fff; padding: 4px 6px; font-size: 9pt; text-align: center; }
        td { border: 0.5pt solid #999; padding: 4px 6px; font-size: 9pt; }
        .amount { text-align: right; font-family: 'Courier New', monospace; }
        .total-row { font-weight: bold; background: #f5f5f5; }
        .signature { margin-top: 20px; }
        .sig-line { border-top: 1pt solid #333; width: 180px; margin: 0 auto; padding-top: 4px; text-align: center; font-size: 8pt; color: #555; }
        .footer { margin-top: 12px; font-size: 7.5pt; color: #888; border-top: 0.5pt solid #ccc; padding-top: 4px; }
        @media print {
            body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="header">
        <table style="border:none">
            <tr>
                <td style="border:none; width:60%">
                    <div class="company-name">{{ config('app.company_name', 'FX Exchange') }}</div>
                    <div class="doc-info">สาขา: {{ $transaction->counter->branch->branch_name ?? '' }}
                        | เคาน์เตอร์: {{ $transaction->counter_name }}</div>
                </td>
                <td style="border:none; text-align:right; vertical-align:top">
                    <div class="doc-info">
                        <strong>เลขที่: {{ $transaction->trns_no }}</strong><br>
                        วันที่: {{ $transaction->trns_datetime->format('d/m/Y H:i') }}<br>
                        ประเภท: <strong>{{ $transaction->trns_type === 'BUYING' ? 'ซื้อเงิน (Buy)' : 'ขายเงิน (Sell)' }}</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    {{-- Customer --}}
    <div style="margin-bottom:6px; font-size:9pt;">
        ลูกค้า / Customer: <strong>{{ $transaction->cust_name ?: '—' }}</strong>
        @if ($transaction->customer)
            &nbsp;|&nbsp; เลขที่ Passport: <strong>{{ $transaction->customer->id_number }}</strong>
            &nbsp;|&nbsp; สัญชาติ: <strong>{{ $transaction->customer->nationality }}</strong>
        @endif
    </div>

    {{-- Transaction detail table --}}
    <table>
        <thead>
            <tr>
                <th style="text-align:left">สกุลเงิน</th>
                <th>จำนวน</th>
                <th>อัตราแลกเปลี่ยน</th>
                <th>รวม (THB)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($transaction->details as $detail)
                <tr>
                    <td>{{ $detail->currency_code }}
                        <span style="color:#666; font-size:8pt;">{{ $detail->currency_name }}</span>
                    </td>
                    <td class="amount">{{ number_format($detail->amount, 2) }}</td>
                    <td class="amount">{{ number_format($detail->unit_price, 4) }}</td>
                    <td class="amount">{{ number_format($detail->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr class="total-row">
                <td colspan="3" style="text-align:right; font-weight:bold;">รวมทั้งหมด (THB)</td>
                <td class="amount" style="font-size:11pt; font-weight:bold;">
                    {{ number_format($transaction->details->sum('total'), 2) }}
                </td>
            </tr>
        </tfoot>
    </table>

    {{-- Signatures --}}
    <div class="signature">
        <table style="border:none">
            <tr>
                <td style="border:none; text-align:center; width:50%">
                    <div class="sig-line">ลายมือชื่อพนักงาน / Cashier</div>
                </td>
                <td style="border:none; text-align:center; width:50%">
                    <div class="sig-line">ลายมือชื่อลูกค้า / Customer</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        พิมพ์เมื่อ: {{ now()->format('d/m/Y H:i:s') }}
        | ผู้พิมพ์: {{ $transaction->createdBy?->name ?? auth()->user()?->name ?? '—' }}
        <span style="float:right">ขอบคุณที่ใช้บริการ / Thank you</span>
    </div>

    <script>
        window.onload = function() {
            @if(request('print') === 'Y')
            window.print();
            @endif
        };
    </script>
</body>
</html>
