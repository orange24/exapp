<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CounterRate extends Model
{
    protected $fillable = [
        'counter_id', 'currency_code', 'denomination_id', 'rate_buy', 'rate_sell',
        'sell_discount_rate', 'set_by', 'rate_date',
    ];

    protected function casts(): array
    {
        return ['rate_date' => 'datetime'];
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'currency_code');
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }

    public function setBy()
    {
        return $this->belongsTo(User::class, 'set_by');
    }
}
