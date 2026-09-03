<?php

namespace App\Livewire\Rate;

use App\Models\BankSale;
use App\Models\Counter;
use App\Models\CounterStock;
use App\Models\Currency;
use App\Models\CurrencyDenomination;
use App\Models\Customer;
use App\Services\BankSaleService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

class BankSaleManager extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    /**
     * 'sell' = ขายให้ธนาคาร, 'buy' = ซื้อจากธนาคาร
     * Locked เพราะ public property แก้ได้จาก browser — ถ้าปล่อยให้พลิกได้
     * รายการอีกฝั่งจะหลุดเข้ามาวิ่งผิด code path
     */
    #[Locked]
    public string $direction = BankSale::DIRECTION_SELL;

    // Form
    public bool $showForm = false;
    public string $bankName = '';
    public string $bankAccount = '';

    /**
     * คู่ค้าฝั่งซื้อ — ซื้อเข้าไม่ได้มาจากธนาคารเสมอไป จึงพิมพ์ชื่อเองพร้อม
     * autocomplete จากทะเบียนลูกค้า ชื่อยังลงคอลัมน์ bank_name เหมือนฝั่งขาย
     */
    public string $custName = '';
    public ?int $customerId = null;
    public string $custPassportNo = '';
    public string $custNationality = '';
    public string $custExpiry = '';
    public array $customerSuggestions = [];
    public bool $showSuggestions = false;
    public string $currencyCode = '';
    public string $denominationId = '';
    public string $totalAmount = '';
    public string $bankRate = '';
    public string $settlementMethod = 'bank_transfer';
    public string $notes = '';

    // Source counters: [counter_id => amount] — ฝั่งขายเท่านั้น
    public array $sourceAmounts = [];

    /**
     * แถวธนบัตรของใบซื้อ — ธนาคารส่งมาครั้งเดียวได้หลาย denom หลายสกุล
     * [{currency_code, denomination_id, denomination_label, amount, bank_rate, total_thb}]
     */
    public array $rows = [];

    // แถวที่กำลังกรอกอยู่ (ฝั่งซื้อ)
    public string $rowDenominationId = '';
    public string $rowAmount = '';
    public string $rowRate = '';

    // Filter
    public string $filterStatus = '';

    public function mount(string $direction = BankSale::DIRECTION_SELL)
    {
        abort_unless(auth()->user()->isTrader(), 403, 'Access denied. Trader role required.');

        $this->direction = $direction === BankSale::DIRECTION_BUY
            ? BankSale::DIRECTION_BUY
            : BankSale::DIRECTION_SELL;
    }

    public function isBuy(): bool
    {
        return $this->direction === BankSale::DIRECTION_BUY;
    }

    /** เคาน์เตอร์ทั้งหมดในสาขาที่ trader คนนี้รับผิดชอบ */
    private function managedCounterIds(): array
    {
        return Counter::whereIn('branch_id', Auth::user()->getManagedBranchIds())
            ->pluck('id')->all();
    }

    public function getCurrenciesProperty()
    {
        return Currency::where('is_active', true)->orderBy('seq')->get();
    }

    public function getDenominationsProperty()
    {
        if (! $this->currencyCode) return collect();
        return CurrencyDenomination::where('currency_code', $this->currencyCode)
            ->where('is_active', true)->orderBy('seq')->get();
    }

    /** แหล่งสต็อก (ฝั่งขาย) — เฉพาะเคาน์เตอร์ที่ trader ดูแลและมีของอยู่จริง */
    public function getCounterStocksProperty()
    {
        if (! $this->denominationId || $this->isBuy()) return collect();

        return CounterStock::with('counter.branch')
            ->where('denomination_id', $this->denominationId)
            ->whereIn('counter_id', $this->managedCounterIds())
            ->where('quantity', '>', 0)
            ->orderBy('counter_id')
            ->get()
            ->map(function ($s) {
                // counter_stock ไม่มีคอลัมน์/accessor available — คำนวณใส่ไว้ให้ view
                $s->available = (float) $s->quantity - (float) $s->hold_amount;
                return $s;
            });
    }

    /**
     * กองกลาง = เคาน์เตอร์แรกของสำนักงานใหญ่ที่ trader สังกัด
     *
     * ซื้อจากธนาคารเข้ากองกลางที่เดียว ไม่ต้องเลือกปลายทาง — จะกระจายออก
     * สาขาทีหลังผ่านเมนู ยืม/คืน/โอน
     */
    public function getCentralCounterProperty(): ?Counter
    {
        return Counter::with('branch')
            ->where('branch_id', Auth::user()->branch_id)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

/** ธนบัตรทั้งหมดที่เลือกได้ในฟอร์มซื้อ — group ตามสกุลเงินในหน้า blade */
    public function getDenominationOptionsProperty()
    {
        return CurrencyDenomination::with('currency')
            ->where('currency_denominations.is_active', true)
            ->join('currencies', 'currency_denominations.currency_code', '=', 'currencies.currency_code')
            ->orderBy('currencies.seq')
            ->orderBy('currency_denominations.seq')
            ->select('currency_denominations.*')
            ->get();
    }

    /** [denomination_id => เรทซื้อเฉลี่ยของทุกสาขาที่ trader ดูแล] */
    public function getAvgBuyRatesProperty(): array
    {
        return collect($this->stockInfo)
            ->pluck('buy_rate', 'denomination_id')
            ->all();
    }

    /** สต็อกกองกลางของธนบัตรที่อยู่ในแถว — ดึงทีเดียวกันคิวรีต่อแถว */
    public function getCentralStocksProperty()
    {
        $counter = $this->centralCounter;
        $denomIds = array_column($this->rows, 'denomination_id');

        if (! $counter || empty($denomIds)) return collect();

        return CounterStock::where('counter_id', $counter->id)
            ->whereIn('denomination_id', $denomIds)
            ->get()
            ->keyBy('denomination_id');
    }

    public function getRowsTotalThbProperty(): float
    {
        return round(array_sum(array_column($this->rows, 'total_thb')), 2);
    }

    /** เลือกธนบัตรแล้วเติมเรทเฉลี่ยให้เป็นค่าตั้งต้น — trader พิมพ์ทับด้วยเรทจริงได้ */
    public function updatedRowDenominationId(): void
    {
        $rate = $this->avgBuyRates[$this->rowDenominationId] ?? 0;
        $this->rowRate = $rate > 0 ? (string) round($rate, 4) : '';
    }

    public function addRow(): void
    {
        $amount = (float) $this->rowAmount;
        $rate = (float) $this->rowRate;

        if (! $this->rowDenominationId || $amount <= 0 || $rate <= 0) {
            $this->addError('rows', 'กรุณาเลือกธนบัตร ใส่จำนวน และเรทให้ครบ');
            return;
        }

        $denom = CurrencyDenomination::find($this->rowDenominationId);
        if (! $denom) {
            $this->addError('rows', 'ไม่พบธนบัตรที่เลือก');
            return;
        }

        // ธนบัตรเดิมซ้ำในใบเดียวกันทำให้ยอดรวมกับ avg cost คำนวณซ้อนกัน — รวมเข้าแถวเดิมแทน
        foreach ($this->rows as $i => $row) {
            if ((int) $row['denomination_id'] === (int) $denom->id && (float) $row['bank_rate'] === $rate) {
                $this->rows[$i]['amount'] += $amount;
                $this->rows[$i]['total_thb'] = round($this->rows[$i]['amount'] * $rate, 2);
                $this->resetRowInput();
                return;
            }
        }

        $this->rows[] = [
            'currency_code' => $denom->currency_code,
            'denomination_id' => (int) $denom->id,
            'denomination_label' => $denom->display_name ?? $denom->currency_code,
            'amount' => $amount,
            'bank_rate' => $rate,
            'total_thb' => round($amount * $rate, 2),
        ];

        $this->resetRowInput();
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    private function resetRowInput(): void
    {
        $this->resetErrorBag('rows');
        $this->rowDenominationId = '';
        $this->rowAmount = '';
        $this->rowRate = '';
    }

    /** ค้นจากเลขพาสปอร์ตหรือชื่อ — เหมือนหน้ารับซื้อของ staff */
    public function searchCustomers(string $term): void
    {
        if (mb_strlen($term) < 2) {
            $this->customerSuggestions = [];
            $this->showSuggestions = false;
            return;
        }

        $this->customerSuggestions = Customer::where(function ($q) use ($term) {
                $q->where('id_number', 'like', "%{$term}%")
                  ->orWhere('name_en', 'like', "%{$term}%")
                  ->orWhere('first_name', 'like', "%{$term}%")
                  ->orWhere('last_name', 'like', "%{$term}%")
                  ->orWhere('name_th', 'like', "%{$term}%");
            })
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'id_number' => $c->id_number ?? '',
                'name' => $c->name_en ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')),
                'nationality' => $c->nationality ?? '',
            ])
            ->toArray();

        $this->showSuggestions = count($this->customerSuggestions) > 0;
    }

    public function selectCustomer(int $id): void
    {
        $customer = Customer::find($id);
        if (! $customer) return;

        $this->customerId = $customer->id;
        $this->custName = $customer->name_en ?: trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $this->custPassportNo = $customer->id_number ?? '';
        $this->custNationality = $customer->nationality ?? '';
        $this->custExpiry = $customer->passport_expiry
            ? Carbon::parse($customer->passport_expiry)->format('Y-m-d')
            : '';
        $this->showSuggestions = false;
    }

    public function hideSuggestions(): void
    {
        $this->showSuggestions = false;
    }

    public function getSalesProperty()
    {
        $query = BankSale::with([
                'sources.counter.branch', 'createdByUser', 'approvedByUser', 'denomination',
                'items.denomination', 'destinationCounter.branch',
            ])
            ->direction($this->direction)
            ->orderByDesc('created_at');

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        return $query->paginate(15);
    }

    public function updatedFilterStatus(): void
    {
        $this->resetPage();
    }

    /**
     * เรทเฉลี่ย + สต็อกคงเหลือ รวมทุกสาขาที่ trader รับผิดชอบ
     *
     * cache สั้นๆ เพราะ panel นี้ re-render ทุกครั้งที่ field แบบ .live เปลี่ยน
     */
    public function getStockInfoProperty(): array
    {
        $counterIds = $this->managedCounterIds();
        if (empty($counterIds)) return [];

        // v2 = เพิ่ม denomination_id เข้าไปในรูปร่างข้อมูล ค่าที่ cache ไว้แบบเก่าใช้ไม่ได้
        $key = 'bank_stock_info_v2_' . md5(implode(',', $counterIds));

        return Cache::remember($key, 60, function () use ($counterIds) {
            $rates = DB::table('counter_rates')
                ->whereIn('counter_id', $counterIds)
                ->whereNotNull('denomination_id')
                ->selectRaw('denomination_id, AVG(rate_buy) as avg_buy, AVG(rate_sell) as avg_sell')
                ->groupBy('denomination_id')
                ->get()->keyBy('denomination_id');

            $stocks = DB::table('counter_stock')
                ->whereIn('counter_id', $counterIds)
                ->whereNotNull('denomination_id')
                ->selectRaw('denomination_id, SUM(quantity - hold_amount) as available')
                ->groupBy('denomination_id')
                ->get()->keyBy('denomination_id');

            $denomIds = $rates->keys()->merge($stocks->keys())->unique();
            if ($denomIds->isEmpty()) return [];

            return CurrencyDenomination::with('currency')
                ->whereIn('id', $denomIds)
                ->orderBy('currency_code')->orderBy('seq')
                ->get()
                ->map(function ($denom) use ($rates, $stocks) {
                    $rate = $rates->get($denom->id);
                    $stock = $stocks->get($denom->id);

                    return [
                        'denomination_id' => (int) $denom->id,
                        'currency_code' => $denom->currency_code,
                        'currency_name' => $denom->currency?->currency_name ?? $denom->currency_code,
                        'denomination_label' => $denom->display_name ?? $denom->currency_code,
                        'buy_rate' => $rate ? (float) $rate->avg_buy : 0.0,
                        'sell_rate' => $rate ? (float) $rate->avg_sell : 0.0,
                        'available' => $stock ? (float) $stock->available : 0.0,
                    ];
                })->toArray();
        });
    }

    /** ฝั่งขายเท่านั้น — ซื้อเข้าไม่มีกำไร/ขาดทุน เงินที่จ่ายทั้งก้อนคือต้นทุน */
    public function getProfitPreviewProperty(): array
    {
        $totalAmount = (float) $this->totalAmount;
        $bankRate = (float) $this->bankRate;
        if ($totalAmount <= 0 || $bankRate <= 0) return ['revenue' => 0, 'cost' => 0, 'pl' => 0, 'allocated' => 0];

        $revenue = round($totalAmount * $bankRate, 2);
        $totalAllocated = 0;

        // Calculate weighted cost from selected sources
        $totalCost = 0;
        foreach ($this->counterStocks as $stock) {
            $amt = (float) ($this->sourceAmounts[$stock->counter_id] ?? 0);
            if ($amt > 0) {
                $totalCost += $amt * (float) $stock->avg_cost;
                $totalAllocated += $amt;
            }
        }

        $cost = round($totalCost, 2);
        return ['revenue' => $revenue, 'cost' => $cost, 'pl' => round($revenue - $cost, 2), 'allocated' => $totalAllocated];
    }

    /** avg_cost ของปลายทางหลังรับของเข้า — ให้เห็นผลกระทบก่อนกดสร้าง */
    public function projectedAvgCost(float $currentQty, float $currentAvg, float $incoming, float $unitCost): float
    {
        $newQty = $currentQty + $incoming;
        if ($newQty <= 0) return 0.0;

        return ($currentQty * $currentAvg + $incoming * $unitCost) / $newQty;
    }

    public function updatedCurrencyCode(): void
    {
        $this->denominationId = '';
        $this->sourceAmounts = [];
    }

    public function updatedDenominationId(): void
    {
        $this->sourceAmounts = [];
    }

    public function save(): void
    {
        if ($this->isBuy()) {
            $this->savePurchase();
            return;
        }

        $this->validate([
            'bankName' => 'required|min:2',
            'currencyCode' => 'required',
            'denominationId' => 'required',
            'totalAmount' => 'required|numeric|min:1',
            'bankRate' => 'required|numeric|min:0.0001',
        ], [
            'bankName.required' => 'กรุณาระบุชื่อธนาคาร',
            'totalAmount.required' => 'กรุณาระบุจำนวนเงิน',
            'bankRate.required' => 'กรุณาระบุเรทขาย',
        ]);

        // Build sources
        $sources = [];
        $totalAllocated = 0;
        foreach ($this->sourceAmounts as $counterId => $amount) {
            $amt = (float) $amount;
            if ($amt > 0) {
                $sources[] = ['counter_id' => (int) $counterId, 'amount' => $amt];
                $totalAllocated += $amt;
            }
        }

        if (empty($sources)) {
            $this->addError('sourceAmounts', 'กรุณาระบุจำนวนจากเคาน์เตอร์อย่างน้อย 1 แห่ง');
            return;
        }

        if (abs($totalAllocated - (float) $this->totalAmount) > 0.01) {
            $this->addError('sourceAmounts', "จำนวนที่จัดสรร ({$totalAllocated}) ไม่ตรงกับจำนวนรวม ({$this->totalAmount})");
            return;
        }

        try {
            $sale = app(BankSaleService::class)->create([
                'direction' => $this->direction,
                'bank_name' => $this->bankName,
                'bank_account' => $this->bankAccount ?: null,
                'currency_code' => $this->currencyCode,
                'denomination_id' => $this->denominationId,
                'total_amount' => $this->totalAmount,
                'bank_rate' => $this->bankRate,
                'settlement_method' => $this->settlementMethod,
                'notes' => $this->notes ?: null,
            ], $sources, Auth::id());

            $this->resetForm();
            session()->flash('success', "สร้างรายการขายธนาคาร {$sale->sale_no} สำเร็จ — สต็อกถูก Reserve แล้ว");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /** ฝั่งซื้อ: จำนวนเงินอยู่ที่แถวธนบัตร ปลายทางคือกองกลางที่เดียว */
    private function savePurchase(): void
    {
        $this->validate([
            'custName' => 'required|min:2',
        ], [
            'custName.required' => 'กรุณาระบุชื่อลูกค้า/คู่ค้า',
            'custName.min' => 'กรุณาระบุชื่อลูกค้า/คู่ค้า',
        ]);

        if (empty($this->rows)) {
            $this->addError('rows', 'กรุณาเพิ่มรายการธนบัตรอย่างน้อย 1 แถว');
            return;
        }

        $central = $this->centralCounter;
        if (! $central) {
            $this->addError('rows', 'ไม่พบเคาน์เตอร์กองกลางของสำนักงานใหญ่ที่คุณสังกัด');
            return;
        }

        try {
            $purchase = app(BankSaleService::class)->create([
                'direction' => $this->direction,
                // ชื่อคู่ค้าใช้คอลัมน์เดียวกับฝั่งขาย
                'bank_name' => $this->custName,
                'bank_account' => $this->bankAccount ?: null,
                'customer_id' => $this->customerId,
                'customer_passport_no' => $this->custPassportNo ?: null,
                'customer_nationality' => $this->custNationality ?: null,
                'customer_passport_expiry' => $this->custExpiry ?: null,
                'destination_counter_id' => $central->id,
                'settlement_method' => $this->settlementMethod,
                'notes' => $this->notes ?: null,
            ], $this->rows, Auth::id());

            $this->resetForm();
            session()->flash('success', "สร้างรายการซื้อจากธนาคาร {$purchase->sale_no} สำเร็จ — รอยืนยันรับของ");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /**
     * id มาจาก browser — ต้องกรอง direction ด้วย ไม่งั้นรายการของอีกหน้าจอ
     * ถูกส่งเข้า code path ผิดฝั่งได้
     */
    private function findInDirection(int $saleId): BankSale
    {
        return BankSale::direction($this->direction)->findOrFail($saleId);
    }

    public function markTransit(int $saleId): void
    {
        try {
            $sale = $this->findInDirection($saleId);
            app(BankSaleService::class)->markInTransit($sale);
            session()->flash('success', "อัปเดตสถานะ {$sale->sale_no} → กำลังขนส่ง");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function markDelivered(int $saleId): void
    {
        try {
            $sale = $this->findInDirection($saleId);
            app(BankSaleService::class)->markDelivered($sale);
            session()->flash('success', "อัปเดตสถานะ {$sale->sale_no} → ส่งถึงแล้ว");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function completeSale(int $saleId): void
    {
        try {
            $sale = $this->findInDirection($saleId);
            app(BankSaleService::class)->complete($sale, Auth::id());
            $msg = $sale->isBuy()
                ? "ยืนยันรับของ {$sale->sale_no} — สต็อกเข้าเคาน์เตอร์ + GL Journal เรียบร้อย"
                : "ยืนยันขายสำเร็จ {$sale->sale_no} — ตัดสต็อก + GL Journal เรียบร้อย";
            session()->flash('success', $msg);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function cancelSale(int $saleId): void
    {
        try {
            $sale = $this->findInDirection($saleId);
            app(BankSaleService::class)->cancel($sale, Auth::id());
            $msg = $sale->isBuy()
                ? "ยกเลิก {$sale->sale_no} เรียบร้อย"
                : "ยกเลิก {$sale->sale_no} — สต็อก Release กลับเรียบร้อย";
            session()->flash('success', $msg);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    /** แก้ "วิธีรับเงิน" ทีหลังได้ สำหรับรายการที่สร้างไว้แบบ "ระบุภายหลัง" */
    public function updateSettlementMethod(int $saleId, string $method): void
    {
        $allowed = ['bank_transfer', 'cash', 'cheque', BankSale::SETTLEMENT_PENDING];

        if (! in_array($method, $allowed, true)) {
            session()->flash('error', 'วิธีรับเงินไม่ถูกต้อง');
            return;
        }

        try {
            $sale = $this->findInDirection($saleId);

            if (in_array($sale->status, [BankSale::STATUS_COMPLETED, BankSale::STATUS_CANCELLED], true)) {
                session()->flash('error', 'รายการที่ปิดแล้วแก้วิธีรับเงินไม่ได้');
                return;
            }

            $sale->update(['settlement_method' => $method]);
            session()->flash('success', "อัปเดตวิธีรับเงินของ {$sale->sale_no} แล้ว");
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->bankName = '';
        $this->bankAccount = '';
        $this->currencyCode = '';
        $this->denominationId = '';
        $this->totalAmount = '';
        $this->bankRate = '';
        $this->settlementMethod = 'bank_transfer';
        $this->notes = '';
        $this->sourceAmounts = [];
        $this->rows = [];
        $this->custName = '';
        $this->customerId = null;
        $this->custPassportNo = '';
        $this->custNationality = '';
        $this->custExpiry = '';
        $this->customerSuggestions = [];
        $this->showSuggestions = false;
        $this->resetRowInput();
    }

    public function render()
    {
        return view('livewire.rate.bank-sale-manager');
    }
}
