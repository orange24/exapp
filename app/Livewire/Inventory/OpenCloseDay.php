<?php

namespace App\Livewire\Inventory;

use App\Models\Counter;
use App\Models\CounterStock;
use App\Models\Inventory;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\WorkingDay;
use App\Services\InventoryService;
use App\Services\ThbCashService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class OpenCloseDay extends Component
{
    public string $counterId = '';
    public string $date = '';
    public string $note = '';

    /**
     * เงินทุนที่จะเติมเข้าลิ้นชักตอนเปิดวัน — optional
     *
     * เดิมช่องนี้คือ "ยอดบาทที่รับมา" ที่พนักงานต้องกรอกทุกวันและถูกเก็บลง
     * working_days.opening_thb_cash ตรงๆ โดยไม่มีใครคำนวณยอดคงเหลือต่อ
     * ตอนนี้ยอดยกมาถูกคำนวณสดจาก movement แล้ว ช่องนี้จึงเหลือหน้าที่แค่
     * "เติมเงินเพิ่มเท่าไหร่" ซึ่งเว้นว่างได้ถ้าเงินทุนยกมาพอแล้ว
     */
    public float $topupThbCash = 0;

    // Closing flow
    public bool $showClosingForm = false;
    public array $closingItems = [];
    public string $closingNotes = '';

    /** ยอดบาทที่นับได้จริงตอนปิดวัน */
    public float $closingThbActual = 0;

    // เติมเงินทุน / ส่งบาทคืนคลัง (ตัดสองขากับเคาน์เตอร์คลัง)
    public float $transferAmount = 0;
    public string $transferNote = '';

    public function mount(): void
    {
        $this->counterId = (string) (session('working_counter_id') ?? '');
        $this->date = now()->format('Y-m-d');
    }

    public function getCountersProperty()
    {
        $query = Counter::with('branch')->where('is_active', true)
            ->orderBy('branch_id')->orderBy('counter_name');

        if (!Auth::user()->isAdmin()) {
            $query->whereIn('branch_id', Auth::user()->getVisibleBranchIds());
        }

        return $query->get();
    }

    public function getWorkingDayProperty()
    {
        if (!$this->counterId || !$this->date) {
            return null;
        }

        return WorkingDay::where('counter_id', $this->counterId)
            ->whereDate('work_date', $this->date)
            ->first();
    }

    public function getInventorySnapshotProperty()
    {
        if (!$this->counterId) {
            return collect();
        }

        return Inventory::where('counter_id', $this->counterId)
            ->where('date', $this->date)
            ->with('denomination')
            ->orderBy('currency_code')
            ->get();
    }

    /**
     * ยอดเงินบาท: ยกมา / เข้า / ออก / คงเหลือ ของวันที่เลือก
     *
     * คำนวณสดจาก movement ทุกครั้ง ไม่อ่านยอดปิดของวันก่อนหน้า — วันก่อนจะปิดยอด
     * หรือเว้นไปกี่วันก็ไม่กระทบ
     */
    public function getThbSummaryProperty(): array
    {
        if (! $this->counterId || ! $this->date) {
            return ['opening' => 0.0, 'in' => 0.0, 'out' => 0.0, 'closing' => 0.0];
        }

        return app(ThbCashService::class)->summaryFor((int) $this->counterId, $this->date);
    }

    public function getRecentDaysProperty()
    {
        if (!$this->counterId) {
            return collect();
        }

        return WorkingDay::where('counter_id', $this->counterId)
            ->with(['openedByUser', 'closedByUser'])
            ->orderByDesc('work_date')
            ->limit(15)
            ->get();
    }

    public function openDay(): void
    {
        if (!$this->counterId) {
            session()->flash('error', 'กรุณาเลือกเคาน์เตอร์');
            return;
        }

        // ไม่บังคับให้กรอกเงินทุนอีกต่อไป — เคาน์เตอร์ที่มีเงินบาทยกมาจากวันก่อน
        // เปิดวันได้เลยโดยไม่ต้องเติมอะไร

        // Check if already opened
        $existing = WorkingDay::where('counter_id', $this->counterId)
            ->whereDate('work_date', $this->date)
            ->first();

        if ($existing) {
            session()->flash('error', 'วันทำการนี้ถูกเปิดแล้ว');
            return;
        }

        $thbCash = app(ThbCashService::class);
        $topup = (float) $this->topupThbCash;

        // เงินที่เติมตอนนี้เกิดที่ now() ซึ่งอยู่หลัง cutoff ของวันนี้ จึงนับเป็น
        // "รับเข้าระหว่างวัน" ไม่ใช่ "ยอดยกมา" — opening_thb_cash ข้างล่างจะยัง
        // เป็นยอดที่ยกมาจากวันก่อนเท่านั้น ตามความหมายของคำว่ายกมา
        if ($topup > 0) {
            $thbCash->topUp(
                (int) $this->counterId,
                $topup,
                Auth::id(),
                'เติมเงินทุนตอนเปิดวันทำการ ' . Carbon::parse($this->date)->format('d/m/Y'),
            );
        }

        // opening_thb_cash เก็บยอดยกมาที่ระบบคำนวณได้ ไม่ใช่ยอดที่พนักงานกรอก
        // — เป็น snapshot ไว้ตรวจย้อนหลังว่าตอนเปิดวันระบบเห็นเท่าไหร่
        WorkingDay::create([
            'counter_id' => $this->counterId,
            'work_date' => $this->date,
            'opening_thb_cash' => $thbCash->openingFor((int) $this->counterId, $this->date),
            'status' => 'open',
            'opened_by' => Auth::id(),
            'opened_at' => now(),
            'note' => $this->note ?: null,
        ]);

        // Snapshot opening balances
        $service = app(InventoryService::class);
        $service->openDay((int) $this->counterId, $this->date);

        $this->note = '';
        $this->topupThbCash = 0;

        session()->flash('success', 'เปิดวันทำการสำเร็จ - เงินบาทคงเหลือ: '
            . number_format($thbCash->balance((int) $this->counterId), 2) . ' บาท');
    }

    /** เติมเงินทุนเข้าลิ้นชักระหว่างวัน */
    public function topUpCash(): void
    {
        $this->runCashMovement(isTopUp: true);
    }

    /** นำเงินบาทออกจากลิ้นชัก (ฝากเข้าบัญชี / ส่งคืน) */
    public function withdrawCash(): void
    {
        $this->runCashMovement(isTopUp: false);
    }

    private function runCashMovement(bool $isTopUp): void
    {
        if (! $this->counterId) {
            session()->flash('error', 'กรุณาเลือกเคาน์เตอร์');
            return;
        }

        $thbCash = app(ThbCashService::class);
        $amount = (float) $this->transferAmount;

        try {
            $isTopUp
                ? $thbCash->topUp((int) $this->counterId, $amount, Auth::id(), $this->transferNote ?: null)
                : $thbCash->withdraw((int) $this->counterId, $amount, Auth::id(), $this->transferNote ?: null);
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
            return;
        }

        $this->transferAmount = 0;
        $this->transferNote = '';

        session()->flash('success', ($isTopUp ? 'เติมเงินทุน ' : 'นำเงินออก ')
            . number_format($amount, 2) . ' บาท สำเร็จ');
    }

    public function prepareClosing(): void
    {
        if (!$this->counterId) {
            session()->flash('error', 'กรุณาเลือกเคาน์เตอร์');
            return;
        }

        // Get current stock for all denominations
        $stocks = CounterStock::where('counter_id', $this->counterId)
            ->whereNotNull('denomination_id')
            ->where('quantity', '>', 0)
            ->with('denomination.currency')
            ->orderBy('currency_code')
            ->get();

        $this->closingItems = $stocks->map(function ($stock) {
            return [
                'denomination_id' => $stock->denomination_id,
                'currency_code' => $stock->currency_code,
                'denom_label' => $stock->denomination?->display_name ?? $stock->currency_code,
                'expected' => (float) $stock->quantity,
                'actual' => 0, // ให้ staff กรอกเอง
                'variance' => -(float) $stock->quantity, // negative variance initially
            ];
        })->toArray();

        // ตั้งค่าเริ่มต้นเป็นยอดตามระบบ พนักงานนับเงินแล้วแก้ทับถ้าไม่ตรง
        $this->closingThbActual = app(ThbCashService::class)
            ->expectedClosingFor((int) $this->counterId, $this->date);

        $this->showClosingForm = true;
    }

    public function updateClosingItem(int $index, float $actual): void
    {
        if (isset($this->closingItems[$index])) {
            $this->closingItems[$index]['actual'] = $actual;
            $this->closingItems[$index]['variance'] = $actual - $this->closingItems[$index]['expected'];
        }
    }

    public function saveClosingAmounts(): void
    {
        if (!$this->counterId) {
            session()->flash('error', 'กรุณาเลือกเคาน์เตอร์');
            return;
        }

        $workingDay = WorkingDay::where('counter_id', $this->counterId)
            ->whereDate('work_date', $this->date)
            ->where('status', 'open')
            ->first();

        if (!$workingDay) {
            session()->flash('error', 'ไม่พบวันทำการที่เปิดอยู่');
            return;
        }

        // Calculate closing balances
        $service = app(InventoryService::class);
        $service->closeDay((int) $this->counterId, $this->date);

        $expected = app(ThbCashService::class)
            ->expectedClosingFor((int) $this->counterId, $this->date);
        $actual = (float) $this->closingThbActual;

        // เก็บผลต่างไว้ก่อน ยังไม่โพสต์เป็น movement — รอ admin อนุมัติ ไม่งั้น
        // ยอดในระบบจะถูกแก้ไปแล้วทั้งที่การนับอาจถูกปฏิเสธ
        $workingDay->update([
            'status' => 'closed',
            'closed_by' => Auth::id(),
            'closed_at' => now(),
            'closing_items' => $this->closingItems,
            'closing_status' => 'pending',
            'closing_thb_expected' => $expected,
            'closing_thb_actual' => $actual,
            'closing_thb_variance' => $actual - $expected,
        ]);

        $this->showClosingForm = false;

        $variance = $actual - $expected;
        session()->flash('success', 'บันทึกยอดปิดสำเร็จ - รออนุมัติจาก Admin'
            . (abs($variance) >= 0.005
                ? ' (เงินบาท' . ($variance > 0 ? 'เกิน ' : 'ขาด ') . number_format(abs($variance), 2) . ' บาท)'
                : ''));
    }

    /**
     * เปิดวันทำการที่กดปิดผิดกลับมาใหม่
     *
     * ตอนกดปิดระบบแค่เก็บยอดที่นับได้ไว้รออนุมัติ ยังไม่โอนสต็อกและยังไม่โพสต์ผลต่าง
     * เงินบาท — สองอย่างนั้นอยู่ใน approveClosing การเปิดกลับจึงเป็นแค่การล้างยอดปิด
     * ที่กรอกค้างไว้ ไม่มี movement ไหนต้องกลับรายการ ตราบใดที่ยังไม่อนุมัติ
     */
    public function reopenDay(string $reason = ''): void
    {
        if (!$this->counterId) {
            session()->flash('error', 'กรุณาเลือกเคาน์เตอร์');
            return;
        }

        // จำกัดไว้ที่วันปัจจุบัน — หน้า buy/sell มองหาวันทำการของวันนี้เท่านั้น
        // การเปิดวันเก่ากลับมาจึงไม่ช่วยให้ทำรายการได้ มีแต่จะทำให้ยอดปิดที่ปิดไป
        // แล้วเปลี่ยนย้อนหลัง
        if ($this->date !== now()->format('Y-m-d')) {
            session()->flash('error', 'เปิดใหม่ได้เฉพาะวันทำการปัจจุบันเท่านั้น');
            return;
        }

        $workingDay = WorkingDay::where('counter_id', $this->counterId)
            ->whereDate('work_date', $this->date)
            ->first();

        if (!$workingDay) {
            session()->flash('error', 'ไม่พบวันทำการของวันที่เลือก');
            return;
        }

        if ($workingDay->isOpen()) {
            session()->flash('error', 'วันทำการนี้เปิดอยู่แล้ว');
            return;
        }

        if (!$workingDay->canReopen()) {
            session()->flash('error', 'ยอดปิดถูกอนุมัติไปแล้ว เปิดใหม่ไม่ได้ '
                . '— สต็อกถูกโอนไปคลังกลางและผลต่างเงินบาทถูกบันทึกแล้ว '
                . 'ถ้าต้องแก้ให้ใช้เมนูปรับปรุงสต็อก');
            return;
        }

        $reason = trim($reason);
        if ($reason === '') {
            session()->flash('error', 'กรุณาระบุเหตุผลที่เปิดวันทำการใหม่');
            return;
        }

        // ไม่เรียก InventoryService::openDay() ซ้ำ — เมธอดนั้นเขียน opening_balance
        // ด้วยสต็อกคงเหลือ ณ ตอนเรียก ถ้าเปิดใหม่หลังเทรดไปแล้วครึ่งวัน ยอดยกมาจะ
        // กลายเป็นสต็อกกลางวัน แถว Inventory ของวันนี้ปล่อยไว้ ตอนปิดรอบใหม่
        // closeDay() คำนวณทับให้เอง
        $workingDay->update([
            'status' => 'open',
            'closed_by' => null,
            'closed_at' => null,
            'closing_items' => null,
            'closing_status' => null,
            'closing_notes' => null,
            'closing_approved_by' => null,
            'closing_approved_at' => null,
            'closing_thb_expected' => null,
            'closing_thb_actual' => null,
            'closing_thb_variance' => null,
            'reopened_by' => Auth::id(),
            'reopened_at' => now(),
            'reopen_reason' => $this->appendReopenLog($workingDay, $reason),
        ]);

        // ล้าง state ของฟอร์มปิดวันที่ค้างอยู่ฝั่ง client
        $this->showClosingForm = false;
        $this->closingItems = [];
        $this->closingThbActual = 0;

        session()->flash('success', 'เปิดวันทำการใหม่สำเร็จ - ยอดปิดที่กรอกไว้ถูกยกเลิก '
            . 'ทำรายการต่อได้เลย');
    }

    /**
     * ต่อบรรทัด log ท้ายของเดิม — working_days เก็บได้แถวเดียวต่อวัน
     * (unique counter_id + work_date) ถ้าเปิด-ปิดซ้ำหลายรอบต้องเห็นครบทุกรอบ
     */
    private function appendReopenLog(WorkingDay $workingDay, string $reason): string
    {
        $discarded = 'ยกเลิกยอดปิด: บาทนับได้ '
            . number_format((float) $workingDay->closing_thb_actual, 2)
            . ' (ตามระบบ ' . number_format((float) $workingDay->closing_thb_expected, 2) . ')'
            . ', ' . count($workingDay->closing_items ?? []) . ' รายการสกุลเงิน';

        $line = now()->format('d/m/Y H:i')
            . ' | ' . (Auth::user()?->name ?? '-')
            . ' | ' . $reason
            . ' | ' . $discarded;

        return trim(($workingDay->reopen_reason ? $workingDay->reopen_reason . "\n" : '') . $line);
    }

    public function approveClosing(int $workingDayId): void
    {
        if (!Auth::user()->isAdmin()) {
            session()->flash('error', 'เฉพาะ Admin เท่านั้นที่อนุมัติได้');
            return;
        }

        $workingDay = WorkingDay::find($workingDayId);
        if (!$workingDay) {
            session()->flash('error', 'ไม่พบรายการ');
            return;
        }

        // Transfer stock to HQ
        $this->transferStockToHQ($workingDay);

        // โพสต์ผลต่างเงินบาทตอนนี้ — ยอดยกมาวันถัดไปจึงเท่ากับเงินที่นับได้จริง
        // เงินบาทไม่ถูกโอนไปคลังอัตโนมัติ ต่างจากเงินตราต่างประเทศ เพราะ
        // เคาน์เตอร์ต้องเก็บเงินทุนหมุนข้ามคืน ถ้าจะส่งคืนให้ใช้เมนูส่งคืนคลัง
        $variance = app(ThbCashService::class)->postClosingVariance($workingDay, Auth::id());

        // Approve
        $workingDay->update([
            'closing_status' => 'approved',
            'closing_approved_by' => Auth::id(),
            'closing_approved_at' => now(),
        ]);

        session()->flash('success', 'อนุมัติยอดปิดสำเร็จ - โอนสต็อกไปส่วนกลางแล้ว'
            . ($variance ? ' และปรับปรุงผลต่างเงินบาท ' . number_format((float) $variance->amount, 2) . ' บาท' : ''));
    }

    public function rejectClosing(int $workingDayId, string $reason): void
    {
        if (!Auth::user()->isAdmin()) {
            session()->flash('error', 'เฉพาะ Admin เท่านั้นที่ปฏิเสธได้');
            return;
        }

        $workingDay = WorkingDay::find($workingDayId);
        if (!$workingDay) {
            session()->flash('error', 'ไม่พบรายการ');
            return;
        }

        $workingDay->update([
            'closing_status' => 'rejected',
            'closing_notes' => $reason,
            'closing_approved_by' => Auth::id(),
            'closing_approved_at' => now(),
        ]);

        session()->flash('error', 'ปฏิเสธยอดปิด - ' . $reason);
    }

    protected function transferStockToHQ(WorkingDay $workingDay): void
    {
        // Find HQ counter (assume counter with code 'HQ' or branch HQ)
        $hqCounter = Counter::where('counter_code', 'like', '%HQ%')
            ->orWhereHas('branch', function ($q) {
                $q->where('branch_type', 'HQ');
            })
            ->first();

        if (!$hqCounter) {
            // If no HQ counter found, skip transfer
            return;
        }

        $inventoryService = app(InventoryService::class);
        $fromCounter = (int) $workingDay->counter_id;
        $toCounter = (int) $hqCounter->id;

        // Transfer each denomination based on actual amounts
        foreach ($workingDay->closing_items ?? [] as $item) {
            if ($item['actual'] <= 0) continue;

            $inventoryService->transferStock(
                $fromCounter,
                $toCounter,
                $item['denomination_id'],
                $item['actual'],
                Auth::id(),
                'ส่งมอบยอดปิดวัน ' . $workingDay->work_date->format('d/m/Y')
            );
        }
    }

    public function render()
    {
        return view('livewire.inventory.open-close-day');
    }
}
