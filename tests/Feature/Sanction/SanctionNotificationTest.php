<?php

namespace Tests\Feature\Sanction;

use App\Models\Branch;
use App\Models\Role;
use App\Models\SanctionEntry;
use App\Models\SanctionEntryIdentifier;
use App\Models\SanctionEntryName;
use App\Models\SanctionScreening;
use App\Models\SanctionSyncRun;
use App\Models\User;
use App\Notifications\SanctionApprovedDespiteMatch;
use App\Notifications\SanctionSyncFailed;
use App\Notifications\SanctionTransactionBlocked;
use App\Services\Sanction\NameNormalizer;
use App\Services\Sanction\SanctionNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class SanctionNotificationTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    private Branch $otherBranch;
    private User $managerHere;
    private User $managerElsewhere;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();

        $this->otherBranch = Branch::create([
            'branch_code' => 'HKT-01',
            'branch_name' => 'Phuket',
            'type' => 'branch',
            'city' => 'Phuket',
            'is_active' => true,
        ]);

        $bmRole = Role::where('name', 'branch_manager')->first();

        $this->managerHere = User::create([
            'name' => 'Manager Here', 'email' => 'here@test.local',
            'password' => bcrypt('x'), 'role_id' => $bmRole->id, 'branch_id' => $this->branch->id,
        ]);
        $this->managerHere->branches()->attach($this->branch->id);

        $this->managerElsewhere = User::create([
            'name' => 'Manager Elsewhere', 'email' => 'far@test.local',
            'password' => bcrypt('x'), 'role_id' => $bmRole->id, 'branch_id' => $this->otherBranch->id,
        ]);
        $this->managerElsewhere->branches()->attach($this->otherBranch->id);
    }

    private function blockedScreening(): SanctionScreening
    {
        $entry = SanctionEntry::create([
            'list_code' => SanctionEntry::LIST_FREEZE_05_TH,
            'source_ref' => '1', 'name_en' => 'AMRAN MING', 'nationality' => 'TH',
            'national_id' => '5960500028101', 'status' => 'Designated person',
            'content_hash' => hash('sha256', 'x'), 'first_seen_at' => now(), 'last_seen_at' => now(),
        ]);

        SanctionEntryName::create([
            'sanction_entry_id' => $entry->id, 'name_raw' => 'AMRAN MING',
            'name_normalized' => NameNormalizer::normalize('AMRAN MING'),
            'name_soundex' => NameNormalizer::soundexOf('AMRAN MING'),
            'script' => 'latin', 'is_primary' => true,
        ]);

        SanctionEntryIdentifier::create([
            'sanction_entry_id' => $entry->id, 'type' => 'national_id',
            'value_raw' => '5960500028101', 'value_normalized' => '5960500028101',
        ]);

        return app(\App\Services\Sanction\SanctionScreeningService::class)->screen(
            input: new \App\Services\Sanction\Dto\ScreeningInput(
                name: 'AMRAN MING', idType: 'national_id', idNumber: '5960500028101'
            ),
            trigger: SanctionScreening::TRIGGER_TRANSACTION,
            screenedBy: $this->staffUser->id,
            branchId: $this->branch->id,
        );
    }

    public function test_blocked_transaction_notifies_admins(): void
    {
        Notification::fake();

        app(SanctionNotifier::class)->transactionBlocked($this->blockedScreening());

        Notification::assertSentTo($this->adminUser, SanctionTransactionBlocked::class);
    }

    public function test_blocked_transaction_notifies_manager_of_that_branch_only(): void
    {
        Notification::fake();

        app(SanctionNotifier::class)->transactionBlocked($this->blockedScreening());

        Notification::assertSentTo($this->managerHere, SanctionTransactionBlocked::class);
        Notification::assertNotSentTo($this->managerElsewhere, SanctionTransactionBlocked::class);
    }

    public function test_branch_membership_comes_from_user_branches_not_the_branch_id_column(): void
    {
        Notification::fake();

        // ผู้ใช้ที่ branch_id ชี้ไปสาขาอื่น แต่ถูกผูกกับสาขานี้ผ่าน user_branches
        $bmRole = Role::where('name', 'branch_manager')->first();

        $crossBranch = User::create([
            'name' => 'Cross Branch', 'email' => 'cross@test.local',
            'password' => bcrypt('x'), 'role_id' => $bmRole->id,
            'branch_id' => $this->otherBranch->id,
        ]);
        $crossBranch->branches()->attach($this->branch->id);

        app(SanctionNotifier::class)->transactionBlocked($this->blockedScreening());

        Notification::assertSentTo($crossBranch, SanctionTransactionBlocked::class);
    }

    public function test_staff_are_never_notified(): void
    {
        Notification::fake();

        app(SanctionNotifier::class)->transactionBlocked($this->blockedScreening());

        Notification::assertNotSentTo($this->staffUser, SanctionTransactionBlocked::class);
    }

    public function test_approval_notifies_admins(): void
    {
        Notification::fake();

        $screening = $this->blockedScreening();
        $screening->update([
            'result' => SanctionScreening::RESULT_POTENTIAL_MATCH,
            'decision' => SanctionScreening::DECISION_FALSE_POSITIVE,
            'decided_by' => $this->managerHere->id,
            'decided_at' => now(),
            'decision_reason' => 'ตรวจพาสปอร์ตเล่มจริงแล้ว คนละคน วันเกิดต่างกัน 12 ปี',
        ]);

        app(SanctionNotifier::class)->approvedDespiteMatch($screening->fresh());

        Notification::assertSentTo($this->adminUser, SanctionApprovedDespiteMatch::class);
    }

    public function test_sync_failure_notifies_admins_only_not_branch_managers(): void
    {
        Notification::fake();

        $run = SanctionSyncRun::create([
            'list_code' => SanctionEntry::LIST_FREEZE_04_UN,
            'source_adapter' => 'amlo_public_scraper',
            'status' => SanctionSyncRun::STATUS_ABORTED_SANITY_CHECK,
            'started_at' => now(), 'finished_at' => now(),
            'error_message' => 'parse ได้ 12 จาก 605 ราย',
        ]);

        app(SanctionNotifier::class)->syncFailed($run);

        Notification::assertSentTo($this->adminUser, SanctionSyncFailed::class);
        Notification::assertNotSentTo($this->managerHere, SanctionSyncFailed::class);
    }

    public function test_notification_lands_in_the_database(): void
    {
        // ไม่เรียก notifier ซ้ำเอง — screen() ที่บล็อกต้องส่งให้แล้วหนึ่งใบ
        // ถ้าเรียกซ้ำจะได้สองใบ แล้วเทสนี้จะพิสูจน์ไม่ได้ว่าส่ง "ใบเดียว"
        // ซึ่งเป็นสิ่งที่ต้องกัน: กระดิ่งที่ขึ้นเลขซ้ำซ้อนจะถูกคนเลิกเชื่อถือ
        $this->blockedScreening();

        $this->assertGreaterThan(0, $this->adminUser->notifications()->count());
        $this->assertSame(1, $this->adminUser->unreadNotifications()->count());
    }
}
