<?php

namespace App\Livewire\Inventory;

use App\Models\Counter;
use App\Models\CounterStock;
use App\Models\Currency;
use App\Models\CurrencyDenomination;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class StockAdjustment extends Component
{
    use WithPagination;

    // Form
    public bool $showForm = false;
    public string $counterId = '';
    public string $currencyCode = '';
    public string $denominationId = '';
    public string $adjustType = 'add'; // add or subtract
    public string $amount = '';
    public string $note = '';

    // Filters
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    public float $currentStock = 0;

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->counterId = (string) (session('working_counter_id') ?? '');
        $this->filterDateFrom = now()->format('Y-m-d');
        $this->filterDateTo = now()->format('Y-m-d');
    }

    public function getCountersProperty()
    {
        $query = Counter::with('branch')->where('is_active', true)
            ->orderBy('branch_id')->orderBy('counter_name');
        if (!Auth::user()->isAdmin()) {
            $query->where('branch_id', Auth::user()->branch_id);
        }
        return $query->get();
    }

    public function getCurrenciesProperty()
    {
        return Currency::where('is_active', true)->orderBy('seq')->get();
    }

    public function getDenominationsProperty()
    {
        if (!$this->currencyCode) return collect();
        return CurrencyDenomination::where('currency_code', $this->currencyCode)->orderBy('seq')->get();
    }

    public function updatedCurrencyCode(): void
    {
        $this->denominationId = '';
        $this->currentStock = 0;
    }

    public function updatedDenominationId(): void
    {
        $this->updateCurrentStock();
    }

    public function updatedCounterId(): void
    {
        $this->updateCurrentStock();
    }

    private function updateCurrentStock(): void
    {
        if ($this->counterId && $this->denominationId) {
            $stock = CounterStock::where('counter_id', $this->counterId)
                ->where('denomination_id', $this->denominationId)->first();
            $this->currentStock = $stock ? (float) $stock->quantity : 0;
        } else {
            $this->currentStock = 0;
        }
    }

    public function save(): void
    {
        $this->validate([
            'counterId' => 'required|exists:counters,id',
            'currencyCode' => 'required',
            'denominationId' => 'required|exists:currency_denominations,id',
            'adjustType' => 'required|in:add,subtract',
            'amount' => 'required|numeric|min:0.01',
            'note' => 'required|min:3',
        ], [
            'counterId.required' => 'กรุณาเลือกเคาน์เตอร์',
            'currencyCode.required' => 'กรุณาเลือกสกุลเงิน',
            'denominationId.required' => 'กรุณาเลือก denomination',
            'amount.required' => 'กรุณาระบุจำนวน',
            'note.required' => 'กรุณาระบุเหตุผลการปรับปรุง',
            'note.min' => 'เหตุผลต้องมีอย่างน้อย 3 ตัวอักษร',
        ]);

        $adjustAmount = (float) $this->amount;
        if ($this->adjustType === 'subtract') {
            // Check stock
            if ($adjustAmount > $this->currentStock) {
                $this->addError('amount', "สต็อกไม่เพียงพอ (คงเหลือ: {$this->currentStock})");
                return;
            }
            $adjustAmount = -$adjustAmount;
        }

        DB::transaction(function () use ($adjustAmount) {
            $stock = CounterStock::lockForUpdate()->firstOrCreate(
                [
                    'counter_id' => $this->counterId,
                    'currency_code' => $this->currencyCode,
                    'denomination_id' => $this->denominationId,
                ],
                ['quantity' => 0, 'hold_amount' => 0, 'avg_cost' => 0, 'total_cost_value' => 0]
            );
            if (!$stock->wasRecentlyCreated) {
                $stock = CounterStock::lockForUpdate()->find($stock->id);
            }

            $stock->quantity = (float) $stock->quantity + $adjustAmount;
            $stock->total_cost_value = $stock->quantity * (float) $stock->avg_cost;
            $stock->save();

            StockMovement::create([
                'counter_id' => $this->counterId,
                'currency_code' => $this->currencyCode,
                'denomination_id' => $this->denominationId,
                'movement_type' => 'adjustment',
                'amount' => $adjustAmount,
                'unit_price' => (float) $stock->avg_cost,
                'reference_type' => 'adjustment',
                'note' => ($this->adjustType === 'add' ? 'ปรับเพิ่ม: ' : 'ปรับลด: ') . $this->note,
                'moved_by' => Auth::id(),
                'moved_at' => now(),
            ]);
        });

        $this->resetForm();
        session()->flash('success', 'ปรับปรุงสต็อกสำเร็จ');
    }

    public function getAdjustmentsProperty()
    {
        $query = StockMovement::where('movement_type', 'adjustment')
            ->with(['counter.branch', 'denomination', 'movedBy'])
            ->orderByDesc('moved_at');

        if ($this->counterId) {
            $query->where('counter_id', $this->counterId);
        }
        if ($this->filterDateFrom) {
            $query->whereDate('moved_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('moved_at', '<=', $this->filterDateTo);
        }

        return $query->paginate(20);
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->currencyCode = '';
        $this->denominationId = '';
        $this->adjustType = 'add';
        $this->amount = '';
        $this->note = '';
        $this->currentStock = 0;
    }

    public function render()
    {
        return view('livewire.inventory.stock-adjustment');
    }
}
