<?php

namespace App\Livewire\Accounting;

use App\Models\Account;
use App\Models\JournalLine;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class GeneralLedger extends Component
{
    public string $dateFrom = '';
    public string $dateTo = '';
    public string $accountId = '';
    public string $viewMode = 'trial_balance'; // trial_balance or ledger

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->format('Y-m-d');
        $this->dateTo = now()->format('Y-m-d');
    }

    public function getAccountsProperty()
    {
        return Account::where('is_active', true)->orderBy('account_code')->get();
    }

    public function getTrialBalanceProperty()
    {
        if ($this->viewMode !== 'trial_balance') return collect();

        return JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->join('accounts', 'journal_lines.account_id', '=', 'accounts.id')
            ->where('journal_entries.is_posted', true)
            ->when($this->dateFrom, fn($q) => $q->whereDate('journal_entries.entry_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('journal_entries.entry_date', '<=', $this->dateTo))
            ->select(
                'accounts.id',
                'accounts.account_code',
                'accounts.name_th',
                'accounts.type',
                DB::raw('SUM(journal_lines.debit) as total_debit'),
                DB::raw('SUM(journal_lines.credit) as total_credit'),
                DB::raw('SUM(journal_lines.debit) - SUM(journal_lines.credit) as balance')
            )
            ->groupBy('accounts.id', 'accounts.account_code', 'accounts.name_th', 'accounts.type')
            ->orderBy('accounts.account_code')
            ->get();
    }

    public function getLedgerEntriesProperty()
    {
        if ($this->viewMode !== 'ledger' || !$this->accountId) return collect();

        return JournalLine::join('journal_entries', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_lines.account_id', $this->accountId)
            ->where('journal_entries.is_posted', true)
            ->when($this->dateFrom, fn($q) => $q->whereDate('journal_entries.entry_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn($q) => $q->whereDate('journal_entries.entry_date', '<=', $this->dateTo))
            ->select(
                'journal_entries.entry_no',
                'journal_entries.entry_date',
                'journal_entries.description as entry_desc',
                'journal_lines.description as line_desc',
                'journal_lines.debit',
                'journal_lines.credit'
            )
            ->orderBy('journal_entries.entry_date')
            ->orderBy('journal_entries.id')
            ->get();
    }

    public function render()
    {
        return view('livewire.accounting.general-ledger');
    }
}
