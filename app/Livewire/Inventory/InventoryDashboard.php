<?php

namespace App\Livewire\Inventory;

use App\Models\Counter;
use App\Models\CounterStock;
use App\Models\Currency;
use App\Models\Setting;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class InventoryDashboard extends Component
{
    public string $counterId = '';
    public string $filterCurrency = '';
    public string $date = '';

    public function mount(): void
    {
        $this->date = now()->format('Y-m-d');
        $this->counterId = (string) (session('working_counter_id') ?? '');
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

    public function getInventoryDataProperty()
    {
        if (!$this->counterId) {
            return collect();
        }

        // Get current stock for this counter
        $query = CounterStock::where('counter_id', $this->counterId)
            ->whereNotNull('denomination_id')
            ->with(['currency', 'denomination']);

        if ($this->filterCurrency) {
            $query->where('currency_code', $this->filterCurrency);
        }

        $stocks = $query->get();

        // Get movements for the selected date
        $cutoff = Setting::get('WORKING_CUT_OFF', '03:00:00');
        $dateStart = "{$this->date} {$cutoff}";
        $dateEnd = Carbon::parse($this->date)->addDay()->format('Y-m-d') . " {$cutoff}";

        $movements = StockMovement::where('counter_id', $this->counterId)
            ->whereNotNull('denomination_id')
            ->where('moved_at', '>', $dateStart)
            ->where('moved_at', '<=', $dateEnd)
            ->selectRaw('
                denomination_id,
                currency_code,
                SUM(CASE WHEN movement_type = "buy" THEN amount ELSE 0 END) as bought,
                SUM(CASE WHEN movement_type = "sell" THEN ABS(amount) ELSE 0 END) as sold,
                SUM(CASE WHEN movement_type = "transfer_in" THEN amount ELSE 0 END) as tfr_in,
                SUM(CASE WHEN movement_type = "transfer_out" THEN ABS(amount) ELSE 0 END) as tfr_out,
                SUM(CASE WHEN movement_type = "adjustment" THEN amount ELSE 0 END) as adjust
            ')
            ->groupBy('denomination_id', 'currency_code')
            ->get()
            ->keyBy('denomination_id');

        if ($this->filterCurrency) {
            $movements = $movements->filter(fn($m) => $m->currency_code === $this->filterCurrency);
        }

        // Combine: for each stock row, calculate opening = current - today's net
        $data = $stocks->map(function ($stock) use ($movements) {
            $mv = $movements->get($stock->denomination_id);
            $bought = $mv ? (float) $mv->bought : 0;
            $sold = $mv ? (float) $mv->sold : 0;
            $tfrIn = $mv ? (float) $mv->tfr_in : 0;
            $tfrOut = $mv ? (float) $mv->tfr_out : 0;
            $adjust = $mv ? (float) $mv->adjust : 0;
            $current = (float) $stock->quantity;
            $opening = $current - $bought + $sold - $tfrIn + $tfrOut - $adjust;

            return [
                'currency_code'   => $stock->currency_code,
                'currency_name'   => $stock->currency?->currency_name ?? $stock->currency_code,
                'denom_label'     => $stock->denomination?->denom_label ?? '-',
                'denom_seq'       => $stock->denomination?->seq ?? 0,
                'currency_seq'    => $stock->currency?->seq ?? 999,
                'opening'         => $opening,
                'bought'          => $bought,
                'sold'            => $sold,
                'tfr_in'          => $tfrIn,
                'tfr_out'         => $tfrOut,
                'adjust'          => $adjust,
                'remaining'       => $current,
                'avg_cost'        => (float) $stock->avg_cost,
                'thb_value'       => $current * (float) $stock->avg_cost,
            ];
        });

        // Also include denominations that have movements today but no stock row
        foreach ($movements as $denomId => $mv) {
            if (!$stocks->contains('denomination_id', $denomId)) {
                $denom = \App\Models\CurrencyDenomination::with('currency')->find($denomId);
                if ($denom) {
                    $bought = (float) $mv->bought;
                    $sold = (float) $mv->sold;
                    $tfrIn = (float) $mv->tfr_in;
                    $tfrOut = (float) $mv->tfr_out;
                    $adjust = (float) $mv->adjust;
                    $data->push([
                        'currency_code' => $mv->currency_code,
                        'currency_name' => $denom->currency?->currency_name ?? $mv->currency_code,
                        'denom_label'   => $denom->denom_label ?? '-',
                        'denom_seq'     => $denom->seq ?? 0,
                        'currency_seq'  => $denom->currency?->seq ?? 999,
                        'opening'       => -$bought + $sold - $tfrIn + $tfrOut - $adjust,
                        'bought'        => $bought,
                        'sold'          => $sold,
                        'tfr_in'        => $tfrIn,
                        'tfr_out'       => $tfrOut,
                        'adjust'        => $adjust,
                        'remaining'     => 0,
                        'avg_cost'      => 0,
                        'thb_value'     => 0,
                    ]);
                }
            }
        }

        return $data->sortBy(['currency_seq', 'denom_seq'])->values();
    }

    public function getSummaryProperty()
    {
        $data = $this->inventoryData;
        return [
            'opening'   => $data->sum(fn($r) => $r['opening'] * $r['avg_cost']),
            'bought'    => $data->sum(fn($r) => $r['bought'] * $r['avg_cost']),
            'sold'      => $data->sum(fn($r) => $r['sold'] * $r['avg_cost']),
            'tfr_in'    => $data->sum(fn($r) => $r['tfr_in'] * $r['avg_cost']),
            'tfr_out'   => $data->sum(fn($r) => $r['tfr_out'] * $r['avg_cost']),
            'adjust'    => $data->sum(fn($r) => $r['adjust'] * $r['avg_cost']),
            'remaining' => $data->sum('thb_value'),
        ];
    }

    public function render()
    {
        return view('livewire.inventory.inventory-dashboard');
    }
}
