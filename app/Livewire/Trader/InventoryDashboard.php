<?php

namespace App\Livewire\Trader;

use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterStock;
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
        return $this->aggregateByDenomination($branch->counters->pluck('id'));
    }

    private function getCombinedInventory()
    {
        return $this->aggregateByDenomination(
            Counter::whereIn('branch_id', auth()->user()->getManagedBranchIds())->pluck('id')
        );
    }

    /**
     * รวมสต็อกแยกตาม denomination ไม่ใช่ต่อสกุลเงิน
     *
     * เดิมรวมทุก denomination ของสกุลเดียวกันเป็นแถวเดียวแล้วหาต้นทุนเฉลี่ยรวม
     * ซึ่งเป็นตัวเลขที่ใช้ตัดสินใจอะไรไม่ได้ เพราะแต่ละ denomination ซื้อขายกัน
     * คนละเรท — USD บน production กระจายอยู่ 4 ช่วง (100-50, 20-10, 5, 2-1)
     * ต้นทุนต่างกันตั้งแต่ 33.03 ถึง 33.29 เฉลี่ยรวมแล้วไม่ตรงกับช่วงไหนเลย
     *
     * ต้นทุนเฉลี่ยถ่วงน้ำหนักด้วยจำนวน ไม่ใช่เฉลี่ยของ avg_cost แต่ละเคาน์เตอร์
     * ตรงๆ — เคาน์เตอร์ที่ถือของมากต้องมีน้ำหนักมากกว่า
     *
     * @param  \Illuminate\Support\Collection<int, int>  $counterIds
     */
    private function aggregateByDenomination($counterIds): array
    {
        if ($counterIds->isEmpty()) {
            return [];
        }

        // foreignCurrency() ตัดแถวเงินบาท (denomination_id = null) ออก — เงินบาท
        // ไม่ใช่สินค้าคงคลังที่ trader ต้องบริหารเรท
        $stocks = CounterStock::query()
            ->foreignCurrency()
            ->whereIn('counter_id', $counterIds)
            ->with(['denomination', 'currency'])
            ->get()
            ->groupBy('denomination_id');

        $rows = [];

        foreach ($stocks as $group) {
            $totalQty = (float) $group->sum('quantity');
            $totalCost = (float) $group->sum('total_cost_value');

            // ไม่แสดงช่องที่ไม่มีของ — ลิสต์จะยาวโดยไม่ได้ข้อมูลอะไร
            if (abs($totalQty) < 0.005) {
                continue;
            }

            $first = $group->first();

            $rows[] = [
                'currency_code' => $first->currency_code,
                'currency_name' => $first->currency?->currency_name ?? $first->currency_code,
                'denom_label' => $first->denomination?->denom_label ?? '-',
                'display_name' => $first->denomination?->display_name
                    ?? ($first->currency_code . ' ' . ($first->denomination?->denom_label ?? '')),
                'avg_cost' => $totalQty > 0 ? $totalCost / $totalQty : 0,
                'balance' => $totalQty,
                'thb_value' => $totalCost,
                'currency_seq' => $first->currency?->seq ?? 999,
                'denom_seq' => $first->denomination?->seq ?? 0,
            ];
        }

        usort($rows, function ($a, $b) {
            return [$a['currency_seq'], $a['denom_seq'], $a['denom_label']]
                <=> [$b['currency_seq'], $b['denom_seq'], $b['denom_label']];
        });

        return $rows;
    }

    public function render()
    {
        return view('livewire.trader.inventory-dashboard');
    }
}
