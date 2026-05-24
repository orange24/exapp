<?php

namespace App\Livewire\Transaction;

use App\Models\Counter;
use App\Models\TransactionMaster;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class AllTransactions extends Component
{
    use WithPagination;

    public string $searchTrnsNo = '';
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $counterId = '';
    public string $trnsType = '';      // BUYING, SELLING, or '' (all)
    public string $cancelStatus = '';  // N, R, Y, or '' (all)
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
        $this->counterId = '';
        $this->trnsType = '';
        $this->cancelStatus = '';
        $this->resetPage();
    }

    public function approveCancel(int $id): void
    {
        $transaction = TransactionMaster::findOrFail($id);

        if ($transaction->flag_cancel !== 'R') {
            session()->flash('error', 'ไม่มีคำขอยกเลิกสำหรับรายการนี้');
            return;
        }

        $transaction->update([
            'flag_cancel' => 'Y',
            'updated_by' => Auth::id(),
        ]);

        // Reverse inventory
        app(\App\Services\InventoryService::class)->reverseMovement($transaction->id, Auth::id());

        // Reverse GL journal if exists
        $originalJournal = \App\Models\JournalEntry::where('source_type', 'transaction')
            ->where('source_id', $transaction->id)->first();
        if ($originalJournal) {
            $originalJournal->load('lines');
            app(\App\Services\AutoJournalService::class)->createReversal($originalJournal);
        }

        session()->flash('success', 'อนุมัติยกเลิกเรียบร้อยแล้ว');
    }

    public function render()
    {
        $query = TransactionMaster::query()
            ->with(['details', 'createdBy'])
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
        if ($this->counterId) {
            $query->where('counter_id', $this->counterId);
        }
        if ($this->trnsType) {
            $query->where('trns_type', $this->trnsType);
        }
        if ($this->cancelStatus) {
            $query->where('flag_cancel', $this->cancelStatus);
        }

        $counters = Counter::where('is_active', true)->orderBy('counter_name')->get();

        return view('livewire.transaction.all-transactions', [
            'transactions' => $query->paginate(20),
            'counters' => $counters,
        ]);
    }
}
