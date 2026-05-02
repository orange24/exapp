<?php

namespace App\Livewire\Transaction;

use App\Models\TransactionMaster;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class MyTransactions extends Component
{
    use WithPagination;

    public string $searchTrnsNo = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public ?int $cancelId = null;
    public string $cancelReason = '';

    public function mount(): void
    {
        $this->dateFrom = now()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function search(): void
    {
        $this->resetPage();
    }

    public function clear(): void
    {
        $this->searchTrnsNo = '';
        $this->dateFrom = now()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
        $this->resetPage();
    }

    public function requestCancel(int $id): void
    {
        $this->cancelId = $id;
        $this->cancelReason = '';
    }

    public function submitCancel(): void
    {
        $this->validate([
            'cancelReason' => 'required|string|max:500',
        ], [
            'cancelReason.required' => 'กรุณาระบุเหตุผลในการยกเลิก',
            'cancelReason.max' => 'เหตุผลต้องไม่เกิน 500 ตัวอักษร',
        ]);

        $transaction = TransactionMaster::findOrFail($this->cancelId);

        if ($transaction->flag_cancel !== 'N') {
            session()->flash('error', 'ไม่สามารถขอยกเลิกรายการนี้ได้');
            $this->dismissCancel();
            return;
        }

        $transaction->update([
            'flag_cancel' => 'R',
            'cancel_reason' => $this->cancelReason,
            'updated_by' => Auth::id(),
        ]);

        session()->flash('success', 'ส่งคำขอยกเลิกเรียบร้อยแล้ว');
        $this->dismissCancel();
    }

    public function dismissCancel(): void
    {
        $this->cancelId = null;
        $this->cancelReason = '';
    }

    public function render()
    {
        $query = TransactionMaster::query()
            ->where('created_by', Auth::id())
            ->with(['details'])
            ->orderBy('trns_datetime', 'desc');

        if ($this->dateFrom) {
            $query->whereDate('trns_datetime', '>=', $this->dateFrom);
        }
        if ($this->dateTo) {
            $query->whereDate('trns_datetime', '<=', $this->dateTo);
        }
        if ($this->searchTrnsNo) {
            $query->where('trns_no', 'like', '%' . $this->searchTrnsNo . '%');
        }

        return view('livewire.transaction.my-transactions', [
            'transactions' => $query->paginate(20),
        ]);
    }
}
