<?php

namespace Tests\Feature\Sanction;

use App\Models\SanctionEntry;
use App\Models\SanctionSyncRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SanctionsSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    public function test_rejects_unknown_list_code(): void
    {
        $this->artisan('sanctions:sync', ['--list' => 'bogus'])
            ->expectsOutputToContain('ไม่รู้จัก list')
            ->assertExitCode(1);
    }

    public function test_dry_run_does_not_write_entries(): void
    {
        $this->fakeAmloResponses();

        $this->artisan('sanctions:sync', ['--list' => 'freeze_05_th', '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(0, SanctionEntry::count());
        $this->assertSame(0, SanctionSyncRun::count());
    }

    public function test_sync_writes_entries_and_a_run_record(): void
    {
        $this->fakeAmloResponses();

        $this->artisan('sanctions:sync', ['--list' => 'freeze_05_th'])
            ->assertExitCode(0);

        $this->assertSame(1, SanctionEntry::count());
        $this->assertSame('AMRAN MING', SanctionEntry::first()->name_en);

        $run = SanctionSyncRun::first();
        $this->assertSame(SanctionSyncRun::STATUS_SUCCESS, $run->status);
        $this->assertSame('amlo_public_scraper', $run->source_adapter);
    }

    public function test_sync_all_runs_every_configured_list(): void
    {
        $this->fakeAmloResponses();

        $this->artisan('sanctions:sync', ['--list' => 'all'])
            ->assertExitCode(0);

        $this->assertSame(2, SanctionSyncRun::count());
        $this->assertEqualsCanonicalizing(
            [SanctionEntry::LIST_FREEZE_05_TH, SanctionEntry::LIST_FREEZE_04_UN],
            SanctionSyncRun::pluck('list_code')->all()
        );
    }

    private function fakeAmloResponses(): void
    {
        config(['sanction.amlo.request_delay_ms' => 0]);

        $listHtml = static function (string $slug): string {
            return '<table><tr><th>No.</th></tr><tr>'
                . '<td>1</td><td>อำรัน มิง</td><td>AMRAN MING</td>'
                . '<td>XXXX50002XXXX</td><td></td><td></td><td>Designated person</td>'
                . '<td><a href="https://aps.amlo.go.th/aps/public/' . $slug . '/detail/17178">view</a></td>'
                . '</tr></table>';
        };

        $detailHtml = '<div><a class="block-icon">'
            . 'Examining the Name of Designated Person under Section 7 (Thailand List)'
            . '<br>As Of 2026-10-01</a></div>'
            . '<table>'
            . '<tr><th>Notification Number</th><td>001/2556</td></tr>'
            . '<tr><th>Individual/Entity Name (Thailand)</th><td>1. อำรัน มิง</td></tr>'
            . '<tr><th>Individual/Entity Name (English)</th><td>AMRAN MING</td></tr>'
            . '<tr><th>Date of Birth</th><td>18-12-1981</td></tr>'
            . '<tr><th>Also known as (a.k.a)</th><td></td></tr>'
            . '<tr><th>Nationality</th><td>TH</td></tr>'
            . '<tr><th>Address No.1</th><td></td></tr>'
            . '<tr><th>Address No.2</th><td></td></tr>'
            . '<tr><th>Phone Number</th><td></td></tr>'
            . '<tr><th>E-mail</th><td></td></tr>'
            . '<tr><th>National Identification Number</th><td>5960500028101</td></tr>'
            . '<tr><th>Passport Number</th><td></td></tr>'
            . '<tr><th>Company Registration Number</th><td></td></tr>'
            . '<tr><th>Group</th><td></td></tr>'
            . '<tr><th>Status</th><td>Designated person</td></tr>'
            . '</table>';

        Http::fake([
            '*/thailandlist/detail/*' => Http::response($detailHtml),
            '*/unlist/detail/*' => Http::response($detailHtml),
            '*/thailandlist/*' => Http::response($listHtml('thailandlist')),
            '*/unlist/*' => Http::response($listHtml('unlist')),
        ]);
    }
}
