<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Currency extends Model
{
    protected $fillable = [
        'currency_code', 'currency_name', 'currency_name_th', 'country', 'country_flag',
        'rate_buy', 'rate_sell', 'sell_discount_rate', 'seq', 'is_active',
        'is_default', 'target_currency', 'tenant_code',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_default' => 'boolean'];
    }

    public function denominations()
    {
        return $this->hasMany(CurrencyDenomination::class, 'currency_code', 'currency_code')->orderBy('seq');
    }

    public function counterRates()
    {
        return $this->hasMany(CounterRate::class, 'currency_code', 'currency_code');
    }

    public function getFlagUrlAttribute(): string
    {
        $flag = $this->country_flag ?? strtolower($this->currency_code) . '.png';
        return asset('images/flags/' . $flag);
    }
}
