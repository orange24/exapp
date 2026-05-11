<?php

namespace App\Livewire\Report;

use App\Models\BotReportMaster;
use App\Models\BotReportTransaction;
use App\Models\Branch;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class BotReportManager extends Component
{
    use WithPagination;

    // Generate form
    public string $genMonth = '';
    public string $genYear = '';
    public string $genBranchId = '';

    // Current report being viewed/edited
    public ?int $activeReportId = null;
    public string $filterType = ''; // BUY or SELL

    // Editing
    public ?int $editingRowId = null;
    public array $editForm = [];

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $prev = now()->subMonth();
        $this->genMonth = $prev->format('m');
        $this->genYear = $prev->format('Y');
    }

    public function getBranchesProperty()
    {
        return Branch::where('is_active', true)->orderBy('branch_name')->get();
    }

    public function getReportsProperty()
    {
        return BotReportMaster::with(['branch', 'generatedByUser'])
            ->orderByDesc('report_month')
            ->get();
    }

    public function getActiveReportProperty()
    {
        return $this->activeReportId ? BotReportMaster::find($this->activeReportId) : null;
    }

    public function getTransactionsProperty()
    {
        if (!$this->activeReportId) return collect();

        $query = BotReportTransaction::where('bot_report_id', $this->activeReportId)
            ->orderBy('trns_date')->orderBy('id');

        if ($this->filterType) {
            $query->where('trns_type', $this->filterType);
        }

        return $query->paginate(50);
    }

    /**
     * Clone transactions from source into bot_report_transactions.
     */
    public function generate(): void
    {
        if (!$this->genMonth || !$this->genYear) {
            session()->flash('error', 'กรุณาเลือกเดือนและปี');
            return;
        }

        $reportMonth = $this->genYear . '-' . str_pad($this->genMonth, 2, '0', STR_PAD_LEFT);
        $branchId = $this->genBranchId ?: null;

        // Check if already exists
        $existing = BotReportMaster::where('report_month', $reportMonth)
            ->where('branch_id', $branchId)
            ->first();

        if ($existing) {
            // Already exists — switch to sync mode
            $this->activeReportId = $existing->id;
            $this->syncNewTransactions($existing);
            return;
        }

        $dateStart = Carbon::create($this->genYear, $this->genMonth, 1)->startOfMonth();
        $dateEnd = $dateStart->copy()->endOfMonth();

        $branch = $branchId ? Branch::find($branchId) : null;

        $count = 0;
        DB::transaction(function () use ($reportMonth, $branchId, $branch, $dateStart, $dateEnd, &$count) {
            $master = BotReportMaster::create([
                'report_month' => $reportMonth,
                'branch_id' => $branchId,
                'institution_code' => Setting::get('BOT_INSTITUTION_CODE', ''),
                'license_no' => Setting::get('BOT_LICENSE_NO', ''),
                'company_name' => Setting::get('BOT_COMPANY_NAME', Setting::get('COMPANY_NAME', '')),
                'branch_name' => $branch?->branch_name ?? '',
                'branch_address' => $branch?->address ?? '',
                'status' => 'draft',
                'generated_by' => Auth::id(),
                'generated_at' => now(),
            ]);

            $count = $this->cloneTransactions($master, $dateStart, $dateEnd);
            $this->activeReportId = $master->id;
        });

        session()->flash('success', "สร้างรายงานสำเร็จ — clone {$count} รายการ");
    }

    /**
     * Sync: ดึงเฉพาะรายการใหม่ที่ยังไม่มีใน staging (ไม่กระทบรายการที่แก้ไขแล้ว)
     */
    public function syncNewTransactions(?BotReportMaster $report = null): void
    {
        $report = $report ?? ($this->activeReportId ? BotReportMaster::find($this->activeReportId) : null);
        if (!$report) return;

        if ($report->status !== 'draft') {
            session()->flash('error', 'รายงานที่ยืนยันแล้วไม่สามารถ sync ได้');
            return;
        }

        $month = (int) substr($report->report_month, 5, 2);
        $year = (int) substr($report->report_month, 0, 4);
        $dateStart = Carbon::create($year, $month, 1)->startOfMonth();
        $dateEnd = $dateStart->copy()->endOfMonth();

        $count = $this->cloneTransactions($report, $dateStart, $dateEnd);

        if ($count > 0) {
            session()->flash('success', "ดึงข้อมูลใหม่เพิ่ม {$count} รายการ (รายการเดิมไม่ถูกกระทบ)");
        } else {
            session()->flash('success', 'ไม่มีรายการใหม่ — ข้อมูลครบแล้ว');
        }
    }

    /**
     * Clone source transactions into staging, skipping already-existing detail_ids.
     */
    private function cloneTransactions(BotReportMaster $master, Carbon $dateStart, Carbon $dateEnd): int
    {
        // Get detail_ids that are already in staging
        $existingDetailIds = BotReportTransaction::where('bot_report_id', $master->id)
            ->whereNotNull('source_detail_id')
            ->pluck('source_detail_id')
            ->toArray();

        $rows = DB::table('transactions_master')
            ->join('transactions_detail', 'transactions_master.id', '=', 'transactions_detail.transaction_id')
            ->leftJoin('customers', 'transactions_master.customer_id', '=', 'customers.id')
            ->leftJoin('counters', 'transactions_master.counter_id', '=', 'counters.id')
            ->where('transactions_master.flag_cancel', 'N')
            ->whereDate('transactions_master.trns_datetime', '>=', $dateStart)
            ->whereDate('transactions_master.trns_datetime', '<=', $dateEnd)
            ->when($master->branch_id, fn($q) => $q->where('counters.branch_id', $master->branch_id))
            ->when(!empty($existingDetailIds), fn($q) => $q->whereNotIn('transactions_detail.id', $existingDetailIds))
            ->select(
                'transactions_master.id as master_id',
                'transactions_detail.id as detail_id',
                'transactions_master.trns_type',
                'transactions_master.trns_datetime',
                'transactions_master.cust_name',
                'customers.id_type',
                'customers.id_number',
                'customers.nationality',
                'transactions_detail.currency_code',
                'transactions_detail.unit_price',
                'transactions_detail.amount',
                'transactions_detail.total'
            )
            ->orderBy('transactions_master.trns_datetime')
            ->get();

        foreach ($rows as $row) {
            $isBuy = $row->trns_type === 'BUYING';
            BotReportTransaction::create([
                'bot_report_id' => $master->id,
                'source_transaction_id' => $row->master_id,
                'source_detail_id' => $row->detail_id,
                'trns_type' => $isBuy ? 'BUY' : 'SELL',
                'trns_date' => Carbon::parse($row->trns_datetime)->toDateString(),
                'customer_type' => $this->mapCustomerType($row->id_type),
                'customer_name' => $row->cust_name ?? '',
                'id_type_code' => $this->mapIdTypeCode($row->id_type),
                'id_number' => $row->id_number ?? '',
                'nationality' => $row->nationality ?? '',
                'purpose' => 'เดินทาง/ท่องเที่ยว',
                'fx_point' => 'สถานประกอบการ',
                'fx_channel' => '0753600001',
                'currency_code' => $row->currency_code,
                'exchange_rate' => (float) $row->unit_price,
                'fx_amount' => (float) $row->amount,
                'thb_point' => 'สถานประกอบการ',
                'thb_channel' => '0753600001',
                'thb_amount' => (float) $row->total,
            ]);
        }

        return $rows->count();
    }

    public function viewReport(int $id): void
    {
        $this->activeReportId = $id;
        $this->editingRowId = null;
        $this->resetPage();
    }

    public function backToList(): void
    {
        $this->activeReportId = null;
        $this->editingRowId = null;
    }

    public function startEdit(int $rowId): void
    {
        $row = BotReportTransaction::findOrFail($rowId);
        $this->editingRowId = $rowId;
        $this->editForm = [
            'trns_date' => $row->trns_date->format('Y-m-d'),
            'customer_type' => $row->customer_type,
            'customer_name' => $row->customer_name,
            'id_type_code' => $row->id_type_code,
            'id_number' => $row->id_number,
            'nationality' => $row->nationality,
            'purpose' => $row->purpose,
            'currency_code' => $row->currency_code,
            'exchange_rate' => (string) $row->exchange_rate,
            'fx_amount' => (string) $row->fx_amount,
            'thb_amount' => (string) $row->thb_amount,
            'remark' => $row->remark ?? '',
        ];
    }

    public function saveEdit(): void
    {
        $row = BotReportTransaction::findOrFail($this->editingRowId);
        $row->update($this->editForm);
        $this->editingRowId = null;
        $this->editForm = [];
    }

    public function cancelEdit(): void
    {
        $this->editingRowId = null;
        $this->editForm = [];
    }

    public function deleteRow(int $rowId): void
    {
        BotReportTransaction::where('id', $rowId)
            ->where('bot_report_id', $this->activeReportId)
            ->delete();
    }

    public function confirmReport(int $id): void
    {
        $report = BotReportMaster::findOrFail($id);
        $report->update([
            'status' => 'confirmed',
            'confirmed_by' => Auth::id(),
            'confirmed_at' => now(),
        ]);
        session()->flash('success', 'ยืนยันรายงานแล้ว — พร้อมส่งออก Excel');
    }

    public function deleteReport(int $id): void
    {
        BotReportMaster::findOrFail($id)->delete();
        $this->activeReportId = null;
        session()->flash('success', 'ลบรายงานสำเร็จ');
    }

    private function mapCustomerType(?string $idType): string
    {
        return match ($idType) {
            'national_id' => 'คนไทย',
            'passport' => 'ชาวต่างชาติ',
            'corporate_id' => 'นิติบุคคลไทย',
            default => 'ชาวต่างชาติ',
        };
    }

    private function mapIdTypeCode(?string $idType): string
    {
        return match ($idType) {
            'national_id' => '324001',
            'passport' => '324002',
            'corporate_id' => '324004',
            default => '324002',
        };
    }

    public function render()
    {
        return view('livewire.report.bot-report-manager');
    }
}
