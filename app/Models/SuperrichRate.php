<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperrichRate extends Model
{
    protected $fillable = [
        'currency_code', 'superrich_denom', 'denomination_id',
        'rate_buy', 'rate_sell', 'api_datetime', 'fetched_at', 'fetched_by',
    ];

    protected function casts(): array
    {
        return [
            'api_datetime' => 'datetime',
            'fetched_at' => 'datetime',
            'rate_buy' => 'float',
            'rate_sell' => 'float',
        ];
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }

    public function fetchedByUser()
    {
        return $this->belongsTo(User::class, 'fetched_by');
    }
}
