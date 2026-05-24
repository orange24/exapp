<?php

namespace App\Livewire\Inventory;

use App\Models\Booking;
use App\Models\Counter;
use App\Models\CounterStock;
use App\Models\Currency;
use App\Models\CurrencyDenomination;
use App\Models\Customer;
use App\Models\TransactionMaster;
use App\Models\TransactionDetail;
use App\Services\AutoJournalService;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class BookingManager extends Component
{
    use WithPagination;

    // Form
    public bool $showForm = false;
    public string $type = 'buy';
    public string $currencyCode = '';
    public string $denominationId = '';
    public string $amount = '';
    public string $rate = '';
    public string $customerName = '';
    public string $customerId = '';
    public string $expiresIn = '60'; // minutes
    public string $note = '';

    // Filters
    public string $filterStatus = '';
    public string $filterDateFrom = '';
    public string $filterDateTo = '';

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->filterDateFrom = now()->format('Y-m-d');
        $this->filterDateTo = now()->format('Y-m-d');
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

    public function updatedCurrencyCode(): void
    {
        $this->denominationId = '';
    }

    public function getBookingsProperty()
    {
        $counterId = session('working_counter_id');
        $query = Booking::with(['counter.branch', 'customer', 'createdBy', 'transaction', 'denomination'])
            ->orderByDesc('created_at');

        if ($counterId && !Auth::user()->isAdmin()) {
            $query->where('counter_id', $counterId);
        }

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterDateFrom) {
            $query->whereDate('created_at', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('created_at', '<=', $this->filterDateTo);
        }

        return $query->paginate(20);
    }

    public function save(): void
    {
        $this->validate([
            'type' => 'required|in:buy,sell',
            'currencyCode' => 'required',
            'amount' => 'required|numeric|min:0.01',
            'rate' => 'required|numeric|min:0.000001',
            'expiresIn' => 'required|integer|min:1|max:1440',
        ], [
            'currencyCode.required' => 'กรุณาเลือกสกุลเงิน',
            'amount.required' => 'กรุณาระบุจำนวน',
            'rate.required' => 'กรุณาระบุอัตราแลกเปลี่ยน',
        ]);

        $counterId = session('working_counter_id');
        if (!$counterId) {
            session()->flash('error', 'กรุณาเลือกเคาน์เตอร์ก่อน');
            return;
        }

        $holdAmount = (float) $this->amount;

        // For sell booking: check stock availability and hold
        if ($this->type === 'sell') {
            $stock = CounterStock::where('counter_id', $counterId)
                ->where('currency_code', $this->currencyCode)
                ->first();

            $available = $stock ? ((float) $stock->quantity - (float) $stock->hold_amount) : 0;
            if ($holdAmount > $available) {
                $this->addError('amount', "สต็อกว่างไม่เพียงพอ (คงเหลือ: " . number_format($available, 2) . ")");
                return;
            }
        }

        DB::transaction(function () use ($counterId, $holdAmount) {
            $booking = Booking::create([
                'customer_id' => $this->customerId ?: null,
                'counter_id' => $counterId,
                'currency_code' => $this->currencyCode,
                'denomination_id' => $this->denominationId ?: null,
                'amount' => (float) $this->amount,
                'rate' => (float) $this->rate,
                'type' => $this->type,
                'status' => 'pending',
                'hold_amount' => $holdAmount,
                'expires_at' => now()->addMinutes((int) $this->expiresIn),
                'created_by' => Auth::id(),
            ]);

            // Hold stock for sell bookings
            if ($this->type === 'sell') {
                CounterStock::where('counter_id', $counterId)
                    ->where('currency_code', $this->currencyCode)
                    ->increment('hold_amount', $holdAmount);
            }
        });

        $this->resetForm();
        session()->flash('success', 'สร้าง Booking สำเร็จ');
    }

    public function confirmBooking(int $id): void
    {
        $booking = Booking::findOrFail($id);
        if ($booking->status !== 'pending') {
            session()->flash('error', 'Booking นี้ไม่สามารถยืนยันได้');
            return;
        }

        // Check if expired
        if ($booking->isExpired()) {
            $this->expireBooking($booking);
            session()->flash('error', 'Booking หมดอายุแล้ว — stock ถูกปล่อยคืน');
            return;
        }

        DB::transaction(function () use ($booking) {
            // Release hold first
            if ($booking->type === 'sell' && $booking->hold_amount > 0) {
                CounterStock::where('counter_id', $booking->counter_id)
                    ->where('currency_code', $booking->currency_code)
                    ->decrement('hold_amount', (float) $booking->hold_amount);
            }

            // Create real transaction from booking
            $trnsType = $booking->type === 'sell' ? 'SELLING' : 'BUYING';
            $prefix = $booking->type === 'sell' ? 'S' : 'B';
            $docNo = $this->generateDocNo($prefix);
            $counter = Counter::find($booking->counter_id);

            $thbAmount = round($booking->amount * $booking->rate, 2);
            if ($booking->type === 'sell') {
                // Sell: amount = THB from customer, total = foreign given
                $foreignAmount = $booking->amount;
                $thbAmount = round($foreignAmount * $booking->rate, 0);
            }

            $master = TransactionMaster::create([
                'trns_no'        => $docNo,
                'trns_type'      => $trnsType,
                'counter_id'     => $booking->counter_id,
                'counter_name'   => $counter?->counter_name,
                'customer_id'    => $booking->customer_id,
                'cust_name'      => $booking->customer?->name_en ?? $booking->customerName ?? '',
                'convert_currency_to' => 'THB',
                'trns_datetime'  => now(),
                'created_by'     => Auth::id(),
                'updated_by'     => Auth::id(),
            ]);

            // Determine denomination
            $denomId = $booking->denomination_id;
            $denom = $denomId ? CurrencyDenomination::find($denomId) : null;

            if ($booking->type === 'sell') {
                TransactionDetail::create([
                    'transaction_id'  => $master->id,
                    'currency_code'   => $booking->currency_code,
                    'denomination_id' => $denomId,
                    'currency_name'   => $denom?->display_name ?? $booking->currency_code,
                    'unit_price'      => $booking->rate,
                    'amount'          => $thbAmount,        // THB from customer
                    'total'           => $booking->amount,  // foreign given
                    'created_by'      => Auth::id(),
                ]);

                // Cut inventory
                if ($denomId) {
                    app(InventoryService::class)->recordSell(
                        $booking->counter_id,
                        $booking->currency_code,
                        $denomId,
                        $booking->amount,
                        $booking->rate,
                        $master->id,
                        Auth::id()
                    );
                }
            } else {
                // Buy: amount = foreign, total = THB
                TransactionDetail::create([
                    'transaction_id'  => $master->id,
                    'currency_code'   => $booking->currency_code,
                    'denomination_id' => $denomId,
                    'currency_name'   => $denom?->display_name ?? $booking->currency_code,
                    'unit_price'      => $booking->rate,
                    'amount'          => $booking->amount,
                    'total'           => $thbAmount,
                    'created_by'      => Auth::id(),
                ]);

                if ($denomId) {
                    app(InventoryService::class)->recordBuy(
                        $booking->counter_id,
                        $booking->currency_code,
                        $denomId,
                        $booking->amount,
                        $booking->rate,
                        $master->id,
                        Auth::id()
                    );
                }
            }

            // Auto GL Journal
            $master->load('details');
            app(AutoJournalService::class)->createFromTransaction($master);

            // Link booking to transaction
            $booking->update([
                'status' => 'confirmed',
                'transaction_id' => $master->id,
            ]);
        });

        session()->flash('success', 'ยืนยัน Booking สำเร็จ — สร้างรายการซื้อ/ขายเรียบร้อย');
    }

    private function expireBooking(Booking $booking): void
    {
        DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'expired']);
            if ($booking->type === 'sell' && $booking->hold_amount > 0) {
                CounterStock::where('counter_id', $booking->counter_id)
                    ->where('currency_code', $booking->currency_code)
                    ->decrement('hold_amount', (float) $booking->hold_amount);
            }
        });
    }

    private function generateDocNo(string $prefix): string
    {
        $today = now()->format('Ymd');
        $last = TransactionMaster::where('trns_no', 'like', $prefix . $today . '%')
            ->orderByDesc('trns_no')->first();
        $seq = $last ? (int) substr($last->trns_no, -4) + 1 : 1;
        return $prefix . $today . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    public function cancelBooking(int $id): void
    {
        $booking = Booking::findOrFail($id);
        if ($booking->status !== 'pending') {
            session()->flash('error', 'Booking นี้ไม่สามารถยกเลิกได้');
            return;
        }

        DB::transaction(function () use ($booking) {
            $booking->update(['status' => 'cancelled']);

            // Release hold for sell bookings
            if ($booking->type === 'sell') {
                CounterStock::where('counter_id', $booking->counter_id)
                    ->where('currency_code', $booking->currency_code)
                    ->decrement('hold_amount', (float) $booking->hold_amount);
            }
        });

        session()->flash('success', 'ยกเลิก Booking สำเร็จ');
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->type = 'buy';
        $this->currencyCode = '';
        $this->denominationId = '';
        $this->amount = '';
        $this->rate = '';
        $this->customerName = '';
        $this->customerId = '';
        $this->expiresIn = '60';
        $this->note = '';
    }

    public function render()
    {
        return view('livewire.inventory.booking-manager');
    }
}
