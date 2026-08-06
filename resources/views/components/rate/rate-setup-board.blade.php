<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Counter;
use App\Models\Currency;
use App\Models\CurrencyDenomination;
use App\Models\CounterRate;
use App\Models\RateChangeLog;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

new class extends Component
{
    public int $counterId;
    public string $counterCode = '';
    public string $counterName = '';
    public array $rates = [];          // keyed by denomination id
    public string $copyFromCode = '';
    public bool $saved = false;

    // ข้อความแจ้งผลของปุ่ม คัดลอกราคา / ดึงราคาตั้งต้น
    // เดิมสองปุ่มนี้ return เงียบเมื่อไม่เจอข้อมูล คนกดเลยแยกไม่ออกว่า
    // "ไม่มีข้อมูล" กับ "ระบบค้าง"
    public string $notice = '';
    public string $noticeType = 'info';   // info | warn | success

    private function notify(string $message, string $type = 'info'): void
    {
        $this->notice = $message;
        $this->noticeType = $type;
    }

    public function mount(int $counterId): void
    {
        $counter = Counter::findOrFail($counterId);
        $this->counterId   = $counterId;
        $this->counterCode = $counter->counter_code;
        $this->counterName = $counter->counter_name;
        $this->loadRates();
    }

    public function loadRates(): void
    {
        $denoms = CurrencyDenomination::with('currency')
            ->where('is_active', true)
            ->orderBy('seq')
            ->get();

        // ดึงราคาวันนี้
        $existingRates = CounterRate::where('counter_id', $this->counterId)
            ->whereDate('rate_date', today())
            ->whereNotNull('denomination_id')
            ->get()
            ->keyBy('denomination_id');

        // ถ้าวันนี้ยังไม่มีราคาเลย → ดึงราคาล่าสุดมาแสดงแทน
        $latestRates = collect();
        if ($existingRates->isEmpty()) {
            $latestRates = CounterRate::where('counter_id', $this->counterId)
                ->whereNotNull('denomination_id')
                ->orderByDesc('rate_date')
                ->get()
                ->unique('denomination_id')
                ->keyBy('denomination_id');
        }

        $this->rates = [];
        foreach ($denoms as $denom) {
            $existing = $existingRates->get($denom->id);
            $fallback = $latestRates->get($denom->id);
            $source = $existing ?? $fallback;

            $this->rates[$denom->id] = [
                'currency_code'  => $denom->currency_code,
                'display_name'   => $denom->display_name,
                'country'        => $denom->currency?->country ?? '',
                'flag'           => strtolower($denom->currency_code) . '.png',
                'buy'            => $source ? (float) $source->rate_buy  : 0,
                'sell'           => $source ? (float) $source->rate_sell : 0,
                'discount'       => $source ? (float) $source->sell_discount_rate : 0,
            ];
        }
    }

    public function copyRates(): void
    {
        $this->notice = '';

        if (! $this->copyFromCode) {
            $this->notify('กรุณาเลือกเคาน์เตอร์ต้นทางก่อน', 'warn');
            return;
        }

        $sourceCounter = Counter::where('counter_code', $this->copyFromCode)->first();
        if (! $sourceCounter) {
            $this->notify("ไม่พบเคาน์เตอร์รหัส {$this->copyFromCode}", 'warn');
            return;
        }

        // ดึงราคาวันนี้ของเคาน์เตอร์ต้นทาง
        $sourceRates = CounterRate::where('counter_id', $sourceCounter->id)
            ->whereDate('rate_date', today())
            ->whereNotNull('denomination_id')
            ->get()
            ->keyBy('denomination_id');

        // ถ้าวันนี้ยังไม่มี → ดึงราคาล่าสุด
        if ($sourceRates->isEmpty()) {
            $sourceRates = CounterRate::where('counter_id', $sourceCounter->id)
                ->whereNotNull('denomination_id')
                ->orderByDesc('rate_date')
                ->get()
                ->unique('denomination_id')
                ->keyBy('denomination_id');
        }

        if ($sourceRates->isEmpty()) {
            $this->notify("เคาน์เตอร์ {$sourceCounter->counter_name} ยังไม่เคยตั้งราคาไว้เลย ไม่มีอะไรให้คัดลอก", 'warn');
            return;
        }

        $filled = 0;
        foreach ($this->rates as $denomId => &$row) {
            if ($sourceRates->has($denomId)) {
                $row['buy']      = (float) $sourceRates[$denomId]->rate_buy;
                $row['sell']     = (float) $sourceRates[$denomId]->rate_sell;
                $row['discount'] = (float) $sourceRates[$denomId]->sell_discount_rate;
                $filled++;
            }
        }
        unset($row);

        $this->notify("คัดลอกราคาจาก {$sourceCounter->counter_name} มาแล้ว {$filled} รายการ — ยังไม่บันทึก กด \"บันทึก\" เพื่อยืนยัน", 'success');
    }

    public function autoSetRates(): void
    {
        $this->notice = '';

        // ดึงราคาล่าสุดของเคาน์เตอร์นี้ (วันก่อนหน้า)
        $prevRates = CounterRate::where('counter_id', $this->counterId)
            ->whereNotNull('denomination_id')
            ->where('rate_date', '<', today())
            ->orderByDesc('rate_date')
            ->get()
            ->unique('denomination_id')
            ->keyBy('denomination_id');

        if ($prevRates->isEmpty()) {
            $this->notify('ไม่พบราคาของวันก่อนหน้าสำหรับเคาน์เตอร์นี้ — ยังไม่เคยตั้งราคาไว้ ลองใช้ "คัดลอกราคา" จากเคาน์เตอร์อื่นแทน', 'warn');
            return;
        }

        $filled = 0;
        foreach ($this->rates as $denomId => &$row) {
            if ($prevRates->has($denomId)) {
                $row['buy']  = (float) $prevRates[$denomId]->rate_buy;
                $row['sell'] = (float) $prevRates[$denomId]->rate_sell;
                $filled++;
            }
        }
        unset($row);

        $from = Carbon::parse($prevRates->first()->rate_date)->format('d/m/Y');
        $this->notify("ดึงราคาของวันที่ {$from} มาแล้ว {$filled} รายการ — ยังไม่บันทึก กด \"บันทึก\" เพื่อยืนยัน", 'success');
    }

    public function save(): void
    {
        $today = Carbon::today();
        $userId = Auth::id();

        foreach ($this->rates as $denomId => $row) {
            // Read old rate for logging
            $old = CounterRate::where('counter_id', $this->counterId)
                ->where('denomination_id', $denomId)
                ->whereDate('rate_date', $today)
                ->first();

            CounterRate::updateOrCreate(
                [
                    'counter_id'      => $this->counterId,
                    'denomination_id' => $denomId,
                    'currency_code'   => $row['currency_code'],
                    'rate_date'       => $today,
                ],
                [
                    'rate_buy'           => $row['buy'],
                    'rate_sell'          => $row['sell'],
                    'sell_discount_rate' => $row['discount'],
                    'set_by'             => $userId,
                ]
            );

            RateChangeLog::logChange(
                counterId: $this->counterId,
                denomId: (int) $denomId,
                currencyCode: $row['currency_code'],
                oldBuy: $old ? (float) $old->rate_buy : null,
                oldSell: $old ? (float) $old->rate_sell : null,
                newBuy: (float) $row['buy'],
                newSell: (float) $row['sell'],
                source: 'manual',
            );
        }

        $this->saved = true;
        $this->dispatch('rates-saved');
    }

    #[Computed]
    public function allCounters()
    {
        return Counter::where('is_active', true)
            ->where('id', '!=', $this->counterId)
            ->get();
    }

    public function render()
    {
        return view('livewire.rate.rate-setup-board');
    }
};
?>

{{-- Template placeholder — rendered by livewire.rate.rate-setup-board --}}