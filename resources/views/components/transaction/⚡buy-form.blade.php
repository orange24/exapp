<?php

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\Counter;
use App\Models\CounterRate;
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
            'currency_code' => $denom?->currency_code ?? '',
            'currency_name' => $denom?->display_name ?? '',
            'amount'        => $this->addAmount,
            'rate'          => $this->currentRate,
            'total'         => $this->currentTotal,
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

            foreach ($this->rows as $row) {
                TransactionDetail::create([
                    'transaction_id' => $master->id,
                    'currency_code'  => $row['currency_code'],
                    'currency_name'  => $row['currency_name'],
                    'unit_price'     => $row['rate'],
                    'amount'         => $row['amount'],
                    'total'          => $row['total'],
                    'created_by'     => Auth::id(),
                ]);
            }

            // Save passport OCR data to customer if we have it
            if ($this->passportImageB64 && $this->ocrPassportNo) {
                $this->savePassportCustomer($master);
            }

            $this->savedTransactionId = $master->id;
        });

        $this->rows = [];
        $this->custName = '';
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
        // Store passport image
        $imagePath = null;
        if ($this->passportImageB64) {
            $imageData = base64_decode(preg_replace('/^data:image\/\w+;base64,/', '', $this->passportImageB64));
            $filename  = 'passports/' . now()->format('Y/m') . '/' . uniqid('pp_') . '.jpg';
            \Illuminate\Support\Facades\Storage::disk('public')->put($filename, $imageData);
            $imagePath = $filename;
        }

        $customer = Customer::updateOrCreate(
            ['id_type' => 'passport', 'id_number' => $this->ocrPassportNo],
            [
                'name_en'          => trim($this->ocrFirstName . ' ' . $this->ocrLastName),
                'first_name'       => $this->ocrFirstName,
                'last_name'        => $this->ocrLastName,
                'nationality'      => $this->ocrNationality,
                'date_of_birth'    => $this->ocrDob ?: null,
                'passport_expiry'  => $this->ocrExpiry ?: null,
                'passport_photo'   => $imagePath,
                'kyc_status'       => 'approved',
            ]
        );

        $master->update(['customer_id' => $customer->id]);
        $this->customerId = $customer->id;
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

    public function render()
    {
        return view('livewire.transaction.buy-form');
    }
};
?>

{{-- Template placeholder --}}