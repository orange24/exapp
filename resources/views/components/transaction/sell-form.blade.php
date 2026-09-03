<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Counter;
use App\Models\CounterRate;
use App\Models\CounterStock;
use App\Models\Customer;
use App\Models\TransactionMaster;
use App\Models\TransactionDetail;
use App\Models\Setting;
use App\Models\WorkingDay;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component
{
    public string $counterId = '';
    public string $counterCode = '';
    public bool $showCounterModal = false;

    public string $custName = '';
    public ?int $customerId = null;

    // Booth-to-booth discount
    public bool $isDiscountBooth = false;
    public string $discountBoothCode = '';

    // Transaction rows
    public array $rows = [];

    // Current row — Sell: ลูกค้าให้ THB → แลกเป็นเงินต่างประเทศ
    public string $selectedCurrency = '';  // denomination_id
    public float $addAmount = 0;           // จำนวนเงิน THB ที่ลูกค้าให้
    public float $currentRate = 0;         // อัตราขาย
    public float $defaultRate = 0;         // rate ที่ตั้งค่าไว้ — role ที่แก้ไม่ได้จะถูกบังคับกลับมาค่านี้
    public float $currentTotal = 0;        // จำนวนเงินต่างประเทศที่ลูกค้าจะได้ = THB / rate
    public float $boothAmount = 0;         // จำนวนเงินต่างประเทศที่มีในบูธ
    public bool $showAdjustModal = false;  // แสดง modal ปรับจำนวน
    public float $adjustedTotal = 0;       // จำนวนเงินต่างประเทศหลัง adjust
    public float $adjustedThb = 0;         // จำนวน THB หลัง adjust

    // Result
    public ?int $savedTransactionId = null;
    public bool $showPrintSlip = false;
    public array $savedRows = [];

    // Passport capture (same as buy form)
    public string $passportImageB64 = '';
    public string $ocrPassportNo = '';
    public string $ocrFirstName = '';
    public string $ocrLastName = '';
    public string $ocrNationality = '';
    public string $ocrDob = '';
    public string $ocrExpiry = '';

    public function mount(): void
    {
        $user = Auth::user();

        // Check if role requires counter for Buy/Sell
        if ($user && !$user->requiresCounterForBuySell()) {
            // Trader/Auditor should not access Buy/Sell
            abort(403, 'Access denied. This role cannot access Buy/Sell.');
        }

        // 1st priority: session working counter (user switched)
        $sessionCounterId = session('working_counter_id');
        if ($sessionCounterId) {
            $counter = Counter::where('id', $sessionCounterId)->where('is_active', true)->first();
            if ($counter) {
                $this->counterId   = (string) $counter->id;
                $this->counterCode = $counter->counter_code;
                $this->checkWorkingDayOpen($counter);
                return;
            }
        }

        // No session counter - check if need to show modal
        if ($user && $user->branch_id) {
            $counters = Counter::where('branch_id', $user->branch_id)
                ->where('is_active', true)->get();

            if ($counters->count() === 1) {
                // Auto-select single counter
                $counter = $counters->first();
                $this->counterId   = (string) $counter->id;
                $this->counterCode = $counter->counter_code;
                session(['working_counter_id' => $counter->id, 'working_counter_name' => $counter->counter_name]);
                $this->checkWorkingDayOpen($counter);
            } elseif ($counters->count() > 1) {
                // Show modal to select
                $this->showCounterModal = true;
            }
        }
    }

    protected function checkWorkingDayOpen(Counter $counter): void
    {
        // Check if working day is open for this counter
        $today = now()->format('Y-m-d');
        $workingDay = WorkingDay::where('counter_id', $counter->id)
            ->where('work_date', $today)
            ->where('status', 'open')
            ->first();

        if (!$workingDay) {
            session()->flash('error', 'ยังไม่เปิดวันทำการ - กรุณาเปิดวันทำการก่อนทำรายการ');
            $this->redirect('/inventory/open-close-day', navigate: true);
        }
    }

    /**
     * Only Admin/Superadmin/Branch Manager may override the configured rate.
     * Everyone else transacts at the rate set in "ตั้งราคา".
     */
    #[Computed]
    public function canEditRate(): bool
    {
        $user = Auth::user();

        return $user && ($user->isAdmin() || $user->isBranchManager());
    }

    public function updatedCurrentRate(): void
    {
        // Public properties are writable from the browser, so re-assert the
        // permission server-side rather than relying on the disabled input.
        if (! $this->canEditRate) {
            $this->currentRate = $this->defaultRate;
        }

        $this->recalcTotal();
    }

    public function updatedSelectedCurrency(string $denomId): void
    {
        if (! $denomId || ! $this->counterId) {
            $this->currentRate = 0;
            $this->defaultRate = 0;
            $this->currentTotal = 0;
            $this->boothAmount = 0;
            return;
        }
        $cr = CounterRate::where('counter_id', $this->counterId)
            ->where('denomination_id', $denomId)
            ->whereDate('rate_date', today())
            ->first();

        // ถ้าไม่มี rate วันนี้ ให้ดึง rate ล่าสุดมาใช้
        if (!$cr) {
            $cr = CounterRate::where('counter_id', $this->counterId)
                ->where('denomination_id', $denomId)
                ->orderBy('rate_date', 'desc')
                ->first();
        }

        if ($this->isDiscountBooth && $cr) {
            $this->defaultRate = max(0, (float)$cr->rate_sell - (float)$cr->sell_discount_rate);
        } else {
            $this->defaultRate = $cr ? (float) $cr->rate_sell : 0;
        }
        $this->currentRate = $this->defaultRate;

        // ดึงจำนวนเงินในบูธจาก stock
        $stock = \App\Models\CounterStock::where('counter_id', $this->counterId)
            ->where('denomination_id', $denomId)
            ->first();
        // ใช้ available (quantity - hold_amount) ป้องกันขาย stock ที่ถูก reserve
        $this->boothAmount = $stock ? (float) $stock->quantity - (float) $stock->hold_amount : 0;

        $this->recalcTotal();
    }

    public function updatedAddAmount(): void { $this->recalcTotal(); }

    public function recalcTotal(): void
    {
        // Sell: ลูกค้าให้ THB → คำนวณเงินต่างประเทศที่ได้ = THB / rate
        if ($this->currentRate > 0) {
            $this->currentTotal = floor($this->addAmount / $this->currentRate);
        } else {
            $this->currentTotal = 0;
        }
    }

    public function addRow(): void
    {
        if (! $this->selectedCurrency || $this->addAmount <= 0) return;

        if (! $this->canEditRate) {
            $this->currentRate = $this->defaultRate;
            $this->recalcTotal();
        }

        if ($this->currentRate <= 0) return;

        $denom = \App\Models\CurrencyDenomination::with('currency')->find($this->selectedCurrency);
        $foreignAmount = $this->currentTotal; // จำนวนเงินต่างประเทศที่คำนวณได้

        // boothAmount มาจาก stock เสมอ (quantity - hold_amount) — ค่า 0 แปลว่า
        // "ไม่มีของในบูธ" ไม่ใช่ "ไม่ได้ตรวจ" ถ้าปล่อยผ่านตรงนี้ แถวจะถูกเพิ่มได้
        // แล้วไปพังเป็น 500 ตอน InventoryService::recordSell() แทน
        if ($this->boothAmount <= 0) {
            $this->addError('stock', 'สต็อกไม่พอ — ' . ($denom?->display_name ?? 'สกุลนี้') . ' คงเหลือ 0 ที่เคาน์เตอร์นี้ ขายไม่ได้');
            return;
        }

        // เงินในบูธไม่พอ → เปิด modal ให้ adjust
        if ($foreignAmount > $this->boothAmount) {
            $this->adjustedTotal = $this->boothAmount;
            $this->adjustedThb = ceil($this->boothAmount * $this->currentRate);
            $this->showAdjustModal = true;
            return;
        }

        $this->rows[] = [
            'currency_code'      => $denom?->currency_code ?? '',
            'currency_name'      => $denom?->display_name ?? '',
            'denomination_id'    => (int) $this->selectedCurrency,
            'amount'             => $this->addAmount,       // THB ที่ลูกค้ากรอกมาเต็มๆ
            'rate'               => $this->currentRate,
            'total'              => $foreignAmount,         // เงินต่างประเทศที่ให้ลูกค้า
            'discount_rate_sell' => 0,
        ];

        $this->resetInputRow();
    }

    public function confirmAdjust(): void
    {
        $denom = \App\Models\CurrencyDenomination::with('currency')->find($this->selectedCurrency);

        $this->rows[] = [
            'currency_code'      => $denom?->currency_code ?? '',
            'currency_name'      => $denom?->display_name ?? '',
            'denomination_id'    => (int) $this->selectedCurrency,
            'amount'             => $this->adjustedThb,
            'rate'               => $this->currentRate,
            'total'              => $this->adjustedTotal,
            'discount_rate_sell' => 0,
        ];

        $this->showAdjustModal = false;
        $this->resetInputRow();
    }

    public function cancelAdjust(): void
    {
        $this->showAdjustModal = false;
    }

    protected function resetInputRow(): void
    {
        $this->selectedCurrency = '';
        $this->addAmount = 0;
        $this->boothAmount = 0;
        $this->currentRate = 0;
        $this->defaultRate = 0;
        $this->currentTotal = 0;
        $this->adjustedTotal = 0;
        $this->adjustedThb = 0;
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    public function getGrandTotalProperty(): float
    {
        return collect($this->rows)->sum('total');
    }

    public function saveTransaction(): void
    {
        if (empty($this->rows) || ! $this->counterId) return;

        $counter = Counter::find($this->counterId);
        $docNo   = $this->generateDocNo('S');

        try {
            DB::transaction(function () use ($counter, $docNo) {
                $master = TransactionMaster::create([
                    'trns_no'              => $docNo,
                    'trns_type'            => 'SELLING',
                    'counter_id'           => $this->counterId,
                    'counter_name'         => $counter->counter_name,
                    'customer_id'          => $this->customerId,
                    'cust_name'            => $this->custName,
                    'convert_currency_to'  => 'THB',
                    'is_discount_booth'    => $this->isDiscountBooth,
                    'discount_booth_code'  => $this->discountBoothCode ?: null,
                    'trns_datetime'        => now(),
                    'created_by'           => Auth::id(),
                    'updated_by'           => Auth::id(),
                ]);

                $inventoryService = app(\App\Services\InventoryService::class);

                foreach ($this->rows as $row) {
                    TransactionDetail::create([
                        'transaction_id'     => $master->id,
                        'currency_code'      => $row['currency_code'],
                        'denomination_id'    => $row['denomination_id'] ?? null,
                        'currency_name'      => $row['currency_name'],
                        'unit_price'         => $row['rate'],
                        'amount'             => $row['amount'],
                        'total'              => $row['total'],
                        'discount_rate_sell' => $row['discount_rate_sell'] ?? 0,
                        'created_by'         => Auth::id(),
                    ]);

                    // Update inventory: SELL = stock decreases by foreign currency amount (total)
                    if (!empty($row['denomination_id'])) {
                        $inventoryService->recordSell(
                            (int) $this->counterId,
                            $row['currency_code'],
                            $row['denomination_id'],
                            $row['total'],       // foreign currency amount given to customer
                            $row['rate'],
                            $master->id,
                            Auth::id()
                        );
                    }
                }

                // Save customer data if we have passport no (from OCR or manual input)
                if ($this->ocrPassportNo) {
                    $this->savePassportCustomer($master);
                }

                // Auto GL Journal
                $master->load('details');
                app(\App\Services\AutoJournalService::class)->createFromTransaction($master);

                $this->savedTransactionId = $master->id;
            });
        } catch (\RuntimeException $e) {
            // สต็อกอาจถูกตัดโดยเครื่องอื่นระหว่างกด "เพิ่ม" กับ "บันทึก & พิมพ์"
            // ทั้ง transaction ถูก rollback แล้ว — แจ้งพนักงานแทนที่จะโยน 500
            $this->addError('stock', $e->getMessage());
            return;
        }

        $this->savedRows = $this->rows;
        $this->rows = [];
        $this->showPrintSlip = true;
        $this->dispatch('transaction-saved', id: $this->savedTransactionId);
    }

    protected function generateDocNo(string $prefix): string
    {
        $today = now()->format('Ymd');
        $last = TransactionMaster::where('trns_no', 'like', $prefix . $today . '%')
            ->orderByDesc('trns_no')->first();
        $seq = $last ? (int) substr($last->trns_no, -4) + 1 : 1;
        return $prefix . $today . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    protected function savePassportCustomer(TransactionMaster $master): void
    {
        $imageData = [];
        if ($this->passportImageB64) {
            $decoded  = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $this->passportImageB64));
            $filename = 'passports/' . now()->format('Y/m') . '/' . uniqid('pp_') . '.jpg';
            \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $decoded);
            $imageData['passport_photo'] = $filename;
        }

        $firstName = $this->ocrFirstName;
        $lastName  = $this->ocrLastName;
        if (! $firstName && ! $lastName && $this->custName) {
            $parts = preg_split('/\s+/', trim($this->custName), 2);
            $firstName = $parts[0] ?? '';
            $lastName  = $parts[1] ?? '';
        }

        $customer = Customer::updateOrCreate(
            ['id_type' => 'passport', 'id_number' => $this->ocrPassportNo],
            array_merge([
                'name_en'         => $this->custName ?: trim($firstName . ' ' . $lastName),
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'nationality'     => $this->ocrNationality,
                'date_of_birth'   => $this->ocrDob ?: null,
                'passport_expiry' => $this->ocrExpiry ?: null,
                'kyc_status'      => 'approved',
            ], $imageData)
        );
        $master->update(['customer_id' => $customer->id]);
        $this->customerId = $customer->id;
    }

    // Autocomplete search
    public string $passportSearch = '';
    public array $customerSuggestions = [];
    public bool $showSuggestions = false;

    public function searchCustomers(string $term): void
    {
        $this->passportSearch = $term;
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
                'id'          => $c->id,
                'id_number'   => $c->id_number ?? '',
                'name'        => $c->name_en ?: trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')),
                'nationality' => $c->nationality ?? '',
                'expiry'      => $c->passport_expiry ? Carbon::parse($c->passport_expiry)->format('Y-m-d') : '',
                'first_name'  => $c->first_name ?? '',
                'last_name'   => $c->last_name ?? '',
            ])
            ->toArray();

        $this->showSuggestions = count($this->customerSuggestions) > 0;
    }

    public function selectCustomer(int $id): void
    {
        $customer = Customer::find($id);
        if (! $customer) return;

        $this->customerId     = $customer->id;
        $this->ocrPassportNo  = $customer->id_number ?? '';
        $this->ocrFirstName   = $customer->first_name ?? '';
        $this->ocrLastName    = $customer->last_name ?? '';
        $this->ocrNationality = $customer->nationality ?? '';
        $this->ocrDob         = $customer->date_of_birth ? Carbon::parse($customer->date_of_birth)->format('Y-m-d') : '';
        $this->ocrExpiry      = $customer->passport_expiry ? Carbon::parse($customer->passport_expiry)->format('Y-m-d') : '';
        $this->custName       = $customer->name_en ?: trim($this->ocrFirstName . ' ' . $this->ocrLastName);
        $this->passportSearch = '';
        $this->showSuggestions = false;
    }

    public function hideSuggestions(): void
    {
        $this->showSuggestions = false;
    }

    public function updateCustomerInfo(): void
    {
        if (! $this->ocrPassportNo || ! $this->savedTransactionId) return;

        $firstName = $this->ocrFirstName;
        $lastName  = $this->ocrLastName;
        if (! $firstName && ! $lastName && $this->custName) {
            $parts = preg_split('/\s+/', trim($this->custName), 2);
            $firstName = $parts[0] ?? '';
            $lastName  = $parts[1] ?? '';
        }

        $imageData = [];
        if ($this->passportImageB64) {
            $decoded  = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $this->passportImageB64));
            $filename = 'passports/' . now()->format('Y/m') . '/' . uniqid('pp_') . '.jpg';
            \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $decoded);
            $imageData['passport_photo'] = $filename;
        }

        $customer = Customer::updateOrCreate(
            ['id_type' => 'passport', 'id_number' => $this->ocrPassportNo],
            array_merge([
                'name_en'         => $this->custName ?: trim($firstName . ' ' . $lastName),
                'first_name'      => $firstName,
                'last_name'       => $lastName,
                'nationality'     => $this->ocrNationality,
                'date_of_birth'   => $this->ocrDob ?: null,
                'passport_expiry' => $this->ocrExpiry ?: null,
                'kyc_status'      => 'approved',
            ], $imageData)
        );

        $master = TransactionMaster::find($this->savedTransactionId);
        if ($master) {
            $master->update(['customer_id' => $customer->id, 'cust_name' => $this->custName]);
        }
        $this->customerId = $customer->id;
        $this->dispatch('customer-updated');
    }

    public function newTransaction(): void
    {
        $this->custName         = '';
        $this->customerId       = null;
        $this->rows             = [];
        $this->savedRows        = [];
        $this->selectedCurrency = '';
        $this->addAmount        = 0;
        $this->currentRate      = 0;
        $this->defaultRate      = 0;
        $this->currentTotal     = 0;
        $this->boothAmount      = 0;
        $this->savedTransactionId = null;
        $this->showPrintSlip    = false;
        $this->passportImageB64 = '';
        $this->ocrFirstName     = '';
        $this->ocrLastName      = '';
        $this->ocrNationality   = '';
        $this->ocrDob           = '';
        $this->ocrPassportNo    = '';
        $this->ocrExpiry        = '';
        $this->passportSearch   = '';
        $this->customerSuggestions = [];
        $this->showSuggestions  = false;
        $this->isDiscountBooth  = false;
        $this->discountBoothCode = '';
    }

    public function receiveOcrData(array $data): void
    {
        $this->ocrFirstName   = $data['firstName'] ?? '';
        $this->ocrLastName    = $data['lastName'] ?? '';
        $this->ocrNationality = $data['nationality'] ?? '';
        $this->ocrDob         = $data['dob'] ?? '';
        $this->ocrPassportNo  = $data['passportNo'] ?? '';
        $this->ocrExpiry      = $data['expiry'] ?? '';
        $this->custName       = trim($this->ocrFirstName . ' ' . $this->ocrLastName);
    }

    public function receivePassportImage(string $imageB64): void
    {
        $this->passportImageB64 = $imageB64;
    }

    public function selectCounterFromModal(int $counterId): void
    {
        $counter = Counter::where('id', $counterId)->where('is_active', true)->first();
        if (!$counter) {
            session()->flash('error', 'Counter not found');
            return;
        }

        $this->counterId = (string) $counter->id;
        $this->counterCode = $counter->counter_code;
        session(['working_counter_id' => $counter->id, 'working_counter_name' => $counter->counter_name]);
        $this->showCounterModal = false;
        $this->checkWorkingDayOpen($counter);
    }

    #[Computed]
    public function availableCounters()
    {
        $user = Auth::user();
        $query = Counter::where('is_active', true);

        // Filter by working branch for Staff and Branch Manager
        if ($user->role?->name === 'staff') {
            $workingBranchId = session('working_branch_id', $user->branch_id);
            $query->where('branch_id', $workingBranchId);
        } elseif ($user->role?->name === 'branch_manager') {
            $query->where('branch_id', $user->branch_id);
        }
        // Admin can see all counters

        return $query->with('branch')->orderBy('branch_id')->get();
    }

    #[Computed]
    public function availableCurrencies()
    {
        if (! $this->counterId) return collect();

        // ดึง rate ล่าสุดของแต่ละ denomination
        $latestRates = CounterRate::where('counter_rates.counter_id', $this->counterId)
            ->whereNotNull('counter_rates.denomination_id')
            ->where('counter_rates.rate_sell', '>', 0)
            ->select('counter_rates.denomination_id', DB::raw('MAX(counter_rates.rate_date) as latest_date'))
            ->groupBy('counter_rates.denomination_id');

        return CounterRate::with(['currency', 'denomination'])
            ->where('counter_rates.counter_id', $this->counterId)
            ->whereNotNull('counter_rates.denomination_id')
            ->where('counter_rates.rate_sell', '>', 0)
            ->joinSub($latestRates, 'latest', function($join) {
                $join->on('counter_rates.denomination_id', '=', 'latest.denomination_id')
                     ->on('counter_rates.rate_date', '=', 'latest.latest_date');
            })
            ->join('currency_denominations', 'counter_rates.denomination_id', '=', 'currency_denominations.id')
            ->orderBy('currency_denominations.seq')
            ->select('counter_rates.*')
            ->get();
    }

    #[Computed]
    public function otherCounters()
    {
        return Counter::where('is_active', true)
            ->where('id', '!=', $this->counterId ?? 0)
            ->get();
    }

    #[Computed]
    public function stockInfo(): array
    {
        if (!$this->counterId) return [];

        $cutoff = Setting::get('WORKING_CUT_OFF', '03:00:00');
        $dateStart = date('Y-m-d') . " {$cutoff}";
        $dateEnd = date('Y-m-d', strtotime('+1 day')) . " {$cutoff}";

        // ดึง rate เฉลี่ยจาก transactions ต่อ denomination
        $avgRates = DB::table('transactions_detail')
            ->join('transactions_master', 'transactions_detail.transaction_id', '=', 'transactions_master.id')
            ->where('transactions_master.counter_id', $this->counterId)
            ->where('transactions_master.flag_cancel', 'N')
            ->where('transactions_master.trns_datetime', '>', $dateStart)
            ->where('transactions_master.trns_datetime', '<=', $dateEnd)
            ->selectRaw('
                transactions_detail.denomination_id,
                AVG(CASE WHEN transactions_master.trns_type = "BUYING" THEN transactions_detail.unit_price ELSE NULL END) as avg_buy,
                AVG(CASE WHEN transactions_master.trns_type = "SELLING" THEN transactions_detail.unit_price ELSE NULL END) as avg_sell
            ')
            ->groupBy('transactions_detail.denomination_id')
            ->get()
            ->keyBy('denomination_id');

        // ดึง rate ล่าสุดจาก counter_rates (order by seq) พร้อม denomination
        $result = CounterRate::with(['currency', 'denomination'])
            ->where('counter_rates.counter_id', $this->counterId)
            ->whereNotNull('counter_rates.denomination_id')
            ->join('currency_denominations', 'counter_rates.denomination_id', '=', 'currency_denominations.id')
            ->orderBy('currency_denominations.seq')
            ->select('counter_rates.*')
            ->get()
            ->map(function ($rate) use ($avgRates) {
                $avgRate = $avgRates->get($rate->denomination_id);

                // ใช้ rate เฉลี่ยถ้ามี ไม่งั้นใช้ rate ล่าสุด
                $buyRate = $avgRate && $avgRate->avg_buy ? (float) $avgRate->avg_buy : (float) $rate->rate_buy;
                $sellRate = $avgRate && $avgRate->avg_sell ? (float) $avgRate->avg_sell : (float) $rate->rate_sell;

                return [
                    'currency_code' => $rate->currency_code,
                    'currency_name' => $rate->currency?->currency_name ?? $rate->currency_code,
                    'denomination_label' => $rate->denomination?->display_name ?? $rate->currency_code,
                    'buy_rate' => $buyRate,
                    'sell_rate' => $sellRate,
                ];
            })
            ->toArray();

        return $result;
    }

    public function render()
    {
        return view('livewire.transaction.sell-form');
    }
};
?>

{{-- Template placeholder --}}