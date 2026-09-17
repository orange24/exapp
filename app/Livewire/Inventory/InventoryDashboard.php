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
        $visibleBranchIds = auth()->user()->getVisibleBranchIds();

        return Counter::with('branch')
            ->where('is_active', true)
            ->whereIn('branch_id', $visibleBranchIds)
            ->orderBy('branch_id')
            ->orderBy('counter_name')
            ->get();
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
                SUM(CASE WHEN movement_type = "adjustment" THEN amount ELSE 0 END) as adjust,

                SUM(CASE WHEN movement_type = "buy" THEN amount * unit_price ELSE 0 END) as bought_thb,
                SUM(CASE WHEN movement_type = "sell" THEN ABS(amount) * unit_price ELSE 0 END) as sold_thb,
                SUM(CASE WHEN movement_type = "transfer_in" THEN amount * unit_price ELSE 0 END) as tfr_in_thb,
                SUM(CASE WHEN movement_type = "transfer_out" THEN ABS(amount) * unit_price ELSE 0 END) as tfr_out_thb,
                SUM(CASE WHEN movement_type = "adjustment" THEN amount * unit_price ELSE 0 END) as adjust_thb
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

                // เงินบาทตามเรทที่ใช้จริงในแต่ละรายการ (stock_movements.unit_price)
                // ไม่ใช่ปริมาณ × ต้นทุนเฉลี่ย — ดู getSummaryProperty()
                'bought_thb'      => $mv ? (float) $mv->bought_thb : 0,
                'sold_thb'        => $mv ? (float) $mv->sold_thb : 0,
                'tfr_in_thb'      => $mv ? (float) $mv->tfr_in_thb : 0,
                'tfr_out_thb'     => $mv ? (float) $mv->tfr_out_thb : 0,
                'adjust_thb'      => $mv ? (float) $mv->adjust_thb : 0,
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
                        'bought_thb'    => (float) $mv->bought_thb,
                        'sold_thb'      => (float) $mv->sold_thb,
                        'tfr_in_thb'    => (float) $mv->tfr_in_thb,
                        'tfr_out_thb'   => (float) $mv->tfr_out_thb,
                        'adjust_thb'    => (float) $mv->adjust_thb,
                    ]);
                }
            }
        }

        return $data->sortBy(['currency_seq', 'denom_seq'])->values();
    }

    /**
     * ยอดเงินบาทในลิ้นชัก — แยกจาก inventoryData เพราะเงินบาทไม่มี denomination
     * ถ้ายัดลงตารางเดิม (ที่กรอง whereNotNull('denomination_id')) จะทำให้ยอดรวม
     * มูลค่าสต็อกและตาราง valuation เพี้ยนทั้งหน้า
     */
    public function getThbSummaryProperty(): array
    {
        if (!$this->counterId) {
            return ['opening' => 0.0, 'in' => 0.0, 'out' => 0.0, 'closing' => 0.0];
        }

        return app(\App\Services\ThbCashService::class)
            ->summaryFor((int) $this->counterId, $this->date);
    }

    /**
     * แถบสรุปด้านบน — หน่วยเป็นเงินบาท
     *
     * ตัวที่เป็น "การเคลื่อนไหว" (ซื้อเข้า/ขายออก/โอน/ปรับปรุง) คิดจาก **เรทที่ใช้จริง
     * ในแต่ละรายการ** คือเงินบาทที่จ่ายหรือรับจริง ตรงกับที่เห็นในบิลและใน
     * "รายการของฉัน" เป๊ะๆ เดิมคิดเป็น ปริมาณ × ต้นทุนเฉลี่ย ซึ่งทำให้ยอดขายออก
     * ไม่เท่ากับเงินที่ลูกค้าจ่าย (ต่างกันเท่ากำไรของบิลนั้น) แล้วสับสนกับการ์ด
     * เงินบาทในลิ้นชักที่อยู่หน้าเดียวกัน
     *
     * ตัวที่เป็น "ยอดคงเหลือ" (ยอดยกมา/คงเหลือ) ยังคิดด้วยต้นทุนเฉลี่ย เพราะเป็น
     * สต็อกที่ยังไม่ได้ขาย ไม่มีเรทของรายการให้อ้าง — มันคือมูลค่าสินค้าคงคลัง
     *
     * ผลที่ตามมาโดยเจตนา: แถบนี้ไม่บวกลบกันลงตัวแบบ
     * ยกมา + ซื้อเข้า − ขายออก = คงเหลือ อีกต่อไป เพราะเอากระแสเงินสดมาปนกับ
     * มูลค่าสินค้าคงคลังไม่ได้ ส่วนต่างที่เหลือคือกำไร/ขาดทุน ซึ่งไปดูที่
     * รายงานกำไรขาดทุน (ProfitLossReportController) และงบบัญชี ไม่ใช่ที่หน้านี้
     * ตารางแยกตามสกุลเงินข้างล่างยังบวกลบลงตัวตามปกติ เพราะเป็นจำนวนเงินตรา
     */
    public function getSummaryProperty()
    {
        $data = $this->inventoryData;
        return [
            'opening'   => $data->sum(fn($r) => $r['opening'] * $r['avg_cost']),
            'bought'    => $data->sum('bought_thb'),
            'sold'      => $data->sum('sold_thb'),
            'tfr_in'    => $data->sum('tfr_in_thb'),
            'tfr_out'   => $data->sum('tfr_out_thb'),
            'adjust'    => $data->sum('adjust_thb'),
            'remaining' => $data->sum('thb_value'),
        ];
    }

    public function render()
    {
        return view('livewire.inventory.inventory-dashboard');
    }
}
