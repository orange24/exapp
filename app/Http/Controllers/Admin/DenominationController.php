<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Currency;
use App\Models\CurrencyDenomination;
use Illuminate\Http\Request;

class DenominationController extends Controller
{
    public function store(Request $request, Currency $currency)
    {
        $validated = $request->validate([
            'denom_label' => 'required|string|max:30',
            'seq'         => 'nullable|integer|min:0',
        ]);

        $currency->denominations()->create([
            'currency_code' => $currency->currency_code,
            'denom_label'   => $validated['denom_label'],
            'display_name'  => $currency->currency_code . ' ' . $validated['denom_label'],
            'seq'           => $validated['seq'] ?? 0,
            'is_active'     => true,
        ]);

        return redirect()->route('admin.currencies.edit', $currency)
            ->with('success', 'เพิ่ม denomination "' . $validated['denom_label'] . '" เรียบร้อย');
    }

    public function update(Request $request, CurrencyDenomination $denomination)
    {
        $validated = $request->validate([
            'denom_label' => 'required|string|max:30',
            'seq'         => 'nullable|integer|min:0',
            'is_active'   => 'boolean',
        ]);

        $denomination->update([
            'denom_label'  => $validated['denom_label'],
            'display_name' => $denomination->currency_code . ' ' . $validated['denom_label'],
            'seq'          => $validated['seq'] ?? $denomination->seq,
            'is_active'    => $request->boolean('is_active'),
        ]);

        $currency = Currency::where('currency_code', $denomination->currency_code)->first();

        return redirect()->route('admin.currencies.edit', $currency)
            ->with('success', 'แก้ไข denomination เรียบร้อย');
    }

    public function destroy(CurrencyDenomination $denomination)
    {
        $currency = Currency::where('currency_code', $denomination->currency_code)->first();
        $label = $denomination->display_name;
        $denomination->delete();

        return redirect()->route('admin.currencies.edit', $currency)
            ->with('success', 'ลบ "' . $label . '" เรียบร้อย');
    }
}
