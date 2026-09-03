<?php

namespace Tests\Feature\Rate;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

/**
 * /admin/rate had no role guard beyond `auth` — any authenticated user
 * (staff, trader, auditor) could open /admin/rate/{counter} and actually
 * change rates via the Livewire form, not just view the "ตั้งราคา" button.
 * Only admin/branch_manager (User::canChangeRates()) may reach it; everyone
 * else is routed to the read-only rate board instead.
 */
class RateAccessControlTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    public function test_staff_cannot_open_rate_management_index(): void
    {
        $this->actingAsStaff()->get('/admin/rate')->assertStatus(403);
    }

    public function test_staff_cannot_open_rate_setup_form(): void
    {
        $this->actingAsStaff()
            ->get(route('admin.rate.setup', $this->counter))
            ->assertStatus(403);
    }

    public function test_admin_can_still_open_rate_management(): void
    {
        $this->actingAsAdmin()->get('/admin/rate')->assertStatus(200);
        $this->actingAsAdmin()
            ->get(route('admin.rate.setup', $this->counter))
            ->assertStatus(200);
    }

    public function test_staff_dashboard_rate_card_links_to_rate_board_not_setup(): void
    {
        $response = $this->actingAsStaff()->get('/dashboard');

        $response->assertStatus(200);
        $response->assertDontSee(route('admin.rate.index'), false);
        $response->assertSee('/rate/' . $this->counter->counter_code, false);
    }

    public function test_admin_dashboard_rate_card_still_links_to_setup(): void
    {
        $response = $this->actingAsAdmin()->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee(route('admin.rate.index'), false);
    }

    public function test_staff_sidebar_shows_rate_board_but_not_rate_setup(): void
    {
        $response = $this->actingAsStaff()->get('/dashboard');

        $response->assertStatus(200);
        $response->assertSee('ดูเรทสาขา');
        $response->assertDontSee('ตั้งราคา');
    }
}
