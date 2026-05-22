<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    /**
     * List all currencies.
     */
    public function index(Request $request)
    {
        $query = Currency::withCount('denominations');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('currency_code', 'like', "%{$search}%")
                  ->orWhere('currency_name', 'like', "%{$search}%")
                  ->orWhere('currency_name_th', 'like', "%{$search}%")
                  ->orWhere('country', 'like', "%{$search}%");
            });
        }

        $currencies = $query->orderBy('seq')->orderBy('currency_code')->paginate(50)
            ->withQueryString();

        return view('admin.currencies.index', compact('currencies'));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        return view('admin.currencies.form', [
            'currency' => new Currency(),
            'isEdit'   => false,
        ]);
    }

    /**
     * Store new currency.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'currency_code'    => 'required|string|max:10|unique:currencies,currency_code',
            'currency_name'    => 'required|string|max:100',
            'currency_name_th' => 'nullable|string|max:100',
            'country'          => 'nullable|string|max:100',
            'seq'              => 'nullable|integer|min:0',
            'is_active'        => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['currency_code'] = strtoupper($validated['currency_code']);
        $validated['country_flag'] = strtolower($validated['currency_code']) . '.png';

        Currency::create($validated);

        return redirect()->route('admin.currencies.index')
            ->with('success', 'เพิ่มสกุลเงินเรียบร้อยแล้ว (Currency created)');
    }

    /**
     * Show edit form.
     */
    public function edit(Currency $currency)
    {
        return view('admin.currencies.form', [
            'currency' => $currency,
            'isEdit'   => true,
        ]);
    }

    /**
     * Update currency.
     */
    public function update(Request $request, Currency $currency)
    {
        $validated = $request->validate([
            'currency_code'    => 'required|string|max:10|unique:currencies,currency_code,' . $currency->id,
            'currency_name'    => 'required|string|max:100',
            'currency_name_th' => 'nullable|string|max:100',
            'country'          => 'nullable|string|max:100',
            'seq'              => 'nullable|integer|min:0',
            'is_active'        => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['currency_code'] = strtoupper($validated['currency_code']);
        $validated['country_flag'] = strtolower($validated['currency_code']) . '.png';

        $currency->update($validated);

        return redirect()->route('admin.currencies.index')
            ->with('success', 'แก้ไขสกุลเงินเรียบร้อยแล้ว (Currency updated)');
    }

    /**
     * Toggle active status.
     */
    public function toggleActive(Currency $currency)
    {
        $currency->update(['is_active' => !$currency->is_active]);

        return redirect()->route('admin.currencies.index')
            ->with('success', "{$currency->currency_code} — " . ($currency->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน'));
    }
}
