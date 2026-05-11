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
            $query->where('branch_id', Auth::user()->branch_id);
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

        // Check if already opened
        $existing = WorkingDay::where('counter_id', $this->counterId)
            ->where('work_date', $this->date)
            ->first();

        if ($existing) {
            session()->flash('error', 'วันทำการนี้ถูกเปิดแล้ว');
            return;
        }

        // Create working day record
        WorkingDay::create([
            'counter_id' => $this->counterId,
            'work_date' => $this->date,
            'status' => 'open',
            'opened_by' => Auth::id(),
            'opened_at' => now(),
            'note' => $this->note ?: null,
        ]);

        // Snapshot opening balances
        $service = app(InventoryService::class);
        $service->openDay((int) $this->counterId, $this->date);

        $this->note = '';
        session()->flash('success', 'เปิดวันทำการสำเร็จ');
    }

    public function closeDay(): void
    {
        if (!$this->counterId) {
            session()->flash('error', 'กรุณาเลือกเคาน์เตอร์');
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

        // Update working day status
        $workingDay->update([
            'status' => 'closed',
            'closed_by' => Auth::id(),
            'closed_at' => now(),
        ]);

        session()->flash('success', 'ปิดวันทำการสำเร็จ');
    }

    public function render()
    {
        return view('livewire.inventory.open-close-day');
    }
}
