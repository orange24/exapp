<?php

namespace App\Livewire\Rate;

use App\Models\BankSale;
use App\Models\Counter;
use App\Models\CounterStock;
use App\Models\Currency;
use App\Models\CurrencyDenomination;
use App\Services\BankSaleService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class BankSaleManager extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    // Form
    public bool $showForm = false;
    public string $bankName = '';
    public string $bankAccount = '';
    public string $currencyCode = '';
    public string $denominationId = '';
    public string $totalAmount = '';
    public string $bankRate = '';
    public string $settlementMethod = 'bank_transfer';
    public string $notes = '';

    // Source counters: [counter_id => amount]
    public array $sourceAmounts = [];

    // Filter
    public string $filterStatus = '';

    public function getCurrenciesProperty()
    {
        return Currency::where('is_active', true)->orderBy('seq')->get();
    }

    public function getDenominationsProperty()
    {
        if (! $this->currencyCode) return collect();
        return CurrencyDenomination::where('currency_code', $this->currencyCode)
            ->where('is_active', true)->orderBy('seq')->get();
    }

    public function getCounterStocksProperty()
    {
        if (! $this->denominationId) return collect();
        return CounterStock::with('counter.branch')
            ->where('denomination_id', $this->denominationId)
            ->where('quantity', '>', 0)
            ->orderBy('counter_id')
            ->get()
            ->map(function ($s) {
                $s->available = (float) $s->quantity - (float) $s->hold_amount;
                return $s;
            });
    }

    public function getSalesProperty()
    {
        $query = BankSale::with(['sources.counter.branch', 'createdByUser', 'approvedByUser', 'denomination'])
            ->orderByDesc('created_at');

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return $query->paginate(15);
    }

    public function getProfitPreviewProperty(): array
    {
        $totalAmount = (float) $this->totalAmount;
        $bankRate = (float) $this->bankRate;
        if ($totalAmount <= 0 || $bankRate <= 0) return ['revenue' => 0, 'cost' => 0, 'pl' => 0];

        $revenue = round($totalAmount * $bankRate, 2);

        // Calculate weighted cost from selected sources
        $totalCost = 0;
        $totalAllocated = 0;
        foreach ($this->counterStocks as $stock) {
            $amt = (float) ($this->sourceAmounts[$stock->counter_id] ?? 0);
            if ($amt > 0) {
                $totalCost += $amt * (float) $stock->avg_cost;
                $totalAllocated += $amt;
            }
        }

        $cost = round($totalCost, 2);
        return ['revenue' => $revenue, 'cost' => $cost, 'pl' => round($revenue - $cost, 2), 'allocated' => $totalAllocated];
    }

    public function updatedCurrencyCode(): void
    {
        $this->denominationId = '';
        $this->sourceAmounts = [];
    }

    public function updatedDenominationId(): void
    {
        $this->sourceAmounts = [];
    }

    public function save(): void
    {
        $this->validate([
            'bankName' => 'required|min:2',
            'currencyCode' => 'required',
            'denominationId' => 'required',
            'totalAmount' => 'required|numeric|min:1',
            'bankRate' => 'required|numeric|min:0.0001',
        ], [
            'bankName.required' => 'กรุณาระบุชื่อธนาคาร',
            'totalAmount.required' => 'กรุณาระบุจำนวนเงิน',
            'bankRate.required' => 'กรุณาระบุเรทขาย',
        ]);

        // Build sources
        $sources = [];
        $totalAllocated = 0;
        foreach ($this->sourceAmounts as $counterId => $amount) {
            $amt = (float) $amount;
            if ($amt > 0) {
                $sources[] = ['counter_id' => (int) $counterId, 'amount' => $amt];
                $totalAllocated += $amt;
            }
        }

        if (empty($sources)) {
            $this->addError('sourceAmounts', 'กรุณาระบุจำนวนจากเคาน์เตอร์อย่างน้อย 1 แห่ง');
            return;
        }

        if (abs($totalAllocated - (float) $this->totalAmount) > 0.01) {
            $this->addError('sourceAmounts', "จำนวนที่จัดสรร ({$totalAllocated}) ไม่ตรงกับจำนวนรวม ({$this->totalAmount})");
            return;
        }

        try {
            $service = app(BankSaleService::class);
            $sale = $service->create([
                'bank_name' => $this->bankName,
                'bank_account' => $this->bankAccount ?: null,
                'currency_code' => $this->currencyCode,
                'denomination_id' => $this->denominationId,
                'total_amount' => $this->totalAmount,
                'bank_rate' => $this->bankRate,
                'settlement_method' => $this->settlementMethod,
                'notes' => $this->notes ?: null,
            ], $sources, Auth::id());

            $this->resetForm();
            session()->flash('success', "สร้างรายการขายธนาคาร {$sale->sale_no} สำเร็จ — สต็อกถูก Reserve แล้ว");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markTransit(int $saleId): void
    {
        try {
            $sale = BankSale::findOrFail($saleId);
            app(BankSaleService::class)->markInTransit($sale);
            session()->flash('success', "อัปเดตสถานะ {$sale->sale_no} → กำลังขนส่ง");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markDelivered(int $saleId): void
    {
        try {
            $sale = BankSale::findOrFail($saleId);
            app(BankSaleService::class)->markDelivered($sale);
            session()->flash('success', "อัปเดตสถานะ {$sale->sale_no} → ส่งถึงแล้ว");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function completeSale(int $saleId): void
    {
        try {
            $sale = BankSale::findOrFail($saleId);
            app(BankSaleService::class)->complete($sale, Auth::id());
            session()->flash('success', "ยืนยันขายสำเร็จ {$sale->sale_no} — ตัดสต็อก + GL Journal เรียบร้อย");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelSale(int $saleId): void
    {
        try {
            $sale = BankSale::findOrFail($saleId);
            app(BankSaleService::class)->cancel($sale, Auth::id());
            session()->flash('success', "ยกเลิก {$sale->sale_no} — สต็อก Release กลับเรียบร้อย");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->bankName = '';
        $this->bankAccount = '';
        $this->currencyCode = '';
        $this->denominationId = '';
        $this->totalAmount = '';
        $this->bankRate = '';
        $this->settlementMethod = 'bank_transfer';
        $this->notes = '';
        $this->sourceAmounts = [];
    }

    public function render()
    {
        return view('livewire.rate.bank-sale-manager');
    }
}
