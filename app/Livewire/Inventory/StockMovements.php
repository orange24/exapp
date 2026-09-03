<?php

namespace App\Livewire\Inventory;

use App\Models\Counter;
use App\Models\Currency;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class StockMovements extends Component
{
    use WithPagination;

    public string $counterId = '';
    public string $filterCurrency = '';
    public string $filterType = '';
    public string $dateFrom = '';
    public string $dateTo = '';

    protected $queryString = ['counterId', 'filterCurrency', 'filterType', 'dateFrom', 'dateTo'];

    public function mount(): void
    {
        $this->counterId = (string) (session('working_counter_id') ?? '');
        $this->dateFrom = now()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function updatedCounterId(): void { $this->resetPage(); }
    public function updatedFilterCurrency(): void { $this->resetPage(); }
    public function updatedFilterType(): void { $this->resetPage(); }
    public function updatedDateFrom(): void { $this->resetPage(); }
    public function updatedDateTo(): void { $this->resetPage(); }

    public function getCountersProperty()
    {
        $query = Counter::with('branch')->where('is_active', true)
            ->orderBy('branch_id')->orderBy('counter_name');

        if (!Auth::user()->isAdmin()) {
            $query->whereIn('branch_id', Auth::user()->getVisibleBranchIds());
        }

        return $query->get();
    }

    public function getCurrenciesProperty()
    {
        return Currency::where('is_active', true)->orderBy('seq')->get();
    }

    public function render()
    {
        $query = StockMovement::with(['denomination', 'movedBy', 'counter'])
            ->whereNotNull('denomination_id')
            ->orderByDesc('moved_at');

        if ($this->counterId) {
            $query->where('counter_id', $this->counterId);
        } elseif (!Auth::user()->isAdmin()) {
            $counterIds = Counter::whereIn('branch_id', Auth::user()->getVisibleBranchIds())->pluck('id');
            $query->whereIn('counter_id', $counterIds);
        }

        if ($this->filterCurrency) {
            $query->where('currency_code', $this->filterCurrency);
        }

        if ($this->filterType) {
            $query->where('movement_type', $this->filterType);
        }

        if ($this->dateFrom) {
            $query->whereDate('moved_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo) {
            $query->whereDate('moved_at', '<=', $this->dateTo);
        }

        $movements = $query->paginate(50);

        return view('livewire.inventory.stock-movements', compact('movements'));
    }
}
