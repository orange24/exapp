<?php

namespace Tests\Feature\Sanction;

use App\Livewire\NotificationBell;
use App\Livewire\NotificationCenter;
use App\Models\SanctionScreening;
use App\Notifications\SanctionApprovedDespiteMatch;
use App\Notifications\SanctionSyncFailed;
use App\Models\SanctionSyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class NotificationUiTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    public function test_center_renders_with_notifications(): void
    {
        $screening = SanctionScreening::create([
            'customer_id' => null,
            'transaction_id' => null,
            'branch_id' => $this->branch->id,
            'screened_by' => $this->staffUser->id,
            'screened_at' => now(),
            'input_name' => 'AMRAN MING',
            'trigger' => SanctionScreening::TRIGGER_TRANSACTION,
            'result' => SanctionScreening::RESULT_POTENTIAL_MATCH,
            'top_score' => 92.5,
            'decision' => SanctionScreening::DECISION_TRUE_MATCH,
            'decided_by' => $this->adminUser->id,
            'decided_at' => now(),
            'decision_reason' => 'ตรวจพาสปอร์ตเล่มจริงแล้ว เป็นบุคคลเดียวกันจริง',
        ]);

        $run = SanctionSyncRun::create([
            'list_code' => 'freeze_05_th',
            'status' => SanctionSyncRun::STATUS_FAILED, 'source_adapter' => 'amlo_html',
            'started_at' => now(),
            'finished_at' => now(),
            'error_message' => 'ต้นทางไม่ตอบสนอง',
        ]);

        $this->adminUser->notify(new SanctionApprovedDespiteMatch($screening->fresh()));
        $this->adminUser->notify(new SanctionSyncFailed($run));

        Livewire::actingAs($this->adminUser)
            ->test(NotificationCenter::class)
            ->assertSee('ยืนยันว่าเป็นบุคคลเดียวกัน', false)
                        ->assertSee('ต้นทางไม่ตอบสนอง')
            ->call('setFilter', 'sanction')
            ->assertSee('ยืนยันว่าเป็นบุคคลเดียวกัน', false)
            ->call('setFilter', 'system')
            ->assertDontSee('ยืนยันว่าเป็นบุคคลเดียวกัน', false)
            ->call('setFilter', 'unread')
            ->call('markAllAsRead')
            ->assertHasNoErrors();

        $this->assertSame(0, $this->adminUser->fresh()->unreadNotifications()->count());
    }

    public function test_bell_renders_badge(): void
    {
        $run = SanctionSyncRun::create([
            'list_code' => 'freeze_05_th',
            'status' => SanctionSyncRun::STATUS_FAILED, 'source_adapter' => 'amlo_html',
            'started_at' => now(),
            'finished_at' => now(),
            'error_message' => 'x',
        ]);
        $this->adminUser->notify(new SanctionSyncFailed($run));

        Livewire::actingAs($this->adminUser)
            ->test(NotificationBell::class)
            ->assertSee('1');
    }

    public function test_notifications_page_loads(): void
    {
        $this->actingAsAdmin()->get('/notifications')->assertOk()->assertSee('การแจ้งเตือน');
    }

    public function test_transaction_link_points_at_the_real_route(): void
    {
        \DB::table('notifications')->insert([
            'id' => (string) \Str::uuid(),
            'type' => \App\Notifications\SanctionTransactionBlocked::class,
            'notifiable_type' => \App\Models\User::class,
            'notifiable_id' => $this->adminUser->id,
            'data' => json_encode([
                'category' => 'sanction',
                'severity' => 'blocked',
                'title' => 'ธุรกรรมถูกระงับ',
                'transaction_id' => 4321,
                'link_route' => 'sanctions.review',
                'link_params' => ['screening' => 7],
            ], JSON_UNESCAPED_UNICODE),
            'read_at' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::actingAs($this->adminUser)
            ->test(NotificationCenter::class)
            ->assertSee(route('admin.transactions'))
            ->assertSee('ธุรกรรม #4321', false);
    }
}