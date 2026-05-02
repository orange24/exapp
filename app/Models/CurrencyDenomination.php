<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CurrencyDenomination extends Model
{
    protected $fillable = [
        'currency_code', 'denom_label', 'display_name',
        'seq', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'currency_code');
    }

    public function counterRates()
    {
        return $this->hasMany(CounterRate::class, 'denomination_id');
    }
}
