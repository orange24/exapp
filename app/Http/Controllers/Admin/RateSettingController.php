<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Counter;
use App\Models\CounterRate;
use App\Models\CurrencyDenomination;
use App\Models\RateSettingAdjustment;
use App\Models\RateChangeLog;
use App\Models\RateSettingMaster;
use Illuminate\Http\Request;

class RateSettingController extends Controller
{
    /**
     * List all rate setting groups.
     */
    public function index()
    {
        $settings = RateSettingMaster::with(['mainCounter.branch', 'counters'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('admin.rate-settings.index', compact('settings'));
    }

    /**
     * Show create form.
     */
    public function create()
    {
        $counters = Counter::with('branch')->where('is_active', true)->get();
        $denominations = CurrencyDenomination::with('currency')
            ->where('is_active', true)
            ->orderBy('seq')
            ->get();

        return view('admin.rate-settings.form', [
            'rateSetting'   => new RateSettingMaster(),
            'counters'      => $counters,
            'denominations' => $denominations,
            'allSettings'   => collect(),
            'isEdit'        => false,
        ]);
    }

    /**
     * Store new rate setting group.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'group_name'      => 'required|string|max:100',
            'main_counter_id' => 'required|exists:counters,id',
            'is_active'       => 'boolean',
            'counter_ids'     => 'nullable|array',
            'counter_ids.*'   => 'exists:counters,id',
            'adjustments'     => 'nullable|array',
        ]);

        $rateSetting = RateSettingMaster::create([
            'group_name'      => $validated['group_name'],
            'main_counter_id' => $validated['main_counter_id'],
            'is_active'       => $request->boolean('is_active'),
            'created_by'      => auth()->id(),
        ]);

        // Sync target counters
        $counterIds = $request->input('counter_ids', []);
        $rateSetting->counters()->sync($counterIds);

        // Save adjustments
        $this->saveAdjustments($rateSetting, $request->input('adjustments', []));

        return redirect()->route('admin.rate-settings.index')
            ->with('success', 'สร้างกลุ่มตั้งค่าเรียบร้อยแล้ว (Setting group created)');
    }

    /**
     * Show edit form.
     */
    public function edit(RateSettingMaster $rateSetting)
    {
        $rateSetting->load(['counters', 'adjustments']);

        $counters = Counter::with('branch')->where('is_active', true)->get();
        $denominations = CurrencyDenomination::with('currency')
            ->where('is_active', true)
            ->orderBy('seq')
            ->get();

        $allSettings = RateSettingMaster::where('id', '!=', $rateSetting->id)->get();

        return view('admin.rate-settings.form', [
            'rateSetting'   => $rateSetting,
            'counters'      => $counters,
            'denominations' => $denominations,
            'allSettings'   => $allSettings,
            'isEdit'        => true,
        ]);
    }

    /**
     * Update rate setting group.
     */
    public function update(Request $request, RateSettingMaster $rateSetting)
    {
        $validated = $request->validate([
            'group_name'      => 'required|string|max:100',
            'main_counter_id' => 'required|exists:counters,id',
            'is_active'       => 'boolean',
            'counter_ids'     => 'nullable|array',
            'counter_ids.*'   => 'exists:counters,id',
            'adjustments'     => 'nullable|array',
        ]);

        $rateSetting->update([
            'group_name'      => $validated['group_name'],
            'main_counter_id' => $validated['main_counter_id'],
            'is_active'       => $request->boolean('is_active'),
        ]);

        // Sync target counters
        $counterIds = $request->input('counter_ids', []);
        $rateSetting->counters()->sync($counterIds);

        // Save adjustments
        $this->saveAdjustments($rateSetting, $request->input('adjustments', []));

        return redirect()->route('admin.rate-settings.index')
            ->with('success', 'แก้ไขกลุ่มตั้งค่าเรียบร้อยแล้ว (Setting group updated)');
    }

    /**
     * Delete rate setting group.
     */
    public function destroy(RateSettingMaster $rateSetting)
    {
        $rateSetting->delete();

        return redirect()->route('admin.rate-settings.index')
            ->with('success', 'ลบกลุ่มตั้งค่าเรียบร้อยแล้ว (Setting group deleted)');
    }

    /**
     * Copy adjustments from another group.
     */
    public function copyFrom(Request $request, RateSettingMaster $rateSetting)
    {
        $request->validate([
            'source_setting_id' => 'required|exists:rate_setting_masters,id',
        ]);

        $source = RateSettingMaster::with('adjustments')->findOrFail($request->source_setting_id);

        // Delete existing adjustments and copy from source
        $rateSetting->adjustments()->delete();

        foreach ($source->adjustments as $adj) {
            RateSettingAdjustment::create([
                'setting_master_id' => $rateSetting->id,
                'denomination_id'   => $adj->denomination_id,
                'cal_rate_buy'      => $adj->cal_rate_buy,
                'cal_rate_sell'     => $adj->cal_rate_sell,
            ]);
        }

        return redirect()->route('admin.rate-settings.edit', $rateSetting)
            ->with('success', 'คัดลอกค่าปรับราคาจากกลุ่ม "' . $source->group_name . '" เรียบร้อยแล้ว');
    }

    /**
     * Apply calculated rates to all target counters.
     * Target rate = Main counter rate + adjustment
     */
    public function applyRates(RateSettingMaster $rateSetting)
    {
        $rateSetting->load(['counters', 'adjustments']);
        $today = today();

        // Get main counter's rates for today (only denomination-based rates)
        $mainRates = CounterRate::where('counter_id', $rateSetting->main_counter_id)
            ->whereDate('rate_date', $today)
            ->whereNotNull('denomination_id')
            ->get()
            ->keyBy('denomination_id');

        if ($mainRates->isEmpty()) {
            return redirect()->route('admin.rate-settings.index')
                ->with('error', 'ไม่พบราคาของเคาน์เตอร์หลักสำหรับวันนี้ — กรุณาตั้งราคาก่อน');
        }

        $adjustments = $rateSetting->adjustments->keyBy('denomination_id');
        $updatedCounters = 0;

        foreach ($rateSetting->counters as $counter) {
            foreach ($mainRates as $denomId => $mainRate) {
                $adj = $adjustments->get($denomId);
                $calBuy  = $adj ? $adj->cal_rate_buy : 0;
                $calSell = $adj ? $adj->cal_rate_sell : 0;

                $newBuy  = $mainRate->rate_buy + $calBuy;
                $newSell = $mainRate->rate_sell + $calSell;

                // Read old rate for logging
                $old = CounterRate::where('counter_id', $counter->id)
                    ->where('denomination_id', $denomId)
                    ->whereDate('rate_date', $today)
                    ->first();

                CounterRate::updateOrCreate(
                    [
                        'counter_id'      => $counter->id,
                        'denomination_id' => $denomId,
                        'rate_date'       => $today->toDateString(),
                    ],
                    [
                        'currency_code' => $mainRate->currency_code,
                        'rate_buy'      => $newBuy,
                        'rate_sell'     => $newSell,
                        'set_by'        => auth()->id(),
                    ]
                );

                RateChangeLog::logChange(
                    counterId: $counter->id,
                    denomId: $denomId,
                    currencyCode: $mainRate->currency_code,
                    oldBuy: $old ? (float) $old->rate_buy : null,
                    oldSell: $old ? (float) $old->rate_sell : null,
                    newBuy: $newBuy,
                    newSell: $newSell,
                    source: 'rate_setting',
                    ref: $rateSetting->group_name,
                );
            }
            $updatedCounters++;
        }

        return redirect()->route('admin.rate-settings.index')
            ->with('success', "คำนวณราคาเรียบร้อย — อัปเดต {$updatedCounters} เคาน์เตอร์ (Applied rates to {$updatedCounters} counters)");
    }

    /**
     * Save adjustment records for a setting group.
     */
    private function saveAdjustments(RateSettingMaster $rateSetting, array $adjustments): void
    {
        foreach ($adjustments as $denomId => $values) {
            $calBuy  = $values['cal_rate_buy'] ?? 0;
            $calSell = $values['cal_rate_sell'] ?? 0;

            RateSettingAdjustment::updateOrCreate(
                [
                    'setting_master_id' => $rateSetting->id,
                    'denomination_id'   => $denomId,
                ],
                [
                    'cal_rate_buy'  => $calBuy,
                    'cal_rate_sell' => $calSell,
                ]
            );
        }
    }
}
