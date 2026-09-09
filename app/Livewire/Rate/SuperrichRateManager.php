<?php

namespace App\Livewire\Rate;

use App\Models\Counter;
use App\Models\CurrencyDenomination;
use App\Models\SuperrichAdjustment;
use App\Models\SuperrichRate;
use App\Models\SuperrichRateBatch;
use App\Services\SuperrichRateService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class SuperrichRateManager extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    // Adjustment form
    public array $adjustments = []; // [denom_id => [adj_rate_buy, adj_rate_sell]]
    public bool $showAdjustments = false;

    // Target counters
    public array $selectedCounterIds = [];

    // UI state
    public bool $showPreview = false;

    public function mount(): void
    {
        $this->loadAdjustments();
        // Default: select all active counters
        $this->selectedCounterIds = Counter::where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
    }

    private function loadAdjustments(): void
    {
        $existing = SuperrichAdjustment::all()->keyBy('denomination_id');
        $denoms = CurrencyDenomination::where('is_active', true)
            ->orderBy('currency_code')->orderBy('seq')->get();

        $this->adjustments = [];
        foreach ($denoms as $d) {
            $adj = $existing->get($d->id);
            $this->adjustments[$d->id] = [
                'adj_rate_buy' => $adj ? (string) $adj->adj_rate_buy : '0',
                'adj_rate_sell' => $adj ? (string) $adj->adj_rate_sell : '0',
            ];
        }
    }

    public function pullRates(): void
    {
        try {
            $service = app(SuperrichRateService::class);
            $rates = $service->fetchRates(Auth::id());
            $mapped = $rates->whereNotNull('denomination_id')->count();
            $unmapped = $rates->whereNull('denomination_id')->count();
            session()->flash('success', "ดึงราคา SuperRich สำเร็จ — {$mapped} รายการจับคู่ได้, {$unmapped} รายการจับคู่ไม่ได้");
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('SuperRich rate fetch failed: ' . $e->getMessage(), ['exception' => $e]);
            session()->flash('error', 'ดึงราคาไม่สำเร็จ: ' . $e->getMessage());
        }
    }

    public function saveAdjustments(): void
    {
        foreach ($this->adjustments as $denomId => $adj) {
            SuperrichAdjustment::updateOrCreate(
                ['denomination_id' => $denomId],
                [
                    'adj_rate_buy' => (float) ($adj['adj_rate_buy'] ?? 0),
                    'adj_rate_sell' => (float) ($adj['adj_rate_sell'] ?? 0),
                    'updated_by' => Auth::id(),
                ]
            );
        }
        session()->flash('success', 'บันทึกค่าปรับสำเร็จ');
    }

    public function createBatch(): void
    {
        $counterIds = array_map('intval', $this->selectedCounterIds);
        if (empty($counterIds)) {
            session()->flash('error', 'กรุณาเลือกเคาน์เตอร์อย่างน้อย 1 รายการ');
            return;
        }

        try {
            $service = app(SuperrichRateService::class);
            $batch = $service->createBatch(Auth::id(), $counterIds);
            session()->flash('success', "สร้างชุดราคา #{$batch->id} สำเร็จ — รออนุมัติ");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function approveBatch(int $batchId): void
    {
        try {
            $batch = SuperrichRateBatch::findOrFail($batchId);
            $service = app(SuperrichRateService::class);
            $service->approveBatch($batch, Auth::id());
            session()->flash('success', "อนุมัติชุดราคา #{$batchId} สำเร็จ");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function rejectBatch(int $batchId): void
    {
        try {
            $batch = SuperrichRateBatch::findOrFail($batchId);
            $service = app(SuperrichRateService::class);
            $service->rejectBatch($batch, Auth::id());
            session()->flash('success', "ปฏิเสธชุดราคา #{$batchId}");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function distributeBatch(int $batchId): void
    {
        try {
            $batch = SuperrichRateBatch::findOrFail($batchId);
            $service = app(SuperrichRateService::class);
            $count = $service->distributeBatch($batch, Auth::id());
            session()->flash('success', "กระจายราคาสำเร็จ — อัปเดต {$count} รายการ");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function getLastFetchProperty(): ?string
    {
        $last = SuperrichRate::max('fetched_at');
        return $last ? \Carbon\Carbon::parse($last)->format('d/m/Y H:i:s') : null;
    }

    public function getLatestRatesProperty()
    {
        $service = app(SuperrichRateService::class);
        $rates = $service->getLatestRates();
        $adjustments = SuperrichAdjustment::all()->keyBy('denomination_id');

        return $rates->map(function ($sr) use ($adjustments) {
            $adj = $adjustments->get($sr->denomination_id);
            $adjBuy = $adj ? (float) $adj->adj_rate_buy : 0;
            $adjSell = $adj ? (float) $adj->adj_rate_sell : 0;
            $sr->adj_buy = $adjBuy;
            $sr->adj_sell = $adjSell;
            $sr->final_buy = round($sr->rate_buy + $adjBuy, 4);
            $sr->final_sell = round($sr->rate_sell + $adjSell, 4);
            return $sr;
        });
    }

    public function getDenominationsProperty()
    {
        return CurrencyDenomination::where('is_active', true)
            ->with('currency')
            ->orderBy('currency_code')->orderBy('seq')
            ->get();
    }

    public function getCountersProperty()
    {
        return Counter::where('is_active', true)->with('branch')
            ->orderBy('branch_id')->orderBy('counter_name')->get();
    }

    public function getBatchesProperty()
    {
        return SuperrichRateBatch::with(['createdByUser', 'reviewedByUser'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();
    }

    public function toggleSelectAllCounters(): void
    {
        $allIds = Counter::where('is_active', true)->pluck('id')->map(fn ($id) => (string) $id)->toArray();
        if (count($this->selectedCounterIds) === count($allIds)) {
            $this->selectedCounterIds = [];
        } else {
            $this->selectedCounterIds = $allIds;
        }
    }

    public function render()
    {
        return view('livewire.rate.superrich-rate-manager');
    }
}
