<?php

namespace App\Livewire\Accounting;

use App\Models\Account;
use App\Models\Branch;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class JournalEntryManager extends Component
{
    use WithPagination;

    // Form
    public bool $showForm = false;
    public string $entryDate = '';
    public string $description = '';
    public string $branchId = '';
    public array $lines = [];

    // View detail
    public bool $showDetail = false;
    public ?int $detailId = null;

    // Filters
    public string $filterDateFrom = '';
    public string $filterDateTo = '';
    public string $filterType = '';
    public string $filterPosted = '';

    protected $paginationTheme = 'tailwind';

    public function mount(): void
    {
        $this->filterDateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->filterDateTo = now()->format('Y-m-d');
        $this->entryDate = now()->format('Y-m-d');
        $this->branchId = (string) (Auth::user()->branch_id ?? '');
        $this->addLine();
        $this->addLine();
    }

    public function getBranchesProperty()
    {
        return Branch::where('is_active', true)->orderBy('branch_name')->get();
    }

    public function getAccountsProperty()
    {
        return Account::where('is_active', true)->orderBy('account_code')->get();
    }

    public function getEntriesProperty()
    {
        $query = JournalEntry::with(['branch', 'postedBy', 'lines.account'])
            ->orderByDesc('entry_date')
            ->orderByDesc('id');

        if ($this->filterDateFrom) {
            $query->whereDate('entry_date', '>=', $this->filterDateFrom);
        }
        if ($this->filterDateTo) {
            $query->whereDate('entry_date', '<=', $this->filterDateTo);
        }
        if ($this->filterType) {
            $query->where('type', $this->filterType);
        }
        if ($this->filterPosted !== '') {
            $query->where('is_posted', $this->filterPosted === '1');
        }

        if (!Auth::user()->isAdmin()) {
            $query->where('branch_id', Auth::user()->branch_id);
        }

        return $query->paginate(20);
    }

    public function addLine(): void
    {
        $this->lines[] = ['account_id' => '', 'debit' => '', 'credit' => '', 'description' => ''];
    }

    public function removeLine(int $index): void
    {
        if (count($this->lines) > 2) {
            unset($this->lines[$index]);
            $this->lines = array_values($this->lines);
        }
    }

    public function save(): void
    {
        $this->validate([
            'entryDate' => 'required|date',
            'description' => 'required|min:3',
            'branchId' => 'required|exists:branches,id',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
        ], [
            'description.required' => 'กรุณาระบุคำอธิบาย',
            'branchId.required' => 'กรุณาเลือกสาขา',
            'lines.*.account_id.required' => 'กรุณาเลือกบัญชี',
        ]);

        // Validate debit = credit
        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($this->lines as $line) {
            $totalDebit += (float) ($line['debit'] ?: 0);
            $totalCredit += (float) ($line['credit'] ?: 0);
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            $this->addError('lines', 'ยอดเดบิตและเครดิตไม่เท่ากัน (เดบิต: ' . number_format($totalDebit, 2) . ', เครดิต: ' . number_format($totalCredit, 2) . ')');
            return;
        }

        if ($totalDebit == 0) {
            $this->addError('lines', 'กรุณาระบุจำนวนเงินอย่างน้อย 1 รายการ');
            return;
        }

        DB::transaction(function () {
            $entryNo = $this->generateEntryNo();

            $entry = JournalEntry::create([
                'entry_no' => $entryNo,
                'entry_date' => $this->entryDate,
                'description' => $this->description,
                'type' => 'manual',
                'branch_id' => $this->branchId,
                'posted_by' => Auth::id(),
                'is_posted' => false,
            ]);

            foreach ($this->lines as $line) {
                $debit = (float) ($line['debit'] ?: 0);
                $credit = (float) ($line['credit'] ?: 0);
                if ($debit == 0 && $credit == 0) continue;

                JournalLine::create([
                    'journal_entry_id' => $entry->id,
                    'account_id' => $line['account_id'],
                    'debit' => $debit,
                    'credit' => $credit,
                    'description' => $line['description'] ?? '',
                ]);
            }
        });

        $this->resetForm();
        session()->flash('success', 'บันทึกรายการบัญชีสำเร็จ');
    }

    public function postEntry(int $id): void
    {
        $entry = JournalEntry::findOrFail($id);
        $entry->update([
            'is_posted' => true,
            'posted_by' => Auth::id(),
        ]);
        session()->flash('success', 'ผ่านรายการสำเร็จ (Posted)');
    }

    public function viewDetail(int $id): void
    {
        $this->detailId = $id;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->detailId = null;
    }

    public function getDetailEntryProperty()
    {
        if (!$this->detailId) return null;
        return JournalEntry::with(['branch', 'postedBy', 'lines.account'])->find($this->detailId);
    }

    private function generateEntryNo(): string
    {
        $prefix = 'G' . now()->format('Ymd') . '-';
        $last = JournalEntry::where('entry_no', 'like', $prefix . '%')
            ->orderByDesc('entry_no')
            ->value('entry_no');

        $seq = $last ? ((int) substr($last, strlen($prefix)) + 1) : 1;
        return $prefix . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }

    private function resetForm(): void
    {
        $this->showForm = false;
        $this->entryDate = now()->format('Y-m-d');
        $this->description = '';
        $this->lines = [];
        $this->addLine();
        $this->addLine();
    }

    public function render()
    {
        return view('livewire.accounting.journal-entry-manager');
    }
}
