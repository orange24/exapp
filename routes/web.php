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
use App\Http\Controllers\Report\DailyReportController;
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
    });

    // Module 1: Password Change
    Route::get('/change-password', fn() => view('user.change-password'))->name('user.change-password');

    // Reports
    Route::get('/reports/daily', [DailyReportController::class, 'index'])->name('reports.daily');
    Route::post('/reports/daily/export', [DailyReportController::class, 'exportExcel'])->name('reports.daily.export');
});

