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
    // Counter selection
    public string $counterId = '';
    public string $counterCode = '';
    public bool $showCounterModal = false;

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
    public float $defaultRate = 0;   // rate ที่ตั้งค่าไว้ — role ที่แก้ไม่ได้จะถูกบังคับกลับมาค่านี้
    public float $currentTotal = 0;

    // Result
    public ?int $savedTransactionId = null;
    public bool $showPrintSlip = false;
    public array $savedRows = [];  // keep rows for display after save

    // เตือนเมื่อจ่ายบาทออกเกินยอดที่มีในลิ้นชัก — เตือนเท่านั้น ไม่บล็อกการบันทึก
    // (เงินทุนตั้งต้นในระบบมักไม่ครบ ถ้าบล็อกหน้าเคาน์เตอร์จะทำงานไม่ได้)
    public string $thbWarning = '';

    // Passport OCR fields (filled after capture)
    public string $passportImageB64 = '';
    public string $ocrFirstName = '';
    public string $ocrLastName = '';
    public string $ocrNationality = '';
    public string $ocrDob = '';
    public string $ocrPassportNo = '';
    public string $ocrExpiry = '';

    // --- Sanction screening ---------------------------------------------
    /** ผลการตรวจล่าสุด (array เพื่อให้ Livewire serialize ได้) */
    public ?array $sanctionScreening = null;

    /** @var array<int, array<string, mixed>> */
    public array $sanctionMatches = [];

    /** id ของ screening ที่ถูกอนุมัติแล้ว — ใช้ปลดล็อก saveTransaction */
    public ?int $approvedScreeningId = null;

    public bool $showSanctionApproval = false;
    public string $approverEmail = '';
    public string $approverPassword = '';
    public string $approvalReason = '';

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
            return;
        }
        $rate = CounterRate::where('counter_id', $this->counterId)
            ->where('denomination_id', $denomId)
            ->whereDate('rate_date', today())
            ->first();

        // ถ้าไม่มี rate วันนี้ ให้ดึง rate ล่าสุดมาใช้
        if (!$rate) {
            $rate = CounterRate::where('counter_id', $this->counterId)
                ->where('denomination_id', $denomId)
                ->orderBy('rate_date', 'desc')
                ->first();
        }

        $this->defaultRate = $rate ? (float) $rate->rate_buy : 0;
        $this->currentRate = $this->defaultRate;
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

        if (! $this->canEditRate) {
            $this->currentRate = $this->defaultRate;
            $this->recalcTotal();
        }

        if ($this->currentRate <= 0) return;

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
        $this->defaultRate      = 0;
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

        // Sanction gate — ต้องอยู่ "ก่อน" DB::transaction() ข้างล่าง
        //
        // ถ้าเช็กข้างในแล้วเจอ hit จะต้อง rollback ทั้งก้อน แปลว่า inventory,
        // GL journal และ ThbCash ถูกเขียนแล้วถอยกลับ เสี่ยงกับ auto-increment
        // ของเลขที่เอกสารและทำให้ debug ยาก
        //
        // แบบแผนเดียวกับยอดเงินบาทในลิ้นชักที่คำนวณก่อนเข้า transaction
        // ด้วยเหตุผลเดียวกันเป๊ะ
        if (! $this->passesSanctionGate()) {
            return;
        }

        $counter = Counter::find($this->counterId);
        $docNo = $this->generateDocNo('B');

        $thbCash = app(\App\Services\ThbCashService::class);
        $thbPayout = (float) collect($this->rows)->sum('total');

        // เช็กก่อนบันทึก เพราะหลังบันทึกยอดจะถูกหักไปแล้ว
        $this->thbWarning = $thbCash->wouldGoNegative((int) $this->counterId, $thbPayout)
            ? 'ยอดเงินบาทในลิ้นชักไม่พอจ่าย (คงเหลือ '
                . number_format($thbCash->balance((int) $this->counterId), 2)
                . ' บาท ต้องจ่าย ' . number_format($thbPayout, 2) . ' บาท) — บันทึกรายการแล้ว แต่ยอดคงเหลือจะติดลบ'
            : '';

        DB::transaction(function () use ($counter, $docNo, $thbPayout) {
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

            // จ่ายเงินบาทออกจากลิ้นชัก — บันทึกทีเดียวต่อบิล ไม่แยกตามรายการย่อย
            //
            // ต่างจาก recordBuy() ข้างบนที่ข้ามแถวไม่มี denomination_id เพราะ
            // ไม่รู้จะลงสต็อกช่องไหน — เงินบาทออกไปจริงทุกกรณี ไม่ว่าจะระบุ
            // denomination ของสกุลที่รับซื้อมาหรือไม่
            app(\App\Services\ThbCashService::class)->recordPurchasePayment(
                (int) $this->counterId,
                $thbPayout,
                $master->id,
                Auth::id(),
            );

            // Save customer data if we have passport no (from OCR or manual input)
            if ($this->ocrPassportNo) {
                $this->savePassportCustomer($master);
            }

            // Auto GL Journal
            $master->load('details');
            app(\App\Services\AutoJournalService::class)->createFromTransaction($master);

            $this->savedTransactionId = $master->id;

            // ผูกผลการตรวจเข้ากับธุรกรรม — รายงานต้องตอบได้ว่า
            // ธุรกรรมใบนี้ตรวจกับรายชื่อเวอร์ชันไหน ผลเป็นอะไร ใครอนุมัติ
            if (! empty($this->sanctionScreening['id'])) {
                \App\Models\SanctionScreening::where('id', $this->sanctionScreening['id'])
                    ->update([
                        'transaction_id' => $master->id,
                        'customer_id' => $this->customerId,
                    ]);
            }
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
            ['id_type' => $this->ocrIdType, 'id_number' => $this->ocrPassportNo],
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

        $this->runSanctionScreening(\App\Models\SanctionScreening::TRIGGER_TRANSACTION);
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
            ['id_type' => $this->ocrIdType, 'id_number' => $this->ocrPassportNo],
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
        $this->defaultRate      = 0;
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
        $this->ocrIdType        = 'passport';
        $this->ocrExpiry        = '';
        $this->passportSearch   = '';
        $this->customerSuggestions = [];
        $this->showSuggestions  = false;
    }

    /** ชนิดเอกสารของลูกค้าที่กำลังกรอก — บัตรประชาชนไทยไม่ใช่พาสปอร์ต */
    public string $ocrIdType = 'passport';

    /**
     * เสียบบัตรแล้วคอมโพเนนต์ card-reader.inbox ส่งข้อมูลมาให้
     *
     * การเฝ้ากล่องรับอยู่ที่คอมโพเนนต์นั้น ไม่ใช่ที่นี่ — ฟอร์มนี้มีหน้าต่างครอป
     * รูปพาสปอร์ตที่ Cropper.js ฉีด DOM ของตัวเองเข้าไป ถ้าฟอร์ม re-render
     * ทุกวินาทีครึ่ง Livewire จะลบกรอบครอปทิ้งเพราะเซิร์ฟเวอร์ไม่รู้จักมัน
     *
     * @param array<string, mixed> $card
     */
    #[\Livewire\Attributes\On('card-read')]
    public function onCardRead(array $card): void
    {
        if (($card['kind'] ?? '') === 'passport') {
            $this->fillFromPassportChip($card);

            return;
        }

        $this->fillFromIdCard($card);
    }

    /**
     * ขอให้ agent อ่านชิปพาสปอร์ต โดยส่งกุญแจที่ได้จาก OCR ไปฝากไว้
     *
     * ชิปล็อกอยู่ ต้องมีเลขพาสปอร์ต + วันเกิด + วันหมดอายุ ถึงจะเปิดได้
     * (ICAO 9303) ค่าทั้งสามมาจากบรรทัดที่สองของ MRZ ซึ่งมีเลขตรวจสอบกำกับ
     * ฝั่ง JS จึงเรียกเมธอดนี้เฉพาะตอนเลขตรวจสอบผ่านแล้วเท่านั้น
     */
    public function requestPassportChip(string $documentNo, string $dob, string $expiry): void
    {
        if (! $this->counterId) {
            return;
        }

        // ข้อความผิดพลาดของรอบก่อนไม่เกี่ยวกับรอบใหม่ ค้างไว้ก็มีแต่ทำให้สับสน
        $this->chipError = '';

        app(\App\Services\CardReader\PassportChipRequests::class)->open(
            (int) $this->counterId,
            ['document_no' => $documentNo, 'date_of_birth' => $dob, 'expiry_date' => $expiry],
            \Illuminate\Support\Facades\Auth::id(),
        );
    }

    /** เหตุผลที่อ่านชิปไม่สำเร็จ ว่างเมื่อไม่มีปัญหา */
    public string $chipError = '';

    #[\Livewire\Attributes\On('chip-failed')]
    public function onChipFailed(string $reason): void
    {
        $this->chipError = $reason;
    }

    #[\Livewire\Attributes\On('chip-cleared')]
    public function onChipCleared(): void
    {
        $this->chipError = '';
    }

    /**
     * อ่านชิปใหม่ด้วยค่าที่พนักงานแก้เอง
     *
     * กุญแจเปิดชิปสร้างจากเลขพาสปอร์ต วันเกิด และวันหมดอายุ ถ้า OCR อ่านผิด
     * แม้ตัวเดียวชิปจะไม่ยอมเปิด สามช่องนั้นแก้ได้อยู่แล้วบนฟอร์ม ปุ่มนี้จึงเอา
     * ค่าที่เห็นอยู่ตรงหน้าไปลองใหม่ แทนที่จะบังคับให้ถ่ายรูปใหม่ทั้งใบ
     */
    public function retryPassportChip(): void
    {
        $this->chipError = '';

        $yymmdd = function (string $iso): string {
            $d = \DateTime::createFromFormat('Y-m-d', trim($iso));

            return $d === false ? '' : $d->format('ymd');
        };

        $dob = $yymmdd($this->ocrDob);
        $expiry = $yymmdd($this->ocrExpiry);
        $no = trim($this->ocrPassportNo);

        if ($no === '' || $dob === '' || $expiry === '') {
            $this->chipError = 'ต้องมีเลขพาสปอร์ต วันเกิด และวันหมดอายุ ครบทั้งสามช่องก่อนจึงจะอ่านชิปได้';

            return;
        }

        $this->requestPassportChip($no, $dob, $expiry);
    }

    /** ชื่อ วันเกิด และรูปจากชิป เชื่อถือได้กว่าทุกอย่างที่ OCR ให้ */
    public string $chipPhoto = '';
    public string $chipAuthenticity = '';

    /** @param array<string, mixed> $card */
    private function fillFromPassportChip(array $card): void
    {
        $this->ocrIdType = 'passport';
        $this->ocrPassportNo = (string) ($card['document_no'] ?? '');
        $this->ocrFirstName = (string) ($card['given_names'] ?? '');
        $this->ocrLastName = (string) ($card['surname'] ?? '');
        $this->ocrNationality = (string) ($card['nationality'] ?? '');
        $this->ocrDob = (string) ($card['date_of_birth'] ?? '');
        $this->ocrExpiry = (string) ($card['expiry_date'] ?? '');
        $this->custName = trim($this->ocrFirstName . ' ' . $this->ocrLastName);

        $this->chipError = '';
        $this->chipAuthenticity = (string) ($card['authenticity'] ?? '');
        $this->chipPhoto = ! empty($card['photo_base64']) && ($card['photo_mime'] ?? '') === 'image/jpeg'
            ? 'data:image/jpeg;base64,' . $card['photo_base64']
            : '';

        $this->runSanctionScreening(\App\Models\SanctionScreening::TRIGGER_TRANSACTION);
    }

    /** @param array<string, mixed> $card */
    private function fillFromIdCard(array $card): void
    {
        $citizenId = (string) ($card['citizen_id'] ?? '');

        // คนเดิมที่อยู่ในฟอร์มอยู่แล้วไม่ต้องสแกนซ้ำ
        //
        // การสแกนแต่ละครั้งสร้างรายการตรวจรายชื่อหนึ่งแถว ถ้าปล่อยให้ยิงซ้ำ
        // ประวัติการตรวจของลูกค้าคนเดียวจะกลายเป็นสิบแถวในนาทีเดียว
        // แล้วรายงานการตรวจรายชื่อจะอ่านไม่ได้ความ
        if ($citizenId !== '' && $citizenId === $this->ocrPassportNo && $this->ocrIdType === 'national_id') {
            return;
        }

        $this->ocrIdType     = 'national_id';
        $this->ocrPassportNo = $citizenId;
        $this->ocrFirstName  = (string) ($card['first_name_en'] ?? '');
        $this->ocrLastName   = (string) ($card['last_name_en'] ?? '');
        $this->ocrDob        = (string) ($card['date_of_birth'] ?? '');
        $this->ocrExpiry     = (string) ($card['expire_date'] ?? '');

        // บัตรประชาชนไทยออกให้คนสัญชาติไทยเท่านั้น ไม่ต้องเดา
        $this->ocrNationality = 'THA';

        // ชื่อไทยคือสิ่งที่ตรงกับรายชื่อ ปปง. ฝั่งไทย ใช้เป็นชื่อหลักถ้ามี
        $this->custName = trim((string) ($card['name_th'] ?? ''))
            ?: trim($this->ocrFirstName . ' ' . $this->ocrLastName);

        $this->runSanctionScreening(\App\Models\SanctionScreening::TRIGGER_TRANSACTION);
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

        $this->runSanctionScreening(\App\Models\SanctionScreening::TRIGGER_TRANSACTION);
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
            ->where('counter_rates.rate_buy', '>', 0)
            ->select('counter_rates.denomination_id', DB::raw('MAX(counter_rates.rate_date) as latest_date'))
            ->groupBy('counter_rates.denomination_id');

        return CounterRate::with(['currency', 'denomination'])
            ->where('counter_rates.counter_id', $this->counterId)
            ->whereNotNull('counter_rates.denomination_id')
            ->where('counter_rates.rate_buy', '>', 0)
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

    // =====================================================================
    // Sanction screening
    // =====================================================================

    private function screeningInput(): \App\Services\Sanction\Dto\ScreeningInput
    {
        $name = trim($this->custName) !== ''
            ? trim($this->custName)
            : trim(($this->ocrFirstName ?? '') . ' ' . ($this->ocrLastName ?? ''));

        return new \App\Services\Sanction\Dto\ScreeningInput(
            name: trim($name) !== '' ? trim($name) : null,
            idType: trim((string) $this->ocrPassportNo) !== '' ? $this->ocrIdType : null,
            idNumber: trim((string) $this->ocrPassportNo) !== '' ? trim((string) $this->ocrPassportNo) : null,
            nationality: trim((string) $this->ocrNationality) !== '' ? trim((string) $this->ocrNationality) : null,
            dob: trim((string) $this->ocrDob) !== '' ? trim((string) $this->ocrDob) : null,
        );
    }

    /**
     * ตรวจและเก็บผลไว้แสดงบนหน้าจอ
     * เรียกจาก receiveOcrData(), selectCustomer() และ saveTransaction()
     */
    public function runSanctionScreening(string $trigger = \App\Models\SanctionScreening::TRIGGER_TRANSACTION): \App\Models\SanctionScreening
    {
        // counterId เป็น string property — ว่างได้ตอน OCR ยิงมาก่อนเลือกเคาน์เตอร์
        // ส่ง '' เข้า ?int จะเป็น TypeError ต้อง cast เองที่นี่
        $counterId = $this->counterId !== '' ? (int) $this->counterId : null;
        $counter = $counterId ? Counter::find($counterId) : null;

        $screening = app(\App\Services\Sanction\SanctionScreeningService::class)->screen(
            input: $this->screeningInput(),
            trigger: $trigger,
            screenedBy: Auth::id(),
            customerId: $this->customerId,
            branchId: $counter?->branch_id,
            counterId: $counterId,
        );

        $this->sanctionScreening = [
            'id' => $screening->id,
            'result' => $screening->result,
            'input_name' => $screening->input_name,
            'input_dob' => $screening->input_dob,
            'input_nationality' => $screening->input_nationality,
            'input_id_number' => $screening->input_id_number,
            'screened_at_label' => $screening->screened_at->format('d/m/Y H:i'),
        ];

        $this->sanctionMatches = $screening->matches()->with('entry')->get()
            ->map(fn (\App\Models\SanctionScreeningMatch $m): array => [
                'score' => (float) $m->score,
                'severity' => \App\Services\Sanction\MatchScorer::severity((float) $m->score),
                'matched_on' => $m->matched_on,
                // บอกผู้อนุมัติว่าทำไมคะแนนออกมาเท่านี้ — เห็นแค่ตัวเลขลอย ๆ
                // ตัดสินใจไม่ได้ว่าควรเชื่อแค่ไหน
                'why' => $this->explainMatch($m),
                'list_label' => $m->entry->list_code === \App\Models\SanctionEntry::LIST_FREEZE_05_TH
                    ? 'FREEZE-05 Thailand list'
                    : ($m->entry->list_code === \App\Models\SanctionEntry::LIST_FREEZE_04_UN
                        ? 'FREEZE-04 UN list'
                        : $m->entry->list_code),
                'section' => $m->entry->section,
                'notification_number' => $m->entry->notification_number,
                'reference_number' => $m->entry->reference_number,
                'entry_name_en' => $m->entry->name_en,
                'entry_name_th' => $m->entry->name_th,
                'entry_dob' => $m->entry->date_of_birth,
                'entry_nationality' => $m->entry->nationality,
                'entry_id_number' => $m->entry->national_id,
            ])->all();

        return $screening;
    }

    public function openSanctionApproval(): void
    {
        $this->showSanctionApproval = true;
        $this->approverEmail = '';
        $this->approverPassword = '';
        $this->approvalReason = '';
    }

    public function cancelForSanction(): void
    {
        $this->sanctionScreening = null;
        $this->sanctionMatches = [];
        $this->approvedScreeningId = null;
        $this->showSanctionApproval = false;
        $this->rows = [];
    }

    /**
     * Supervisor override ที่เคาน์เตอร์ — ผู้จัดการเดินมาใส่รหัสของตัวเอง
     * ตรวจ credential + permission โดยไม่ logout พนักงาน
     *
     * screened_by ยังเป็นพนักงาน ส่วน decided_by เป็นผู้จัดการ
     */
    public function submitSanctionApproval(): void
    {
        $this->resetErrorBag();

        if (mb_strlen(trim($this->approvalReason)) < \App\Services\Sanction\SanctionScreeningService::MIN_REASON_LENGTH) {
            $this->addError('approvalReason', 'ต้องระบุเหตุผลอย่างน้อย '
                . \App\Services\Sanction\SanctionScreeningService::MIN_REASON_LENGTH . ' ตัวอักษร');

            return;
        }

        $approver = \App\Models\User::where('email', $this->approverEmail)->first();

        if ($approver === null
            || ! \Illuminate\Support\Facades\Hash::check($this->approverPassword, $approver->password)) {
            $this->addError('approverEmail', 'อีเมลหรือรหัสผ่านไม่ถูกต้อง');

            return;
        }

        if (! $approver->hasPermission('module7', 'approve')) {
            $this->addError('approverEmail', 'ผู้ใช้นี้ไม่มีสิทธิ์อนุมัติรายการที่พบชื่อใกล้เคียง');

            return;
        }

        $screeningId = $this->sanctionScreening['id'] ?? null;

        if ($screeningId === null) {
            $this->addError('approverEmail', 'ไม่พบผลการตรวจที่จะอนุมัติ');

            return;
        }

        $screening = \App\Models\SanctionScreening::find($screeningId);

        if ($screening === null || $screening->isBlocked()) {
            // ตรงเป๊ะด้วยเลขเอกสารบน FREEZE list — กฎหมายไม่เปิดช่องให้อนุมัติผ่าน
            $this->addError('approverEmail', 'รายการนี้อนุมัติผ่านไม่ได้');

            return;
        }

        // decide() ปฏิเสธการตัดสินซ้ำด้วย exception — ถ้าหน้าจอค้างแล้วกดยืนยัน
        // สองครั้ง จะกลายเป็น 500 ใส่หน้าพนักงาน จึงจับไว้เป็น error ในฟอร์ม
        try {
            app(\App\Services\Sanction\SanctionScreeningService::class)->decide(
                screening: $screening,
                decision: \App\Models\SanctionScreening::DECISION_FALSE_POSITIVE,
                decidedBy: $approver->id,
                reason: trim($this->approvalReason),
            );
        } catch (\InvalidArgumentException $e) {
            $this->addError('approverEmail', $e->getMessage());

            return;
        }

        $this->approvedScreeningId = $screening->id;
        $this->showSanctionApproval = false;
        $this->approverPassword = '';

        $this->dispatch('sanction-approved', screeningId: $screening->id);
    }

    /**
     * ลายนิ้วมือของการตรวจหนึ่งครั้ง — "ใครถูกตรวจ และชนกับใครในลิสต์"
     *
     * ใช้ผูกการอนุมัติไว้กับคนคนนั้น ไม่ใช่ผูกไว้กับหน้าจอ
     */
    private function sanctionFingerprint(\App\Models\SanctionScreening $screening): string
    {
        $entryIds = $screening->matches()->pluck('sanction_entry_id')->sort()->values()->all();

        return sha1(implode('|', [
            mb_strtoupper(trim((string) $screening->input_name)),
            mb_strtoupper(trim((string) $screening->input_id_number)),
            implode(',', $entryIds),
        ]));
    }

    /**
     * ด่านสุดท้าย — คืน true แปลว่าให้ทำรายการต่อได้
     *
     * บังคับใช้ฝั่ง server เสมอ: saveTransaction() ถูกเรียกจาก client ได้ตรงๆ
     * เลยต้องตรวจซ้ำว่ามี decision record จริง ไม่เชื่อ state ฝั่งหน้าจอ
     */
    private function passesSanctionGate(): bool
    {
        $screening = $this->runSanctionScreening(\App\Models\SanctionScreening::TRIGGER_TRANSACTION);

        if ($screening->result === \App\Models\SanctionScreening::RESULT_CLEAR) {
            $this->approvedScreeningId = $screening->id;

            return true;
        }

        if ($screening->isBlocked()) {
            $this->approvedScreeningId = null;

            return false;
        }

        // potential_match — ผ่านได้ก็ต่อเมื่อมี decision ที่บันทึกไว้จริงในฐานข้อมูล
        $approval = $this->approvedScreeningId === null
            ? null
            : \App\Models\SanctionScreening::where('id', $this->approvedScreeningId)
                ->whereNotNull('decision')
                ->whereNotNull('decided_by')
                ->first();

        // การอนุมัติผูกกับ "คนที่ถูกตรวจ" ไม่ใช่ผูกกับหน้าจอ — ถ้าไม่เทียบลายนิ้วมือ
        // พนักงานขออนุมัติชื่อที่พิสูจน์ได้ทีเดียว แล้วแก้ชื่อเป็นคนอื่นในลิสต์
        // กดบันทึกต่อได้เรื่อย ๆ โดยใช้ใบอนุมัติเดิม
        if ($approval !== null
            && $this->sanctionFingerprint($approval) !== $this->sanctionFingerprint($screening)) {
            $approval = null;
            $this->approvedScreeningId = null;
        }

        if ($approval !== null) {
            // ผูก screening ล่าสุดเข้ากับการอนุมัติเดิม แล้วปล่อยผ่าน
            // เขียนตรงไม่ผ่าน decide() โดยตั้งใจ — decide() ห้ามตัดสินซ้ำ
            // และนี่คือการ "สืบทอด" การตัดสินเดิม ไม่ใช่การตัดสินใหม่
            $screening->update([
                'decision' => $approval->decision,
                'decided_by' => $approval->decided_by,
                'decided_at' => now(),
                'decision_reason' => $approval->decision_reason,
            ]);

            return true;
        }

        $this->openSanctionApproval();

        return false;
    }

    /**
     * อธิบายเป็นภาษาคนว่าทำไมการแมตช์ครั้งนี้ได้คะแนนเท่านี้
     *
     * คะแนน 72 กับ 95 ต่างกันด้วยเหตุผลที่คนอนุมัติต้องรู้ — ตรงที่ชื่อสามัญ
     * โดยไม่มีวันเกิดยืนยัน ไม่เหมือนกับตรงทั้งชื่อและวันเกิด
     *
     * @return array<int, string>
     */
    private function explainMatch(\App\Models\SanctionScreeningMatch $m): array
    {
        $why = [];
        $entry = $m->entry;

        $rarity = app(\App\Services\Sanction\NameRarityIndex::class)->explain($m->matched_name);
        if ($rarity['commonest'] !== null && $rarity['commonest']['count'] >= 10) {
            $why[] = 'ชื่อ "' . $rarity['commonest']['token'] . '" พบใน '
                . $rarity['commonest']['count'] . ' รายชื่อ — เป็นชื่อที่พบบ่อย';
        } elseif ($rarity['information'] !== null && $rarity['factor'] >= 1.0) {
            $why[] = 'ชื่อนี้เจาะจง พบไม่บ่อยในรายชื่อ';
        }

        $dob = \App\Services\Sanction\MatchScorer::compareDob($this->ocrDob ?: null, $entry?->date_of_birth);
        $why[] = match ($dob) {
            'match' => 'วันเกิดตรงกัน',
            'mismatch' => 'วันเกิดไม่ตรงกัน',
            default => trim((string) $this->ocrDob) === ''
                ? 'ยังไม่ได้กรอกวันเกิดลูกค้า — กรอกแล้วระบบอาจตัดรายการนี้ออกเองได้'
                : 'รายชื่อนี้ไม่มีวันเกิดในฐานข้อมูล ปปง.',
        };

        $nat = \App\Services\Sanction\MatchScorer::compareNationality($this->ocrNationality ?: null, $entry?->nationality);
        if ($nat !== 'unknown') {
            $why[] = $nat === 'match' ? 'สัญชาติตรงกัน' : 'สัญชาติไม่ตรงกัน';
        }

        return $why;
    }

    public function render()
    {
        return view('livewire.transaction.buy-form');
    }
};
?>

{{-- Template placeholder --}}