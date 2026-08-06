<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkingDay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\SeedsTestData;

class PageAccessTest extends TestCase
{
    use RefreshDatabase, SeedsTestData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTestData();
    }

    // ─── Guest redirects to login ────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_login_page_loads(): void
    {
        $this->get('/login')->assertStatus(200);
    }

    // ─── Dashboard ───────────────────────────────────────────────────────

    public function test_dashboard_loads_for_admin(): void
    {
        $this->actingAsAdmin()->get('/dashboard')->assertStatus(200);
    }

    // ─── Sidebar menu rendering ──────────────────────────────────────────

    public function test_rate_board_menu_links_to_working_counter(): void
    {
        $this->actingAsAdmin()->get('/dashboard')
            ->assertStatus(200)
            ->assertSee('/rate/' . $this->counter->counter_code, false);
    }

    public function test_rate_board_menu_is_hidden_without_working_counter(): void
    {
        // rate.board needs a counter code; with none it must be skipped rather
        // than throwing UrlGenerationException and 500-ing every page.
        $this->actingAs($this->adminUser)->get('/dashboard')
            ->assertStatus(200)
            ->assertDontSee('/rate/', false);
    }

    // ─── Transaction pages ───────────────────────────────────────────────

    public function test_buy_page_loads(): void
    {
        $this->openWorkingDay();
        $this->actingAsAdmin()->get('/transaction/buy')->assertStatus(200);
    }

    public function test_sell_page_loads(): void
    {
        $this->openWorkingDay();
        $this->actingAsAdmin()->get('/transaction/sell')->assertStatus(200);
    }

    /**
     * Buy/Sell redirect to open-close-day unless the working day is open.
     *
     * Inserted via the query builder to bypass the `work_date` date cast, which
     * would store "Y-m-d H:i:s". MySQL truncates that to a DATE so lookups still
     * match in production, but sqlite keeps the string verbatim and never would.
     */
    private function openWorkingDay(): void
    {
        WorkingDay::insert([
            'counter_id' => $this->counter->id,
            'work_date' => now()->format('Y-m-d'),
            'opening_thb_cash' => 10000,
            'status' => 'open',
            'opened_by' => $this->adminUser->id,
            'opened_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_my_transactions_page_loads(): void
    {
        $this->actingAsAdmin()->get('/my-transactions')->assertStatus(200);
    }

    public function test_all_transactions_page_loads(): void
    {
        $this->actingAsAdmin()->get('/admin/transactions')->assertStatus(200);
    }

    // ─── Trader tools ────────────────────────────────────────────────────

    /**
     * Inventory Dashboard is trader-only. Admin must not see the link, because
     * the route guard would 403 — menu visibility and route guard have to agree.
     */
    public function test_trader_inventory_is_hidden_and_blocked_for_admin(): void
    {
        $this->actingAsAdmin()->get('/dashboard')
            ->assertStatus(200)
            ->assertDontSee('/trader/inventory', false);

        $this->actingAsAdmin()->get('/trader/inventory')->assertStatus(403);
    }

    public function test_bank_sales_is_hidden_and_blocked_for_admin(): void
    {
        $this->actingAsAdmin()->get('/dashboard')
            ->assertStatus(200)
            ->assertDontSee('/admin/bank-sales', false);

        $this->actingAsAdmin()->get('/admin/bank-sales')->assertStatus(403);
    }

    /** A trader's home screen is the Inventory Dashboard, not the branch dashboard. */
    public function test_dashboard_redirects_trader_to_inventory_dashboard(): void
    {
        $this->actingAs($this->traderUser())->get('/dashboard')->assertRedirect('/trader/inventory');
    }

    public function test_trader_tools_load_for_trader(): void
    {
        $trader = $this->traderUser();

        $this->actingAs($trader)->get('/trader/inventory')->assertStatus(200);
        $this->actingAs($trader)->get('/admin/bank-sales')->assertStatus(200);
    }

    private function traderUser(): User
    {
        return User::firstOrCreate(
            ['email' => 'trader@test.local'],
            [
                'name' => 'Trader',
                'password' => bcrypt('password'),
                'role_id' => Role::where('name', 'trader')->value('id'),
                'branch_id' => $this->branch->id,
                'is_active' => true,
            ]
        );
    }

    // ─── Settings routes are Admin-only ──────────────────────────────────
    //
    // Hiding the menu is not access control — these must 403 on a direct URL.

    public function test_settings_routes_are_blocked_for_non_admin(): void
    {
        foreach (['/admin/users', '/admin/branches', '/admin/permissions', '/admin/sessions'] as $url) {
            $this->actingAsStaff()->get($url)->assertStatus(403);
        }
    }

    // ─── Rate pages ──────────────────────────────────────────────────────

    public function test_rate_index_loads(): void
    {
        $this->actingAsAdmin()->get('/admin/rate')->assertStatus(200);
    }

    // ─── Inventory pages ─────────────────────────────────────────────────

    public function test_inventory_dashboard_loads(): void
    {
        $this->actingAsAdmin()->get('/inventory')->assertStatus(200);
    }

    public function test_inventory_movements_loads(): void
    {
        $this->actingAsAdmin()->get('/inventory/movements')->assertStatus(200);
    }

    public function test_borrow_return_page_loads(): void
    {
        $this->actingAsAdmin()->get('/inventory/borrow-return')->assertStatus(200);
    }

    public function test_disbursement_page_loads(): void
    {
        $this->actingAsAdmin()->get('/inventory/disbursement')->assertStatus(200);
    }

    public function test_intraday_return_page_loads(): void
    {
        $this->actingAsAdmin()->get('/inventory/intraday-return')->assertStatus(200);
    }

    public function test_open_close_day_page_loads(): void
    {
        $this->actingAsAdmin()->get('/inventory/open-close-day')->assertStatus(200);
    }

    public function test_booking_page_loads(): void
    {
        $this->actingAsAdmin()->get('/inventory/booking')->assertStatus(200);
    }

    public function test_adjustment_page_loads(): void
    {
        $this->actingAsAdmin()->get('/inventory/adjustment')->assertStatus(200);
    }

    // ─── Accounting pages ────────────────────────────────────────────────

    public function test_journal_page_loads(): void
    {
        $this->actingAsAdmin()->get('/accounting/journal')->assertStatus(200);
    }

    public function test_ledger_page_loads(): void
    {
        $this->actingAsAdmin()->get('/accounting/ledger')->assertStatus(200);
    }

    // ─── Admin pages ─────────────────────────────────────────────────────

    public function test_users_page_loads(): void
    {
        $this->actingAsAdmin()->get('/admin/users')->assertStatus(200);
    }

    public function test_currencies_page_loads(): void
    {
        $this->actingAsAdmin()->get('/admin/currencies')->assertStatus(200);
    }

    public function test_customers_page_loads(): void
    {
        $this->actingAsAdmin()->get('/admin/customers')->assertStatus(200);
    }

    public function test_accounts_page_loads(): void
    {
        $this->actingAsAdmin()->get('/admin/accounts')->assertStatus(200);
    }

    public function test_branches_page_loads(): void
    {
        $this->actingAsAdmin()->get('/admin/branches')->assertStatus(200);
    }

    public function test_permissions_page_loads(): void
    {
        $this->actingAsAdmin()->get('/admin/permissions')->assertStatus(200);
    }

    public function test_sessions_page_loads(): void
    {
        $this->actingAsAdmin()->get('/admin/sessions')->assertStatus(200);
    }

    public function test_change_password_page_loads(): void
    {
        $this->actingAsAdmin()->get('/change-password')->assertStatus(200);
    }

    // ─── Reports ─────────────────────────────────────────────────────────

    public function test_daily_report_page_loads(): void
    {
        $this->actingAsAdmin()->get('/reports/daily')->assertStatus(200);
    }

    public function test_accounting_summary_page_loads(): void
    {
        $this->actingAsAdmin()->get('/reports/accounting-summary')->assertStatus(200);
    }

    public function test_avg_rate_page_loads(): void
    {
        $this->actingAsAdmin()->get('/reports/avg-rate')->assertStatus(200);
    }

    public function test_stock_movement_report_loads(): void
    {
        $this->actingAsAdmin()->get('/reports/stock-movement')->assertStatus(200);
    }

    public function test_transfer_report_loads(): void
    {
        $this->actingAsAdmin()->get('/reports/transfer')->assertStatus(200);
    }

    public function test_profit_loss_report_loads(): void
    {
        $this->actingAsAdmin()->get('/reports/profit-loss')->assertStatus(200);
    }

    public function test_stock_valuation_report_loads(): void
    {
        $this->actingAsAdmin()->get('/reports/stock-valuation')->assertStatus(200);
    }

    public function test_cashier_report_loads(): void
    {
        $this->actingAsAdmin()->get('/reports/cashier')->assertStatus(200);
    }

    public function test_customer_transaction_report_loads(): void
    {
        $this->actingAsAdmin()->get('/reports/customer-transaction')->assertStatus(200);
    }

    public function test_bot_monthly_report_loads(): void
    {
        $this->actingAsAdmin()->get('/reports/bot-monthly')->assertStatus(200);
    }
}
