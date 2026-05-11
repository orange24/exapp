<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="300">{{-- auto-refresh every 5 minutes --}}
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>อัตราแลกเปลี่ยน — {{ $counter->counter_name }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: #000;
            color: #fff;
            font-family: 'Segoe UI', Arial, sans-serif;
            overflow-x: hidden;
        }

        /* Top bar */
        .top-bar {
            background: #111;
            padding: 8px 16px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #333;
        }
        .top-bar .title {
            font-size: var(--header-size, 28px);
            font-weight: bold;
            color: #FFD700;
            letter-spacing: 2px;
        }
        .top-bar .meta {
            font-size: 14px;
            color: #aaa;
            text-align: right;
        }
        .top-bar .meta strong {
            color: #fff;
        }

        /* Controls (staff view only) */
        .controls {
            background: #1a1a1a;
            padding: 6px 16px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            border-bottom: 1px solid #333;
        }
        .controls button {
            background: #333;
            color: #fff;
            border: 1px solid #555;
            padding: 4px 12px;
            cursor: pointer;
            border-radius: 3px;
            font-size: 13px;
        }
        .controls button:hover { background: #555; }
        .controls .sep { color: #555; line-height: 26px; }

        /* Rate table */
        .rate-table {
            width: 100%;
            border-collapse: collapse;
        }
        .rate-table th {
            background: #1a1a2e;
            color: #FFD700;
            text-align: center;
            padding: 10px 8px;
            font-size: var(--header-size, 20px);
            border-bottom: 2px solid #444;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .rate-table td {
            border-bottom: 1px solid #333;
            vertical-align: middle;
            padding: 6px 8px;
        }
        .row-even td { background: #0a0a0a; }
        .row-odd td { background: #141414; }
        .rate-table tr:hover td { background: #1a1a2e; }

        /* Flag + currency name cell */
        .td-flag {
            text-align: left;
            min-width: 330px;
            padding: 8px 12px 8px 20px !important;
        }
        .td-flag img {
            width: var(--img-width, 80px);
            height: auto;
            display: inline-block;
            vertical-align: middle;
        }
        .td-flag .flag-info {
            display: inline-block;
            vertical-align: middle;
            text-align: left;
            margin-left: 8px;
        }

        /* Currency label */
        .td-currency {
            color: #fff;
            font-weight: bold;
            font-size: var(--digit-size, 26px);
            text-align: center;
            white-space: nowrap;
        }
        .td-currency .currency-code {
            font-size: var(--digit-size, 26px);
            display: block;
        }
        .td-currency .currency-name {
            font-size: calc(var(--digit-size, 26px) * 0.55);
            color: #aaa;
            display: block;
        }

        /* Buy rate */
        .td-buy {
            text-align: right;
            font-size: var(--digit-size, 60px);
            font-weight: bold;
            color: #00FF7F;  /* green */
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
            padding-right: 20px !important;
            line-height: var(--line-height, 60px);
        }

        /* Sell rate */
        .td-sell {
            text-align: right;
            font-size: var(--digit-size, 60px);
            font-weight: bold;
            color: #FF4444;  /* red */
            font-family: 'Courier New', monospace;
            letter-spacing: 2px;
            padding-right: 20px !important;
            line-height: var(--line-height, 60px);
        }

        .zero-rate { color: #333 !important; }

        /* Last update footer */
        .footer-bar {
            background: #111;
            border-top: 2px solid #333;
            padding: 6px 16px;
            display: flex;
            justify-content: space-between;
            font-size: 13px;
            color: #888;
            position: fixed;
            bottom: 0;
            width: 100%;
        }
        .footer-bar strong { color: #FFD700; }
    </style>
</head>
<body>
    {{-- Top bar --}}
    <div class="top-bar">
        <div class="title">อัตราแลกเปลี่ยน — {{ $counter->counter_name }}</div>
        <div class="meta">
            <strong>{{ $counter->branch->branch_name ?? '' }}</strong><br>
            <span id="clock"></span>
        </div>
    </div>

    {{-- Font/Image size controls (hidden in full-screen kiosk mode, visible for staff) --}}
    <div class="controls" id="controls">
        <span style="color:#aaa; line-height:26px;">ขนาดตัวอักษร:</span>
        <button onclick="changeFontSize(5)">+ A</button>
        <button onclick="changeFontSize(-5)">- A</button>
        <span class="sep">|</span>
        <span style="color:#aaa; line-height:26px;">ขนาดธง:</span>
        <button onclick="changeImgSize(10)">+ รูป</button>
        <button onclick="changeImgSize(-10)">- รูป</button>
        <span class="sep">|</span>
        <button onclick="toggleControls()" style="background:#222; color:#666;">ซ่อน</button>
    </div>

    {{-- Rate table --}}
    <table class="rate-table">
        <thead>
            <tr>
                <th style="min-width:330px; text-align:left; padding-left:20px">Country</th>
                <th style="text-align:left">Currency</th>
                <th style="width:30%; text-align:right; padding-right:20px">Buy price</th>
                <th style="width:30%; text-align:right; padding-right:20px">Sell price</th>
            </tr>
        </thead>
        <tbody>
            @php
                // Group rates by currency_code, preserving seq order
                $grouped = $rates->filter(fn($r) => $r->rate_buy > 0 || $r->rate_sell > 0)
                                 ->groupBy('currency_code');
                $colorIdx = 0;
            @endphp
            @foreach ($grouped as $currencyCode => $denomRates)
                @php
                    $rowCount = $denomRates->count();
                    $rowClass = $colorIdx % 2 === 0 ? 'row-even' : 'row-odd';
                    $first = $denomRates->first();
                    $flagName = strtolower($currencyCode);
                    $flagPath = public_path("images/flags/{$flagName}.png");
                    $colorIdx++;
                @endphp
                @foreach ($denomRates->values() as $i => $rate)
                    <tr class="{{ $rowClass }}">
                        {{-- Flag + Currency name: only on first row of group (rowspan) --}}
                        @if ($i === 0)
                            <td class="td-flag" rowspan="{{ $rowCount }}" style="border-right:1px solid #333; border-bottom:2px solid #333;">
                                @if (file_exists($flagPath))
                                    <img src="{{ asset('images/flags/' . $flagName . '.png') }}"
                                         alt="{{ $currencyCode }}">
                                @endif
                                <div class="flag-info">
                                    <div style="color:#FFD700; font-weight:bold; font-size:calc(var(--digit-size,26px) * 0.7); line-height:1.2;">{{ $currencyCode }}</div>
                                    <div style="color:#888; font-size:calc(var(--digit-size,26px) * 0.35); line-height:1.2;">{{ $first->currency?->country ?? '' }}</div>
                                </div>
                            </td>
                        @endif

                        {{-- Denomination label --}}
                        <td class="td-currency" style="text-align:left; padding-left:16px;">
                            <span class="currency-code" style="font-size:calc(var(--digit-size,26px) * 0.8);">{{ $rate->denomination?->denom_label ?? '' }}</span>
                        </td>

                        {{-- Buy --}}
                        <td class="td-buy {{ $rate->rate_buy == 0 ? 'zero-rate' : '' }}">
                            {{ $rate->rate_buy > 0 ? rtrim(rtrim(number_format($rate->rate_buy, 4), '0'), '.') : '—' }}
                        </td>

                        {{-- Sell --}}
                        <td class="td-sell {{ $rate->rate_sell == 0 ? 'zero-rate' : '' }}">
                            {{ $rate->rate_sell > 0 ? rtrim(rtrim(number_format($rate->rate_sell, 4), '0'), '.') : '—' }}
                        </td>
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    {{-- Footer --}}
    <div class="footer-bar">
        <span>อัปเดตล่าสุด: <strong>{{ $lastUpdate }}</strong></span>
        <span>รีเฟรชอัตโนมัติทุก 5 นาที</span>
    </div>

    <script>
        // Font size control
        let fontSize   = 60;
        let imgWidth   = 80;
        let lineHeight = 60;
        let headerSize = 28;

        function applyStyles() {
            document.documentElement.style.setProperty('--digit-size',  fontSize + 'px');
            document.documentElement.style.setProperty('--line-height', lineHeight + 'px');
            document.documentElement.style.setProperty('--img-width',   imgWidth + 'px');
            document.documentElement.style.setProperty('--header-size', headerSize + 'px');
        }

        function changeFontSize(delta) {
            fontSize    = Math.max(20, Math.min(120, fontSize + delta));
            lineHeight  = fontSize;
            headerSize  = Math.max(16, headerSize + Math.round(delta * 0.5));
            applyStyles();
        }

        function changeImgSize(delta) {
            imgWidth = Math.max(40, Math.min(200, imgWidth + delta));
            applyStyles();
        }

        function toggleControls() {
            const ctrl = document.getElementById('controls');
            ctrl.style.display = ctrl.style.display === 'none' ? '' : 'none';
        }

        // Live clock
        function updateClock() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            const el = document.getElementById('clock');
            if (el) el.textContent = h + ':' + m + ':' + s;
        }
        setInterval(updateClock, 1000);
        updateClock();

        applyStyles();
    </script>
</body>
</html>
