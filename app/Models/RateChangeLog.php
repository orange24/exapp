<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class RateChangeLog extends Model
{
    protected $fillable = [
        'counter_id', 'denomination_id', 'currency_code',
        'old_rate_buy', 'old_rate_sell', 'new_rate_buy', 'new_rate_sell',
        'change_source', 'source_reference', 'changed_by', 'changed_at',
    ];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime'];
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }

    public function changedByUser()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Log a rate change from any source.
     */
    public static function logChange(
        int $counterId,
        int $denomId,
        string $currencyCode,
        ?float $oldBuy,
        ?float $oldSell,
        float $newBuy,
        float $newSell,
        string $source,
        ?string $ref = null,
    ): void {
        // Skip logging if rates didn't actually change
        if ($oldBuy == $newBuy && $oldSell == $newSell) {
            return;
        }

        static::create([
            'counter_id' => $counterId,
            'denomination_id' => $denomId,
            'currency_code' => $currencyCode,
            'old_rate_buy' => $oldBuy,
            'old_rate_sell' => $oldSell,
            'new_rate_buy' => $newBuy,
            'new_rate_sell' => $newSell,
            'change_source' => $source,
            'source_reference' => $ref,
            'changed_by' => Auth::id(),
            'changed_at' => now(),
        ]);
    }
}
