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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

new class extends Component
{
    // Counter selection
    public string $counterId = '';
    public string $counterCode = '';

    // Customer
    public string $custName = '';
    public ?int $customerId = null;
    public bool $showPassportCapture = false;

    // Transaction rows
    public array $rows = [];  // [{currency_code, currency_name, amount, rate, total}]

    // Current row being added
    public string $selectedCurrency = '';
    public float $addAmount = 0;
    public float $currentRate = 0;
    public float $currentTotal = 0;

    // Result
    public ?int $savedTransactionId = null;
    public bool $showPrintSlip = false;
    public array $savedRows = [];  // keep rows for display after save

    // Passport OCR fields (filled after capture)
    public string $passportImageB64 = '';
    public string $ocrFirstName = '';
    public string $ocrLastName = '';
    public string $ocrNationality = '';
    public string $ocrDob = '';
    public string $ocrPassportNo = '';
    public string $ocrExpiry = '';

    public function mount(): void
    {
        // 1st priority: session working counter (user switched)
        $sessionCounterId = session('working_counter_id');
        if ($sessionCounterId) {
            $counter = Counter::where('id', $sessionCounterId)->where('is_active', true)->first();
            if ($counter) {
                $this->counterId   = (string) $counter->id;
                $this->counterCode = $counter->counter_code;
                return;
            }
        }

        $user = Auth::user();
        if ($user && $user->branch_id) {
            $counter = Counter::where('branch_id', $user->branch_id)
                ->where('is_active', true)->first();
            if ($counter) {
                $this->counterId   = (string) $counter->id;
                $this->counterCode = $counter->counter_code;
            }
        }
    }

    public function updatedSelectedCurrency(string $denomId): void
    {
        if (! $denomId || ! $this->counterId) {
            $this->currentRate = 0;
            $this->currentTotal = 0;
            return;
        }
        $rate = CounterRate::where('counter_id', $this->counterId)
            ->where('denomination_id', $denomId)
            ->whereDate('rate_date', today())
            ->first();
        $this->currentRate = $rate ? (float) $rate->rate_buy : 0;
        $this->recalcTotal();
    }

    public function updatedAddAmount(): void
    {
        $this->recalcTotal();
    }

    public function recalcTotal(): void
    {
        $this->currentTotal = round($this->addAmount * $this->currentRate, 2);
    }

    public function addRow(): void
    {
        if (! $this->selectedCurrency || $this->addAmount <= 0) return;

        $denom = \App\Models\CurrencyDenomination::with('currency')->find($this->selectedCurrency);

        $this->rows[] = [
            'currency_code'   => $denom?->currency_code ?? '',
            'currency_name'   => $denom?->display_name ?? '',
            'denomination_id' => (int) $this->selectedCurrency,
            'amount'          => $this->addAmount,
            'rate'            => $this->currentRate,
            'total'           => $this->currentTotal,
        ];

        // Reset input row
        $this->selectedCurrency = '';
        $this->addAmount        = 0;
        $this->currentRate      = 0;
        $this->currentTotal     = 0;
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
        $docNo = $this->generateDocNo('B');

        DB::transaction(function () use ($counter, $docNo) {
            $master = TransactionMaster::create([
                'trns_no'        => $docNo,
                'trns_type'      => 'BUYING',
                'counter_id'     => $this->counterId,
                'counter_name'   => $counter->counter_name,
                'customer_id'    => $this->customerId,
                'cust_name'      => $this->custName,
                'convert_currency_to' => 'THB',
                'trns_datetime'  => now(),
                'created_by'     => Auth::id(),
                'updated_by'     => Auth::id(),
            ]);

            $inventoryService = app(\App\Services\InventoryService::class);

            foreach ($this->rows as $row) {
                TransactionDetail::create([
                    'transaction_id'  => $master->id,
                    'currency_code'   => $row['currency_code'],
                    'denomination_id' => $row['denomination_id'] ?? null,
                    'currency_name'   => $row['currency_name'],
                    'unit_price'      => $row['rate'],
                    'amount'          => $row['amount'],
                    'total'           => $row['total'],
                    'created_by'      => Auth::id(),
                ]);

                // Update inventory: BUY = stock increases by foreign amount
                if (!empty($row['denomination_id'])) {
                    $inventoryService->recordBuy(
                        (int) $this->counterId,
                        $row['currency_code'],
                        $row['denomination_id'],
                        $row['amount'],
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
        // Store passport image (if captured)
        $imageData = [];
        if ($this->passportImageB64) {
            $decoded  = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $this->passportImageB64));
            $filename = 'passports/' . now()->format('Y/m') . '/' . uniqid('pp_') . '.jpg';
            \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $decoded);
            $imageData['passport_photo'] = $filename;
        }

        // Parse name: use custName if firstName/lastName empty
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
                'name_en'          => $this->custName ?: trim($firstName . ' ' . $lastName),
                'first_name'       => $firstName,
                'last_name'        => $lastName,
                'nationality'      => $this->ocrNationality,
                'date_of_birth'    => $this->ocrDob ?: null,
                'passport_expiry'  => $this->ocrExpiry ?: null,
                'kyc_status'       => 'approved',
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
                'dob'         => $c->date_of_birth ? Carbon::parse($c->date_of_birth)->format('Y-m-d') : '',
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
                'name_en'          => $this->custName ?: trim($firstName . ' ' . $lastName),
                'first_name'       => $firstName,
                'last_name'        => $lastName,
                'nationality'      => $this->ocrNationality,
                'date_of_birth'    => $this->ocrDob ?: null,
                'passport_expiry'  => $this->ocrExpiry ?: null,
                'kyc_status'       => 'approved',
            ], $imageData)
        );

        // Update transaction master
        $master = TransactionMaster::find($this->savedTransactionId);
        if ($master) {
            $master->update([
                'customer_id' => $customer->id,
                'cust_name'   => $this->custName,
            ]);
        }

        $this->customerId = $customer->id;
        $this->dispatch('customer-updated');
    }

    public function newTransaction(): void
    {
        $this->custName         = '';
        $this->customerId       = null;
        $this->rows             = [];
        $this->selectedCurrency = '';
        $this->addAmount        = 0;
        $this->currentRate      = 0;
        $this->currentTotal     = 0;
        $this->savedTransactionId = null;
        $this->showPrintSlip    = false;
        $this->savedRows        = [];
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
    }

    // Called from JS after OCR completes
    public function receiveOcrData(array $data): void
    {
        $this->ocrFirstName  = $data['firstName'] ?? '';
        $this->ocrLastName   = $data['lastName'] ?? '';
        $this->ocrNationality = $data['nationality'] ?? '';
        $this->ocrDob        = $data['dob'] ?? '';
        $this->ocrPassportNo = $data['passportNo'] ?? '';
        $this->ocrExpiry     = $data['expiry'] ?? '';
        $this->custName      = trim($this->ocrFirstName . ' ' . $this->ocrLastName);
    }

    public function receivePassportImage(string $imageB64): void
    {
        $this->passportImageB64 = $imageB64;
    }

    #[Computed]
    public function availableCounters()
    {
        return Counter::where('is_active', true)->with('branch')->get();
    }

    #[Computed]
    public function availableCurrencies()
    {
        if (! $this->counterId) return collect();
        return CounterRate::with(['currency', 'denomination'])
            ->where('counter_id', $this->counterId)
            ->whereDate('rate_date', today())
            ->whereNotNull('denomination_id')
            ->where('rate_buy', '>', 0)
            ->join('currency_denominations', 'counter_rates.denomination_id', '=', 'currency_denominations.id')
            ->orderBy('currency_denominations.seq')
            ->select('counter_rates.*')
            ->get();
    }

    #[Computed]
    public function stockInfo(): ?array
    {
        if (! $this->selectedCurrency || ! $this->counterId) return null;

        $stock = CounterStock::where('counter_id', $this->counterId)
            ->where('denomination_id', $this->selectedCurrency)->first();
        $rate = CounterRate::where('counter_id', $this->counterId)
            ->where('denomination_id', $this->selectedCurrency)
            ->whereDate('rate_date', today())->first();

        $avgCost  = (float) ($stock?->avg_cost ?? 0);
        $buyRate  = (float) ($rate?->rate_buy ?? 0);
        $sellRate = (float) ($rate?->rate_sell ?? 0);

        return [
            'quantity'    => (float) ($stock?->quantity ?? 0),
            'hold'        => (float) ($stock?->hold_amount ?? 0),
            'available'   => (float) ($stock?->available ?? 0),
            'avg_cost'    => $avgCost,
            'buy_rate'    => $buyRate,
            'sell_rate'   => $sellRate,
            'buy_margin'  => $buyRate > 0 ? round($buyRate - $avgCost, 4) : 0,
            'sell_margin' => $sellRate > 0 ? round($sellRate - $avgCost, 4) : 0,
            'spread'      => ($buyRate > 0 && $sellRate > 0) ? round($sellRate - $buyRate, 4) : 0,
            'denom_label' => $rate?->denomination?->display_name ?? '',
        ];
    }

    public function render()
    {
        return view('livewire.transaction.buy-form');
    }
};
?>

{{-- Template placeholder --}}