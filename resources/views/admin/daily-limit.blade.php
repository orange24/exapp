@extends('layouts.app')

@section('title', 'วงเงินต่อคนต่อวัน (Daily Limit)')

@section('content')
<div class="mb-4">
    <h2 class="text-lg font-semibold text-gray-800">วงเงินต่อคนต่อวัน (Daily Limit per Customer)</h2>
    <p class="text-sm text-gray-500">
        จำกัดยอดเงินบาทที่ลูกค้าหนึ่งคนทำรายการหน้าเคาน์เตอร์ได้ในหนึ่งวัน ถ้าเกินจะบันทึกบิลไม่ได้
        — ไม่รวมซื้อจาก/ขายให้ธนาคาร
    </p>
</div>

@if (session('success'))
    <div class="mb-4 p-3 bg-green-50 border border-green-300 text-green-700 rounded text-sm max-w-2xl">
        {{ session('success') }}
    </div>
@endif

<form method="POST" action="{{ route('admin.daily-limit.update') }}" class="max-w-2xl space-y-4">
    @csrf
    @method('PUT')

    @foreach (['buy' => ['รับซื้อ (Buy)', 'ลูกค้านำเงินตราต่างประเทศมาขายให้ร้าน', $buy],
               'sell' => ['จ่ายขาย (Sell)', 'ลูกค้านำเงินบาทมาซื้อเงินตราต่างประเทศ', $sell]] as $key => [$title, $hint, $config])
        <div class="bg-white shadow rounded-lg p-6" x-data="{ on: {{ old($key . '_enabled', $config['enabled']) ? 'true' : 'false' }} }">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-base font-semibold text-gray-800">{{ $title }}</h3>
                    <p class="text-xs text-gray-500">{{ $hint }}</p>
                </div>
                <label class="flex items-center gap-2 cursor-pointer text-sm font-medium">
                    <input type="hidden" name="{{ $key }}_enabled" value="0">
                    <input type="checkbox" name="{{ $key }}_enabled" value="1" x-model="on" class="w-4 h-4 accent-green-700">
                    <span x-text="on ? 'เปิดใช้วงเงิน' : 'ปิด (ไม่จำกัด)'" :class="on ? 'text-[#0e513a]' : 'text-gray-500'"></span>
                </label>
            </div>

            <div class="mt-4" :class="on ? '' : 'opacity-50'">
                <label for="{{ $key }}_amount" class="block text-sm font-medium text-gray-700 mb-1">
                    วงเงินต่อคนต่อวัน (บาท)
                </label>
                <input type="number" id="{{ $key }}_amount" name="{{ $key }}_amount" step="0.01" min="0"
                       value="{{ old($key . '_amount', $config['amount'] == floor($config['amount']) ? (int) $config['amount'] : $config['amount']) }}"
                       class="w-full sm:w-64 border border-gray-300 rounded-lg px-3 py-2 text-sm text-right focus:ring-2 focus:ring-green-500">
                @error($key . '_amount') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                <p class="mt-2 text-xs text-gray-500" x-show="on">
                    เมื่อเปิดใช้ ทุกบิล{{ $key === 'buy' ? 'รับซื้อ' : 'จ่ายขาย' }}ต้องเลือกหรือสแกนข้อมูลลูกค้า
                    เพื่อนับยอดรวมของคนนั้นทุกสาขาในบริษัทเดียวกัน (นับตามเลขบัตร/พาสปอร์ต ตามวันที่ปฏิทิน)
                </p>
            </div>
        </div>
    @endforeach

    <div class="flex justify-end">
        <button type="submit"
                class="bg-[#0e513a] text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-[#0a3d2d] transition-colors">
            บันทึก (Save)
        </button>
    </div>
</form>
@endsection
