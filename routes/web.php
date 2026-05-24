<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Rate\RateBoardController;
use App\Http\Controllers\Rate\RateController;
use App\Http\Controllers\Transaction\TransactionController;
use App\Http\Controllers\Admin\SessionManagementController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CurrencyController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\DenominationController;
use App\Http\Controllers\Admin\RateSettingController;
use App\Http\Controllers\Admin\BranchController;
use App\Http\Controllers\Api\OcrController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Report\AccountingSummaryController;
use App\Http\Controllers\Report\AvgRateSummaryController;
use App\Http\Controllers\Report\CashierPerformanceReportController;
use App\Http\Controllers\Report\CustomerTransactionReportController;
use App\Http\Controllers\Report\BotMonthlyReportController;
use App\Http\Controllers\Report\DailyReportController;
use App\Http\Controllers\Report\ProfitLossReportController;
use App\Http\Controllers\Report\StockMovementReportController;
use App\Http\Controllers\Report\StockValuationReportController;
use App\Http\Controllers\Report\TransferReportController;
use Illuminate\Support\Facades\Route;

// ─── Public ──────────────────────────────────────────────────────────────────
Route::get('/', fn() => redirect()->route('login'));

// Customer-facing Rate Board (public — no auth required)
Route::get('/rate/{counterCode}', [RateBoardController::class, 'show'])->name('rate.board');

// ─── Auth ─────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.post');
});

Route::match(['get', 'post'], '/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ─── Authenticated ────────────────────────────────────────────────────────────
Route::middleware(['auth', 'session.check'])->group(function () {

    Route::get('/dashboard', fn() => view('dashboard'))->name('dashboard');

    // Switch working counter (stored in session)
    Route::post('/switch-counter', function (\Illuminate\Http\Request $request) {
        $counterId = $request->input('counter_id');
        $query = \App\Models\Counter::where('id', $counterId)->where('is_active', true);

        // Staff can only switch to counters in their own branch
        if (!auth()->user()->isAdmin()) {
            $query->where('branch_id', auth()->user()->branch_id);
        }

        $counter = $query->firstOrFail();
        session(['working_counter_id' => $counter->id, 'working_counter_name' => $counter->counter_name]);

        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'counter' => $counter->counter_name]);
        }
        return back()->with('success', 'เปลี่ยนเคาน์เตอร์เป็น ' . $counter->counter_name);
    })->name('switch-counter');

    // OCR API (web route for session auth)
    Route::post('/ocr/passport', [OcrController::class, 'passport'])->name('ocr.passport');

    // Rate management
    Route::prefix('admin/rate')->name('admin.rate.')->group(function () {
        Route::get('/',           [RateController::class, 'index'])->name('index');
        Route::get('/{counter}',  [RateController::class, 'setup'])->name('setup');
    });

    // Transactions
    Route::prefix('transaction')->name('transaction.')->group(function () {
        Route::get('/buy',       [TransactionController::class, 'buy'])->name('buy');
        Route::get('/sell',      [TransactionController::class, 'sell'])->name('sell');
        Route::get('/{transaction}/print', [TransactionController::class, 'printSlip'])->name('print');
    });

    // Transaction history
    Route::get('/my-transactions', [TransactionController::class, 'myTransactions'])->name('transaction.my');
    Route::get('/admin/transactions', [TransactionController::class, 'allTransactions'])->name('admin.transactions');
    Route::post('/transaction/{transaction}/request-cancel', [TransactionController::class, 'requestCancel'])->name('transaction.request-cancel');
    Route::post('/transaction/{transaction}/approve-cancel', [TransactionController::class, 'approveCancel'])->name('transaction.approve-cancel');
    Route::get('/transaction/{transaction}/detail', [TransactionController::class, 'detail'])->name('transaction.detail');

    // Admin: session management
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/sessions', fn() => view('admin.sessions'))->name('sessions');

        // Module 1: User Management
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::patch('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

        // Module 1: Permission Matrix
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::put('/permissions', [PermissionController::class, 'update'])->name('permissions.update');

        // Module 2: Currency CRUD
        Route::resource('currencies', CurrencyController::class)->except(['show', 'destroy']);
        Route::patch('/currencies/{currency}/toggle-active', [CurrencyController::class, 'toggleActive'])->name('currencies.toggle-active');

        // Module 2: Denomination CRUD (nested under currency)
        Route::post('/currencies/{currency}/denominations', [DenominationController::class, 'store'])->name('denominations.store');
        Route::put('/denominations/{denomination}', [DenominationController::class, 'update'])->name('denominations.update');
        Route::delete('/denominations/{denomination}', [DenominationController::class, 'destroy'])->name('denominations.destroy');

        // Module 2: Customer Registry
        Route::resource('customers', CustomerController::class)->except(['destroy']);

        // Module 2: Chart of Accounts
        Route::resource('accounts', AccountController::class)->except(['show', 'destroy']);
        Route::patch('/accounts/{account}/toggle-active', [AccountController::class, 'toggleActive'])->name('accounts.toggle-active');

        // Branch & Counter Management
        Route::resource('branches', BranchController::class)->except(['show', 'destroy']);
        Route::patch('/branches/{branch}/toggle-active', [BranchController::class, 'toggleActive'])->name('branches.toggle-active');
        Route::post('/branches/{branch}/counters', [BranchController::class, 'storeCounter'])->name('branches.store-counter');
        Route::patch('/counters/{counter}/toggle', [BranchController::class, 'toggleCounter'])->name('branches.toggle-counter');
        Route::delete('/counters/{counter}', [BranchController::class, 'destroyCounter'])->name('branches.destroy-counter');

        // Rate Calculation Settings (Accounting Admin)
        Route::resource('rate-settings', RateSettingController::class)->except(['show'])->parameter('rate-settings', 'rateSetting');
        Route::post('/rate-settings/{rateSetting}/copy-from', [RateSettingController::class, 'copyFrom'])->name('rate-settings.copy-from');
        Route::post('/rate-settings/{rateSetting}/apply', [RateSettingController::class, 'applyRates'])->name('rate-settings.apply');

        // SuperRich Reference Rates
        Route::get('/superrich-rates', fn() => view('admin.superrich-rates'))->name('superrich-rates');

        // Sell to Bank
        Route::get('/bank-sales', fn() => view('admin.bank-sales'))->name('bank-sales');
    });

    // Module 1: Password Change
    Route::get('/change-password', fn() => view('user.change-password'))->name('user.change-password');

    // Inventory
    Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::get('/inventory/movements', [InventoryController::class, 'movements'])->name('inventory.movements');
    Route::get('/inventory/borrow-return', [InventoryController::class, 'borrowReturn'])->name('inventory.borrow-return');
    Route::get('/inventory/disbursement', [InventoryController::class, 'disbursement'])->name('inventory.disbursement');
    Route::get('/inventory/intraday-return', [InventoryController::class, 'intradayReturn'])->name('inventory.intraday-return');
    Route::get('/inventory/open-close-day', [InventoryController::class, 'openCloseDay'])->name('inventory.open-close-day');
    Route::get('/inventory/booking', [InventoryController::class, 'booking'])->name('inventory.booking');
    Route::get('/inventory/adjustment', [InventoryController::class, 'adjustment'])->name('inventory.adjustment');

    // Accounting
    Route::get('/accounting/journal', fn() => view('accounting.journal'))->name('accounting.journal');
    Route::get('/accounting/ledger', fn() => view('accounting.ledger'))->name('accounting.ledger');

    // Reports
    Route::get('/reports/daily', [DailyReportController::class, 'index'])->name('reports.daily');
    Route::post('/reports/daily/export', [DailyReportController::class, 'exportExcel'])->name('reports.daily.export');

    // Accounting Summary Report (All Branches) — admin/auditor only
    Route::get('/reports/accounting-summary', [AccountingSummaryController::class, 'index'])->name('reports.accounting-summary');
    Route::post('/reports/accounting-summary/export', [AccountingSummaryController::class, 'exportExcel'])->name('reports.accounting-summary.export');

    // Summary Report Average Rate — admin/auditor only
    Route::get('/reports/avg-rate', [AvgRateSummaryController::class, 'index'])->name('reports.avg-rate');
    Route::post('/reports/avg-rate/export', [AvgRateSummaryController::class, 'exportExcel'])->name('reports.avg-rate.export');

    // Stock Movement Report
    Route::get('/reports/stock-movement', [StockMovementReportController::class, 'index'])->name('reports.stock-movement');
    Route::post('/reports/stock-movement/export', [StockMovementReportController::class, 'exportExcel'])->name('reports.stock-movement.export');

    // Transfer Report
    Route::get('/reports/transfer', [TransferReportController::class, 'index'])->name('reports.transfer');
    Route::post('/reports/transfer/export', [TransferReportController::class, 'exportExcel'])->name('reports.transfer.export');

    // FX Profit/Loss Report
    Route::get('/reports/profit-loss', [ProfitLossReportController::class, 'index'])->name('reports.profit-loss');
    Route::post('/reports/profit-loss/export', [ProfitLossReportController::class, 'exportExcel'])->name('reports.profit-loss.export');

    // Stock Valuation Report
    Route::get('/reports/stock-valuation', [StockValuationReportController::class, 'index'])->name('reports.stock-valuation');
    Route::post('/reports/stock-valuation/export', [StockValuationReportController::class, 'exportExcel'])->name('reports.stock-valuation.export');

    // Cashier Performance Report
    Route::get('/reports/cashier', [CashierPerformanceReportController::class, 'index'])->name('reports.cashier');
    Route::post('/reports/cashier/export', [CashierPerformanceReportController::class, 'exportExcel'])->name('reports.cashier.export');

    // Customer Transaction Report
    Route::get('/reports/customer-transaction', [CustomerTransactionReportController::class, 'index'])->name('reports.customer-transaction');
    Route::post('/reports/customer-transaction/export', [CustomerTransactionReportController::class, 'exportExcel'])->name('reports.customer-transaction.export');

    // BOT Monthly MC Report (รายงานประจำเดือน ธปท.)
    Route::get('/reports/bot-monthly', [BotMonthlyReportController::class, 'index'])->name('reports.bot-monthly');
    Route::post('/reports/bot-monthly/export', [BotMonthlyReportController::class, 'exportExcel'])->name('reports.bot-monthly.export');
    Route::post('/reports/bot-monthly/settings', [BotMonthlyReportController::class, 'updateSettings'])->name('reports.bot-monthly.settings');

    // BOT Report Staging (จัดการข้อมูลก่อนส่ง ธปท.)
    Route::get('/reports/bot-staging', fn() => view('reports.bot-staging'))->name('reports.bot-staging');
    Route::get('/reports/bot-staging/{report}/export', [BotMonthlyReportController::class, 'exportFromStaging'])->name('reports.bot-monthly.export-staging');
});

