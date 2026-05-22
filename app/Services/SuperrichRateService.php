<?php

namespace App\Services;

use App\Models\CounterRate;
use App\Models\CurrencyDenomination;
use App\Models\RateChangeLog;
use App\Models\SuperrichAdjustment;
use App\Models\SuperrichRate;
use App\Models\SuperrichRateBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SuperrichRateService
{
    /**
     * Fetch rates from SuperRich API and store in staging table.
     */
    public function fetchRates(int $userId): Collection
    {
        $response = Http::withHeaders([
            'Authorization' => config('superrich.auth'),
            'Accept' => 'application/json',
        ])->timeout(config('superrich.timeout', 15))
          ->get(config('superrich.url'));

        if (! $response->successful()) {
            throw new \RuntimeException('SuperRich API returned ' . $response->status());
        }

        $data = $response->json('data.exchangeRate', []);
        $fetchedAt = now();
        $rates = collect();

        foreach ($data as $currency) {
            $currencyCode = $currency['cUnit'] ?? '';
            if (! $currencyCode) continue;

            foreach ($currency['rate'] ?? [] as $rateData) {
                $srDenom = $rateData['denom'] ?? '';
                $denomId = $this->mapDenomination($currencyCode, $srDenom);

                $rate = SuperrichRate::create([
                    'currency_code' => $currencyCode,
                    'superrich_denom' => $srDenom,
                    'denomination_id' => $denomId,
                    'rate_buy' => (float) ($rateData['cBuying'] ?? 0),
                    'rate_sell' => (float) ($rateData['cSelling'] ?? 0),
                    'api_datetime' => isset($rateData['dateTime']) ? \Carbon\Carbon::parse($rateData['dateTime']) : null,
                    'fetched_at' => $fetchedAt,
                    'fetched_by' => $userId,
                ]);

                $rates->push($rate);
            }
        }

        return $rates;
    }

    /**
     * Map SuperRich denomination string to local denomination_id.
     */
    public function mapDenomination(string $currencyCode, string $srDenom): ?int
    {
        $srDenom = trim($srDenom);
        if (! $srDenom || $srDenom === '-') return null;

        $maps = config('superrich.denom_map', []);

        // Try currency-specific mapping first, then default
        $localLabel = $maps[$currencyCode][$srDenom]
            ?? $maps['_default'][$srDenom]
            ?? null;

        // Normalize: strip spaces around dashes and try again
        if (! $localLabel) {
            $normalized = preg_replace('/\s*-\s*/', '-', $srDenom);
            $localLabel = $maps[$currencyCode][$normalized]
                ?? $maps['_default'][$normalized]
                ?? null;

            // Auto-match: try normalized string directly against DB denom_label
            if (! $localLabel) {
                $directMatch = CurrencyDenomination::where('currency_code', $currencyCode)
                    ->where('denom_label', $normalized)
                    ->value('id');
                if ($directMatch) return $directMatch;
            }
        }

        if (! $localLabel) {
            return null;
        }

        return CurrencyDenomination::where('currency_code', $currencyCode)
            ->where('denom_label', $localLabel)
            ->value('id');
    }

    /**
     * Get latest fetched SuperRich rates (one per denomination, highest denom wins).
     */
    public function getLatestRates(): Collection
    {
        $lastFetch = SuperrichRate::max('fetched_at');
        if (! $lastFetch) return collect();

        return SuperrichRate::where('fetched_at', $lastFetch)
            ->whereNotNull('denomination_id')
            ->orderBy('currency_code')
            ->orderByDesc('rate_buy')
            ->get()
            ->unique('denomination_id'); // keep first (highest rate) per denom
    }

    /**
     * Create a rate batch from latest SuperRich rates + adjustments.
     */
    public function createBatch(int $userId, array $counterIds): SuperrichRateBatch
    {
        $rates = $this->getLatestRates();
        if ($rates->isEmpty()) {
            throw new \RuntimeException('ไม่มีข้อมูลราคา SuperRich — กรุณาดึงราคาก่อน');
        }

        $adjustments = SuperrichAdjustment::all()->keyBy('denomination_id');
        $lastFetch = SuperrichRate::max('fetched_at');

        $ratesData = [];
        foreach ($rates as $sr) {
            $adj = $adjustments->get($sr->denomination_id);
            $adjBuy = $adj ? (float) $adj->adj_rate_buy : 0;
            $adjSell = $adj ? (float) $adj->adj_rate_sell : 0;

            $ratesData[] = [
                'denomination_id' => $sr->denomination_id,
                'currency_code' => $sr->currency_code,
                'superrich_denom' => $sr->superrich_denom,
                'base_buy' => $sr->rate_buy,
                'base_sell' => $sr->rate_sell,
                'adj_buy' => $adjBuy,
                'adj_sell' => $adjSell,
                'final_buy' => round($sr->rate_buy + $adjBuy, 4),
                'final_sell' => round($sr->rate_sell + $adjSell, 4),
            ];
        }

        return SuperrichRateBatch::create([
            'status' => SuperrichRateBatch::STATUS_PENDING,
            'rates_data' => $ratesData,
            'target_counter_ids' => $counterIds,
            'source_fetched_at' => $lastFetch,
            'created_by' => $userId,
        ]);
    }

    /**
     * Approve a pending batch.
     */
    public function approveBatch(SuperrichRateBatch $batch, int $userId): void
    {
        if ($batch->status !== SuperrichRateBatch::STATUS_PENDING) {
            throw new \RuntimeException('สามารถอนุมัติได้เฉพาะชุดราคาที่รออนุมัติ');
        }

        $batch->update([
            'status' => SuperrichRateBatch::STATUS_APPROVED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * Reject a pending batch.
     */
    public function rejectBatch(SuperrichRateBatch $batch, int $userId, ?string $reason = null): void
    {
        if ($batch->status !== SuperrichRateBatch::STATUS_PENDING) {
            throw new \RuntimeException('สามารถปฏิเสธได้เฉพาะชุดราคาที่รออนุมัติ');
        }

        $batch->update([
            'status' => SuperrichRateBatch::STATUS_REJECTED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
            'notes' => $reason,
        ]);
    }

    /**
     * Distribute approved batch rates to target counters.
     */
    public function distributeBatch(SuperrichRateBatch $batch, int $userId): int
    {
        if ($batch->status !== SuperrichRateBatch::STATUS_APPROVED) {
            throw new \RuntimeException('สามารถกระจายได้เฉพาะชุดราคาที่อนุมัติแล้ว');
        }

        $counterIds = $batch->target_counter_ids ?? [];
        $ratesData = $batch->rates_data ?? [];
        $updatedCount = 0;

        DB::transaction(function () use ($counterIds, $ratesData, $userId, $batch, &$updatedCount) {
            foreach ($counterIds as $counterId) {
                foreach ($ratesData as $rate) {
                    if (! $rate['denomination_id']) continue;

                    // Read old rate
                    $old = CounterRate::where('counter_id', $counterId)
                        ->where('denomination_id', $rate['denomination_id'])
                        ->whereDate('rate_date', today())
                        ->first();

                    // Write new rate
                    CounterRate::updateOrCreate(
                        [
                            'counter_id' => $counterId,
                            'denomination_id' => $rate['denomination_id'],
                            'rate_date' => today(),
                        ],
                        [
                            'currency_code' => $rate['currency_code'],
                            'rate_buy' => $rate['final_buy'],
                            'rate_sell' => $rate['final_sell'],
                            'set_by' => $userId,
                        ]
                    );

                    // Log the change
                    RateChangeLog::logChange(
                        counterId: (int) $counterId,
                        denomId: (int) $rate['denomination_id'],
                        currencyCode: $rate['currency_code'],
                        oldBuy: $old ? (float) $old->rate_buy : null,
                        oldSell: $old ? (float) $old->rate_sell : null,
                        newBuy: (float) $rate['final_buy'],
                        newSell: (float) $rate['final_sell'],
                        source: 'superrich',
                        ref: 'batch #' . $batch->id,
                    );

                    $updatedCount++;
                }
            }

            $batch->update([
                'status' => SuperrichRateBatch::STATUS_DISTRIBUTED,
                'distributed_at' => now(),
            ]);
        });

        return $updatedCount;
    }
}
