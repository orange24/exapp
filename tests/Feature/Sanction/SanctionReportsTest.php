<?php

namespace Tests\Feature\Sanction;

use App\Models\SanctionEntry;
use App\Models\SanctionScreening;
use App\Models\SanctionSyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class SanctionReportsTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    private function screening(array $attributes = []): SanctionScreening
    {
        return SanctionScreening::create(array_merge([
            'branch_id' => $this->branch->id,
            'screened_by' => $this->staffUser->id,
            'screened_at' => now(),
            'input_name' => 'AMRAN MING',
            'input_id_number' => 'AA123456',
            'trigger' => SanctionScreening::TRIGGER_TRANSACTION,
            'result' => SanctionScreening::RESULT_POTENTIAL_MATCH,
            'top_score' => 88,
        ], $attributes));
    }

    public function test_screening_log_page_loads_and_shows_the_entry(): void
    {
        $this->screening();

        $this->actingAs($this->adminUser)
            ->get(route('reports.sanction-screening-log'))
            ->assertOk()
            ->assertSee('AMRAN MING');
    }

    public function test_screening_log_export_returns_a_spreadsheet(): void
    {
        $this->screening();

        $response = $this->actingAs($this->adminUser)
            ->post(route('reports.sanction-screening-log.export'), [
                'date_from' => now()->startOfMonth()->format('Y-m-d'),
                'date_to' => now()->format('Y-m-d'),
            ]);

        $response->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('content-type'));
    }

    public function test_decision_report_shows_only_decided_screenings(): void
    {
        $this->screening();   // ยังไม่ตัดสิน
        $this->screening([
            'input_name' => 'DECIDED PERSON',
            'decision' => SanctionScreening::DECISION_FALSE_POSITIVE,
            'decided_by' => $this->adminUser->id,
            'decided_at' => now(),
            'decision_reason' => 'ตรวจพาสปอร์ตเล่มจริงแล้ว คนละคน วันเกิดต่างกัน 12 ปี',
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('reports.sanction-decisions'))
            ->assertOk()
            ->assertSee('DECIDED PERSON')
            ->assertDontSee('AMRAN MING');
    }

    public function test_sync_health_page_loads(): void
    {
        SanctionSyncRun::create([
            'list_code' => SanctionEntry::LIST_FREEZE_05_TH,
            'source_adapter' => 'amlo_public_scraper',
            'status' => SanctionSyncRun::STATUS_SUCCESS,
            'started_at' => now()->subHour(), 'finished_at' => now()->subHour(),
            'source_as_of' => '2026-10-01', 'entries_parsed' => 401,
        ]);

        $this->actingAs($this->adminUser)
            ->get(route('reports.sanction-sync-health'))
            ->assertOk()
            ->assertSee('freeze_05_th');
    }

    public function test_list_delta_page_loads(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('reports.sanction-list-delta'))
            ->assertOk();
    }

    public function test_rescan_report_page_loads(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('reports.sanction-rescan'))
            ->assertOk();
    }

    public function test_coverage_gap_page_loads(): void
    {
        $this->actingAs($this->adminUser)
            ->get(route('reports.sanction-coverage-gap'))
            ->assertOk();
    }
}
