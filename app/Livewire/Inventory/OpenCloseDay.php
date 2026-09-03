<?php

namespace App\Livewire\Inventory;

use App\Models\Counter;
use App\Models\CounterStock;
use App\Models\Inventory;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\WorkingDay;
use App\Services\InventoryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OpenCloseDay extends Component
{
    public string $counterId = '';
    public string $date = '';
    public string $note = '';
    public float $openingThbCash = 0;

    // Closing flow
    public bool $showClosingForm = false;
    public array $closingItems = [];
    public string $closingNotes = '';

    public function getCanSaveClosingProperty(): bool
    {
        return collect($this->closingItems)->contains(fn($item) => ($item['actual'] ?? 0) > 0);
    }

    public function mount(): void
    {
        $this->counterId = (string) (session('working_counter_id') ?? '');
        $this->date = now()->format('Y-m-d');
    }

    public function getCountersProperty()
    {
        $query = Counter::with('branch')->where('is_active', true)
            ->orderBy('branch_id')->orderBy('counter_name');

        if (!Auth::user()->isAdmin()) {
            $query->whereIn('branch_id', Auth::user()->getVisibleBranchIds());
        }

        return $query->get();
    }

    public function getWorkingDayProperty()
    {
        if (!$this->counterId || !$this->date) {
            return null;
        }

        return WorkingDay::where('counter_id', $this->counterId)
            ->where('work_date', $this->date)
            ->first();
    }

    public function getInventorySnapshotProperty()
    {
        if (!$this->counterId) {
            return collect();
        }

        return Inventory::where('counter_id', $this->counterId)
            ->where('date', $this->date)
            ->with('denomination')
            ->orderBy('currency_code')
            ->get();
    }

    public function getRecentDaysProperty()
    {
        if (!$this->counterId) {
            return collect();
        }

        return WorkingDay::where('counter_id', $this->counterId)
            ->with(['openedByUser', 'closedByUser'])
            ->orderByDesc('work_date')
            ->limit(15)
            ->get();
    }

    public function openDay(): void
    {
        if (!$this->counterId) {
            session()->flash('error', 'กรุณาเลือกเคาน์เตอร์');
            return;
        }

        if ($this->openingThbCash <= 0) {
            session()->flash('error', 'กรุณากรอกจำนวนเงิน THB ที่รับมา');
            return;
        }

        // Check if already opened
        $existing = WorkingDay::where('counter_id', $this->counterId)
            ->where('work_date', $this->date)
            ->first();

        if ($existing) {
            session()->flash('error', 'วันทำการนี้ถูกเปิดแล้ว');
            return;
        }

        // Create working day record
        $cashAmount = $this->openingThbCash;
        WorkingDay::create([
            'counter_id' => $this->counterId,
            'work_date' => $this->date,
            'opening_thb_cash' => $cashAmount,
            'status' => 'open',
            'opened_by' => Auth::id(),
            'opened_at' => now(),
            'note' => $this->note ?: null,
        ]);

        // Snapshot opening balances
        $service = app(InventoryService::class);
        $service->openDay((int) $this->counterId, $this->date);

        $this->note = '';
        $this->openingThbCash = 0;
        session()->flash('success', 'เปิดวันทำการสำเร็จ - เงินทุนหมุน: ' . number_format($cashAmount, 2) . ' บาท');
    }

    public function prepareClosing(): void
    {
        if (!$this->counterId) {
            session()->flash('error', 'กรุณาเลือกเคาน์เตอร์');
            return;
        }

        // Get current stock for all denominations
        $stocks = CounterStock::where('counter_id', $this->counterId)
            ->whereNotNull('denomination_id')
            ->where('quantity', '>', 0)
            ->with('denomination.currency')
            ->orderBy('currency_code')
            ->get();

        $this->closingItems = $stocks->map(function ($stock) {
            return [
                'denomination_id' => $stock->denomination_id,
                'currency_code' => $stock->currency_code,
                'denom_label' => $stock->denomination?->display_name ?? $stock->currency_code,
                'expected' => (float) $stock->quantity,
                'actual' => 0, // ให้ staff กรอกเอง
                'variance' => -(float) $stock->quantity, // negative variance initially
            ];
        })->toArray();

        $this->showClosingForm = true;
    }

    public function updateClosingItem(int $index, float $actual): void
    {
        if (isset($this->closingItems[$index])) {
            $this->closingItems[$index]['actual'] = $actual;
            $this->closingItems[$index]['variance'] = $actual - $this->closingItems[$index]['expected'];
        }
    }

    public function saveClosingAmounts(): void
    {
        if (!$this->counterId) {
            session()->flash('error', 'กรุณาเลือกเคาน์เตอร์');
            return;
        }

        // Validate at least one item has actual amount > 0
        $hasAnyActual = collect($this->closingItems)->contains(function ($item) {
            return ($item['actual'] ?? 0) > 0;
        });

        if (!$hasAnyActual) {
            session()->flash('error', 'กรุณากรอกยอดเงินจริงอย่างน้อย 1 รายการ');
            return;
        }

        $workingDay = WorkingDay::where('counter_id', $this->counterId)
            ->where('work_date', $this->date)
            ->where('status', 'open')
            ->first();

        if (!$workingDay) {
            session()->flash('error', 'ไม่พบวันทำการที่เปิดอยู่');
            return;
        }

        // Calculate closing balances
        $service = app(InventoryService::class);
        $service->closeDay((int) $this->counterId, $this->date);

        // Update working day status to closed + pending approval
        $workingDay->update([
            'status' => 'closed',
            'closed_by' => Auth::id(),
            'closed_at' => now(),
            'closing_items' => $this->closingItems,
            'closing_status' => 'pending',
        ]);

        $this->showClosingForm = false;
        session()->flash('success', 'บันทึกยอดปิดสำเร็จ - รออนุมัติจาก Admin');
    }

    public function approveClosing(int $workingDayId): void
    {
        if (!Auth::user()->isAdmin()) {
            session()->flash('error', 'เฉพาะ Admin เท่านั้นที่อนุมัติได้');
            return;
        }

        $workingDay = WorkingDay::find($workingDayId);
        if (!$workingDay) {
            session()->flash('error', 'ไม่พบรายการ');
            return;
        }

        // Transfer stock to HQ
        $this->transferStockToHQ($workingDay);

        // Approve
        $workingDay->update([
            'closing_status' => 'approved',
            'closing_approved_by' => Auth::id(),
            'closing_approved_at' => now(),
        ]);

        session()->flash('success', 'อนุมัติยอดปิดสำเร็จ - โอนสต็อกไปส่วนกลางแล้ว');
    }

    public function rejectClosing(int $workingDayId, string $reason): void
    {
        if (!Auth::user()->isAdmin()) {
            session()->flash('error', 'เฉพาะ Admin เท่านั้นที่ปฏิเสธได้');
            return;
        }

        $workingDay = WorkingDay::find($workingDayId);
        if (!$workingDay) {
            session()->flash('error', 'ไม่พบรายการ');
            return;
        }

        $workingDay->update([
            'closing_status' => 'rejected',
            'closing_notes' => $reason,
            'closing_approved_by' => Auth::id(),
            'closing_approved_at' => now(),
        ]);

        session()->flash('error', 'ปฏิเสธยอดปิด - ' . $reason);
    }

    protected function transferStockToHQ(WorkingDay $workingDay): void
    {
        // Find HQ counter (assume counter with code 'HQ' or branch HQ)
        $hqCounter = Counter::where('counter_code', 'like', '%HQ%')
            ->orWhereHas('branch', function ($q) {
                $q->where('branch_type', 'HQ');
            })
            ->first();

        if (!$hqCounter) {
            // If no HQ counter found, skip transfer
            return;
        }

        $inventoryService = app(InventoryService::class);
        $fromCounter = (int) $workingDay->counter_id;
        $toCounter = (int) $hqCounter->id;

        // Transfer each denomination based on actual amounts
        foreach ($workingDay->closing_items ?? [] as $item) {
            if ($item['actual'] <= 0) continue;

            $inventoryService->transferStock(
                $fromCounter,
                $toCounter,
                $item['denomination_id'],
                $item['actual'],
                Auth::id(),
                'ส่งมอบยอดปิดวัน ' . $workingDay->work_date->format('d/m/Y')
            );
        }
    }

    public function render()
    {
        return view('livewire.inventory.open-close-day');
    }
}
