<?php

namespace App\Livewire\Inventory;

use App\Models\Counter;
use App\Models\Currency;
use App\Models\CurrencyDenomination;
use App\Models\CounterStock;
use App\Models\StockTransfer;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class StockTransferForm extends Component
{
    use WithPagination;

    // Form fields
    public string $transferType = 'borrow'; // borrow, return, disbursement, intraday_return
    public string $fromCounterId = '';
    public string $toCounterId = '';
    public string $currencyCode = '';
    public string $denominationId = '';
    public string $amount = '';
    public string $note = '';

    // Filter for list
    public string $filterType = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    // UI state
    public bool $showForm = false;
    public float $availableStock = 0;

    protected $paginationTheme = 'tailwind';

    public function mount(string $type = 'borrow'): void
    {
        $this->transferType = $type;
        $this->filterDateFrom = now()->format('Y-m-d');
        $this->filterDateTo = now()->format('Y-m-d');
        $this->filterType = $type;

        // Default counters based on type
        $workingCounter = (string) (session('working_counter_id') ?? '');
        if (in_array($type, ['borrow'])) {
            $this->toCounterId = $workingCounter; // borrowing TO my counter
        } elseif (in_array($type, ['return', 'intraday_return'])) {
            $this->fromCounterId = $workingCounter; // returning FROM my counter
        } elseif ($type === 'disbursement') {
            $this->fromCounterId = $workingCounter; // disbursing FROM my counter (HQ)
        }
    }

    public function getCountersProperty()
    {
        return Counter::with('branch')->where('is_active', true)
            ->orderBy('branch_id')->orderBy('counter_name')
            ->get();
    }

    public function getCurrenciesProperty()
    {
        return Currency::where('is_active', true)->orderBy('seq')->get();
    }

    public function getDenominationsProperty()
    {
        if (!$this->currencyCode) {
            return collect();
        }
        return CurrencyDenomination::where('currency_code', $this->currencyCode)
            ->orderBy('seq')
            ->get();
    }

    public function updatedCurrencyCode(): void
    {
        $this->denominationId = '';
        $this->availableStock = 0;
    }

    public function updatedDenominationId(): void
    {
        $this->updateAvailableStock();
    }

    public function updatedFromCounterId(): void
    {
        $this->updateAvailableStock();
    }

    private function updateAvailableStock(): void
    {
        if ($this->fromCounterId && $this->denominationId) {
            $stock = CounterStock::where('counter_id', $this->fromCounterId)
                ->where('denomination_id', $this->denominationId)
                ->first();
            $this->availableStock = $stock ? (float) $stock->quantity : 0;
        } else {
            $this->availableStock = 0;
        }
    }

    public function save(): void
    {
        $this->validate([
            'fromCounterId' => 'required|exists:counters,id',
            'toCounterId' => 'required|exists:counters,id|different:fromCounterId',
            'currencyCode' => 'required',
            'denominationId' => 'required|exists:currency_denominations,id',
            'amount' => 'required|numeric|min:0.01',
        ], [
            'fromCounterId.required' => 'กรุณาเลือกเคาน์เตอร์ต้นทาง',
            'toCounterId.required' => 'กรุณาเลือกเคาน์เตอร์ปลายทาง',
            'toCounterId.different' => 'เคาน์เตอร์ต้นทางและปลายทางต้องไม่เหมือนกัน',
            'currencyCode.required' => 'กรุณาเลือกสกุลเงิน',
            'denominationId.required' => 'กรุณาเลือก denomination',
            'amount.required' => 'กรุณาระบุจำนวน',
            'amount.min' => 'จำนวนต้องมากกว่า 0',
        ]);

        // Check stock availability
        $stock = CounterStock::where('counter_id', $this->fromCounterId)
            ->where('denomination_id', $this->denominationId)
            ->first();
        $available = $stock ? (float) $stock->quantity : 0;

        if ((float) $this->amount > $available) {
            $this->addError('amount', "สต็อกไม่เพียงพอ (คงเหลือ: {$available})");
            return;
        }

        $service = app(InventoryService::class);
        $service->transferStock(
            transferType: $this->transferType,
            fromCounterId: (int) $this->fromCounterId,
            toCounterId: (int) $this->toCounterId,
            currencyCode: $this->currencyCode,
            denominationId: (int) $this->denominationId,
            amount: (float) $this->amount,
            userId: Auth::id(),
            note: $this->note ?: null,
        );

        // Reset form
        $this->currencyCode = '';
        $this->denominationId = '';
        $this->amount = '';
        $this->note = '';
        $this->availableStock = 0;
        $this->showForm = false;

        session()->flash('success', 'บันทึกรายการสำเร็จ');
    }

    public function cancelTransfer(int $transferId): void
    {
        $transfer = StockTransfer::find($transferId);
        if (! $transfer || $transfer->status !== 'completed') {
            session()->flash('error', 'ไม่สามารถยกเลิกรายการนี้ได้');
            return;
        }

        try {
            $service = app(InventoryService::class);
            $service->cancelTransfer($transferId, Auth::id());
            session()->flash('success', "ยกเลิกรายการ {$transfer->transfer_no} สำเร็จ — สต็อกคืนกลับเรียบร้อย");
        } catch (\Throwable $e) {
            session()->flash('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }

    public function getTransfersProperty()
    {
        $query = StockTransfer::with(['fromCounter.branch', 'toCounter.branch', 'denomination', 'currency', 'createdByUser'])
            ->orderByDesc('transferred_at');

        if ($this->filterType) {
            $query->where('transfer_type', $this->filterType);
        }

        if ($this->filterDateFrom) {
            $query->whereDate('transferred_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('transferred_at', '<=', $this->filterDateTo);
        }

        // Staff sees only their counter transfers
        if (!Auth::user()->isAdmin()) {
            $branchCounterIds = Counter::where('branch_id', Auth::user()->branch_id)->pluck('id');
            $query->where(function ($q) use ($branchCounterIds) {
                $q->whereIn('from_counter_id', $branchCounterIds)
                  ->orWhereIn('to_counter_id', $branchCounterIds);
            });
        }

        return $query->paginate(20);
    }

    public function render()
    {
        return view('livewire.inventory.stock-transfer-form');
    }
}
