<?php

namespace App\Livewire\Trader;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterStock;
use App\Models\Currency;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class InventoryDashboard extends Component
{
    public $managedBranches;
    public $inventoryData = [];
    public $combinedData = [];

    public function mount()
    {
        $this->managedBranches = Branch::whereIn('id', auth()->user()->getManagedBranchIds())
            ->with('counters')
            ->orderBy('branch_code')
            ->get();

        $this->loadInventory();
    }

    public function loadInventory()
    {
        // Load per branch
        foreach ($this->managedBranches as $branch) {
            $this->inventoryData[$branch->id] = $this->getBranchInventory($branch);
        }

        // Calculate combined
        $this->combinedData = $this->getCombinedInventory();
    }

    private function getBranchInventory($branch)
    {
        $counterIds = $branch->counters->pluck('id');

        // Get all currencies
        $currencies = Currency::orderBy('seq')->get();

        $result = [];

        foreach ($currencies as $currency) {
            // Sum stock across all counters in this branch
            $stocks = CounterStock::whereIn('counter_id', $counterIds)
                ->where('currency_code', $currency->currency_code)
                ->get();

            if ($stocks->isEmpty()) {
                continue; // Skip currencies with no stock
            }

            $totalQty = $stocks->sum('quantity');
            $totalCost = $stocks->sum('total_cost_value');
            $avgCost = $totalQty > 0 ? $totalCost / $totalQty : 0;

            $result[] = [
                'currency_code' => $currency->currency_code,
                'currency_name' => $currency->currency_name,
                'avg_cost' => $avgCost,
                'balance' => $totalQty,
            ];
        }

        return $result;
    }

    private function getCombinedInventory()
    {
        $counterIds = Counter::whereIn('branch_id', auth()->user()->getManagedBranchIds())
            ->pluck('id');

        // Get all currencies
        $currencies = Currency::orderBy('seq')->get();

        $result = [];

        foreach ($currencies as $currency) {
            // Sum stock across all managed counters
            $stocks = CounterStock::whereIn('counter_id', $counterIds)
                ->where('currency_code', $currency->currency_code)
                ->get();

            if ($stocks->isEmpty()) {
                continue; // Skip currencies with no stock
            }

            $totalQty = $stocks->sum('quantity');
            $totalCost = $stocks->sum('total_cost_value');
            $avgCost = $totalQty > 0 ? $totalCost / $totalQty : 0;

            $result[] = [
                'currency_code' => $currency->currency_code,
                'currency_name' => $currency->currency_name,
                'avg_cost' => $avgCost,
                'balance' => $totalQty,
            ];
        }

        return $result;
    }

    public function render()
    {
        return view('livewire.trader.inventory-dashboard');
    }
}
