<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    /**
     * Hierarchical tree view of accounts grouped by type.
     */
    public function index()
    {
        $accounts = Account::orderBy('type')
            ->orderBy('account_code')
            ->get();

        // Group by type, then build tree structure
        $grouped = $accounts->groupBy('type');

        // Build tree: parent accounts with children nested
        $tree = [];
        foreach ($grouped as $type => $items) {
            $parents = $items->whereNull('parent_code')->values();
            $tree[$type] = $parents->map(function ($parent) use ($items) {
                $parent->childAccounts = $items->where('parent_code', $parent->account_code)->values();
                return $parent;
            });
        }

        return view('admin.accounts.index', compact('tree'));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        $parentAccounts = Account::whereNull('parent_code')
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return view('admin.accounts.form', [
            'account'        => new Account(),
            'parentAccounts' => $parentAccounts,
            'isEdit'         => false,
        ]);
    }

    /**
     * Store new account.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'account_code' => 'required|string|max:20|unique:accounts,account_code',
            'name_th'      => 'required|string|max:200',
            'name_en'      => 'nullable|string|max:200',
            'type'         => 'required|in:asset,liability,equity,revenue,expense',
            'parent_code'  => 'nullable|string|exists:accounts,account_code',
            'is_active'    => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['level'] = $validated['parent_code'] ? 2 : 1;

        Account::create($validated);

        return redirect()->route('admin.accounts.index')
            ->with('success', 'เพิ่มผังบัญชีเรียบร้อยแล้ว (Account created)');
    }

    /**
     * Show edit form.
     */
    public function edit(Account $account)
    {
        $parentAccounts = Account::whereNull('parent_code')
            ->where('is_active', true)
            ->where('account_code', '!=', $account->account_code)
            ->orderBy('account_code')
            ->get();

        return view('admin.accounts.form', [
            'account'        => $account,
            'parentAccounts' => $parentAccounts,
            'isEdit'         => true,
        ]);
    }

    /**
     * Update account.
     */
    public function update(Request $request, Account $account)
    {
        $validated = $request->validate([
            'account_code' => 'required|string|max:20|unique:accounts,account_code,' . $account->id,
            'name_th'      => 'required|string|max:200',
            'name_en'      => 'nullable|string|max:200',
            'type'         => 'required|in:asset,liability,equity,revenue,expense',
            'parent_code'  => 'nullable|string|exists:accounts,account_code',
            'is_active'    => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['level'] = $validated['parent_code'] ? 2 : 1;

        $account->update($validated);

        return redirect()->route('admin.accounts.index')
            ->with('success', 'แก้ไขผังบัญชีเรียบร้อยแล้ว (Account updated)');
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Account $account)
    {
        $account->update(['is_active' => !$account->is_active]);

        return redirect()->route('admin.accounts.index')
            ->with('success', "{$account->account_code} — " . ($account->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน'));
    }
}
