<?php

namespace Tests\Feature\Accounting;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class JournalEntryTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Account $cashAccount;
    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $this->cashAccount = Account::create([
            'account_code' => '1100',
            'name_th' => 'เงินสด',
            'name_en' => 'Cash',
            'type' => 'asset',
            'level' => 1,
            'is_active' => true,
        ]);

        $this->revenueAccount = Account::create([
            'account_code' => '4100',
            'name_th' => 'รายได้',
            'name_en' => 'Revenue',
            'type' => 'revenue',
            'level' => 1,
            'is_active' => true,
        ]);
    }

    public function test_can_create_journal_entry(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Accounting\JournalEntryManager::class)
            ->set('entryDate', '2026-05-03')
            ->set('description', 'ทดสอบบันทึกรายการ')
            ->set('branchId', $this->branch->id)
            ->set('lines', [
                ['account_id' => $this->cashAccount->id, 'debit' => '1000', 'credit' => '', 'description' => ''],
                ['account_id' => $this->revenueAccount->id, 'debit' => '', 'credit' => '1000', 'description' => ''],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $entry = JournalEntry::first();
        $this->assertNotNull($entry);
        $this->assertEquals('manual', $entry->type);
        $this->assertFalse($entry->is_posted);
        $this->assertCount(2, $entry->lines);
    }

    public function test_journal_entry_requires_balanced_debits_credits(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Accounting\JournalEntryManager::class)
            ->set('entryDate', '2026-05-03')
            ->set('description', 'ไม่สมดุล')
            ->set('branchId', $this->branch->id)
            ->set('lines', [
                ['account_id' => $this->cashAccount->id, 'debit' => '1000', 'credit' => '', 'description' => ''],
                ['account_id' => $this->revenueAccount->id, 'debit' => '', 'credit' => '500', 'description' => ''],
            ])
            ->call('save')
            ->assertHasErrors('lines');
    }

    public function test_can_post_journal_entry(): void
    {
        $entry = JournalEntry::create([
            'entry_no' => 'G20260503-0001',
            'entry_date' => '2026-05-03',
            'description' => 'Test',
            'type' => 'manual',
            'branch_id' => $this->branch->id,
            'posted_by' => $this->adminUser->id,
            'is_posted' => false,
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Accounting\JournalEntryManager::class)
            ->call('postEntry', $entry->id);

        $this->assertTrue($entry->fresh()->is_posted);
    }

    public function test_entry_number_auto_generated(): void
    {
        Livewire::actingAs($this->adminUser)
            ->test(\App\Livewire\Accounting\JournalEntryManager::class)
            ->set('entryDate', '2026-05-03')
            ->set('description', 'Test auto number')
            ->set('branchId', $this->branch->id)
            ->set('lines', [
                ['account_id' => $this->cashAccount->id, 'debit' => '500', 'credit' => '', 'description' => ''],
                ['account_id' => $this->revenueAccount->id, 'debit' => '', 'credit' => '500', 'description' => ''],
            ])
            ->call('save');

        $entry = JournalEntry::first();
        $this->assertStringStartsWith('G', $entry->entry_no);
        $this->assertStringContainsString('-', $entry->entry_no);
    }
}
