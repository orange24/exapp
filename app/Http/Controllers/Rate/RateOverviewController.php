<?php

namespace App\Http\Controllers\Rate;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Counter;
use App\Models\CounterRate;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * ดูเรทวันนี้ของทุกเคาน์เตอร์ในสาขาที่มีสิทธิ์ — อ่านอย่างเดียว
 *
 * จอแสดงราคา (rate.board) ดูได้ทีละเคาน์เตอร์และอิงเคาน์เตอร์ที่นั่งทำงานอยู่
 * auditor ไม่ได้ประจำเคาน์เตอร์ไหน จึงต้องมีหน้าที่เทียบทุกสาขาในตารางเดียว
 */
class RateOverviewController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasMenuAccess('rates.overview'), 403);

        $visibleBranchIds = $request->user()->getVisibleBranchIds();
        $branches = Branch::whereIn('id', $visibleBranchIds)->where('is_active', true)->orderBy('branch_name')->get();

        $branchId = $request->integer('branch_id') ?: null;
        if ($branchId !== null && ! $branches->contains('id', $branchId)) {
            $branchId = null;
        }

        $counters = Counter::with('branch')
            ->where('is_active', true)
            ->whereIn('branch_id', $branchId !== null ? [$branchId] : $branches->pluck('id'))
            ->orderBy('branch_id')->orderBy('counter_code')
            ->get();

        $rates = CounterRate::with(['currency', 'denomination'])
            ->whereIn('counter_id', $counters->pluck('id'))
            ->whereDate('rate_date', today())
            ->whereNotNull('denomination_id')
            ->get();

        // แถว = ชนิดธนบัตร เรียงตามสกุลเงินแล้วตาม denom / ช่อง = เคาน์เตอร์
        $rows = $rates
            ->groupBy('denomination_id')
            ->map(fn ($group) => [
                'currency' => $group->first()->currency,
                'denomination' => $group->first()->denomination,
                'byCounter' => $group->keyBy('counter_id'),
            ])
            ->sortBy(fn ($row) => sprintf('%05d-%05d', $row['currency']?->seq ?? 99999, $row['denomination']?->seq ?? 99999))
            ->values();

        return view('rate.overview', [
            'branches' => $branches,
            'branchId' => $branchId,
            'counters' => $counters,
            'rows' => $rows,
            'lastUpdate' => $rates->max('updated_at')?->format('d/m/Y H:i'),
        ]);
    }
}
