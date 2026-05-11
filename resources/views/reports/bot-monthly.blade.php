@extends('layouts.app')
@section('title', 'รายงานประจำเดือน ธปท.')
@section('content')
<div class="max-w-7xl mx-auto">

    {{-- Settings --}}
    <div class="bg-white rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">รายงานธุรกรรมรายเดือน ธปท. (BOT MC Monthly Report)</h2>

        @if (session('success'))
            <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm">{{ session('success') }}</div>
        @endif

        {{-- Provider Info Settings --}}
        <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-lg">
            <h3 class="text-sm font-semibold text-gray-700 mb-3">ข้อมูลบุคคลรับอนุญาต (Provider Info)</h3>
            <form method="POST" action="{{ route('reports.bot-monthly.settings') }}" class="flex flex-wrap items-end gap-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">รหัสสถาบัน (เลขนิติบุคคล 13 หลัก)</label>
                    <input type="text" name="institution_code" value="{{ $settings['institution_code'] }}" maxlength="13" placeholder="1234567890123"
                           class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-44">
                    @error('institution_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">License No (ธปท.)</label>
                    <input type="text" name="license_no" value="{{ $settings['license_no'] }}" placeholder="MC125990001"
                           class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-40">
                    @error('license_no') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">ชื่อบุคคลรับอนุญาต</label>
                    <input type="text" name="company_name" value="{{ $settings['company_name'] }}" placeholder="บริษัท ... จำกัด"
                           class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-56">
                    @error('company_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                </div>
                <button type="submit" class="bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-gray-800">บันทึกข้อมูล</button>
            </form>
        </div>

        {{-- Report Filter --}}
        <form method="GET" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">เดือน</label>
                <select name="month" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ str_pad($m, 2, '0', STR_PAD_LEFT) }}" {{ $month == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' }}>
                            {{ $m }} - {{ ['','ม.ค.','ก.พ.','มี.ค.','เม.ย.','พ.ค.','มิ.ย.','ก.ค.','ส.ค.','ก.ย.','ต.ค.','พ.ย.','ธ.ค.'][$m] }}
                        </option>
                    @endfor
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">ปี (ค.ศ.)</label>
                <input type="number" name="year" value="{{ $year }}" min="2020" max="2030" class="border border-gray-300 rounded-lg px-3 py-2 text-sm w-24">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">สาขา</label>
                <select name="branch_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    <option value="">ทั้งหมด</option>
                    @foreach ($branches as $b)
                        <option value="{{ $b->id }}" {{ $branchId == $b->id ? 'selected' : '' }}>{{ $b->branch_name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">แสดงข้อมูล</button>
            <button type="submit" formmethod="POST" formaction="{{ route('reports.bot-monthly.export') }}"
                    class="bg-green-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-green-700 inline-flex items-center gap-1">
                @csrf
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                ดาวน์โหลด Excel (ธปท.)
            </button>
        </form>

        @if ($settings['institution_code'] === '' || $settings['license_no'] === '')
            <div class="mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm text-yellow-800">
                กรุณากรอกข้อมูลบุคคลรับอนุญาต (รหัสสถาบัน + License No) ก่อนดาวน์โหลดรายงาน
            </div>
        @endif
    </div>

    {{-- Preview: Buy FX --}}
    <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
        <div class="px-4 py-3 border-b" style="background:#C6EFCE;">
            <h3 class="font-semibold text-gray-800">รายงานการซื้อเงินตราต่างประเทศ (Buy FX) — {{ count($buyData) }} รายการ</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead><tr class="bg-gray-100">
                    <th class="px-2 py-2 text-left">วันที่</th>
                    <th class="px-2 py-2 text-left">ประเภทลูกค้า</th>
                    <th class="px-2 py-2 text-left">ชื่อลูกค้า</th>
                    <th class="px-2 py-2 text-left">เอกสาร</th>
                    <th class="px-2 py-2 text-left">สัญชาติ</th>
                    <th class="px-2 py-2 text-left">สกุลเงิน</th>
                    <th class="px-2 py-2 text-right">อัตรา</th>
                    <th class="px-2 py-2 text-right">จำนวน FX</th>
                    <th class="px-2 py-2 text-right">จำนวน THB</th>
                </tr></thead>
                <tbody>
                    @forelse ($buyData as $idx => $tx)
                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                        <td class="px-2 py-1">{{ \Carbon\Carbon::parse($tx->trns_date)->format('d/m') }}</td>
                        <td class="px-2 py-1">{{ $tx->id_type === 'national_id' ? 'คนไทย' : 'ต่างชาติ' }}</td>
                        <td class="px-2 py-1">{{ $tx->cust_name ?? '-' }}</td>
                        <td class="px-2 py-1 font-mono">{{ $tx->id_number ?? '-' }}</td>
                        <td class="px-2 py-1">{{ $tx->nationality ?? '-' }}</td>
                        <td class="px-2 py-1 font-medium">{{ $tx->currency_code }}</td>
                        <td class="px-2 py-1 text-right font-mono">{{ number_format($tx->unit_price, 4) }}</td>
                        <td class="px-2 py-1 text-right font-mono">{{ number_format($tx->amount, 2) }}</td>
                        <td class="px-2 py-1 text-right font-mono">{{ number_format($tx->total, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-4 text-center text-gray-400">ไม่มีรายการซื้อ</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Preview: Sell FX --}}
    <div class="bg-white rounded-lg shadow overflow-hidden">
        <div class="px-4 py-3 border-b" style="background:#FFF2CC;">
            <h3 class="font-semibold text-gray-800">รายงานการขายเงินตราต่างประเทศ (Sell FX) — {{ count($sellData) }} รายการ</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead><tr class="bg-gray-100">
                    <th class="px-2 py-2 text-left">วันที่</th>
                    <th class="px-2 py-2 text-left">ประเภทลูกค้า</th>
                    <th class="px-2 py-2 text-left">ชื่อลูกค้า</th>
                    <th class="px-2 py-2 text-left">เอกสาร</th>
                    <th class="px-2 py-2 text-left">สัญชาติ</th>
                    <th class="px-2 py-2 text-left">สกุลเงิน</th>
                    <th class="px-2 py-2 text-right">อัตรา</th>
                    <th class="px-2 py-2 text-right">จำนวน FX</th>
                    <th class="px-2 py-2 text-right">จำนวน THB</th>
                </tr></thead>
                <tbody>
                    @forelse ($sellData as $idx => $tx)
                    <tr class="{{ $idx % 2 === 0 ? 'bg-white' : 'bg-gray-50' }}">
                        <td class="px-2 py-1">{{ \Carbon\Carbon::parse($tx->trns_date)->format('d/m') }}</td>
                        <td class="px-2 py-1">{{ $tx->id_type === 'national_id' ? 'คนไทย' : 'ต่างชาติ' }}</td>
                        <td class="px-2 py-1">{{ $tx->cust_name ?? '-' }}</td>
                        <td class="px-2 py-1 font-mono">{{ $tx->id_number ?? '-' }}</td>
                        <td class="px-2 py-1">{{ $tx->nationality ?? '-' }}</td>
                        <td class="px-2 py-1 font-medium">{{ $tx->currency_code }}</td>
                        <td class="px-2 py-1 text-right font-mono">{{ number_format($tx->unit_price, 4) }}</td>
                        <td class="px-2 py-1 text-right font-mono">{{ number_format($tx->amount, 2) }}</td>
                        <td class="px-2 py-1 text-right font-mono">{{ number_format($tx->total, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="px-4 py-4 text-center text-gray-400">ไม่มีรายการขาย</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Instructions --}}
    <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800">
        <h4 class="font-semibold mb-2">ขั้นตอนการส่งรายงาน ธปท.</h4>
        <ol class="list-decimal list-inside space-y-1">
            <li>กรอกข้อมูลบุคคลรับอนุญาต (ด้านบน) แล้วกดบันทึก</li>
            <li>เลือกเดือน/ปี แล้วกด "ดาวน์โหลด Excel (ธปท.)" — ชื่อไฟล์จะเป็นไปตามมาตรฐาน ธปท.</li>
            <li>นำไฟล์ Excel ไป Encrypt ด้วยโปรแกรม <strong>DAP Encryption Application</strong></li>
            <li>ส่งไฟล์ที่ Encrypt แล้วผ่านระบบ <strong>DMS Data Acquisition</strong> ของ ธปท.</li>
            <li>ส่งภายใน <strong>4 วันทำการ</strong> หลังสิ้นเดือน</li>
        </ol>
    </div>
</div>
@endsection
