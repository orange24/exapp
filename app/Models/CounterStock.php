<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CounterStock extends Model
{
    protected $table = 'counter_stock';

    protected $fillable = ['counter_id', 'currency_code', 'quantity', 'hold_amount'];

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'currency_code');
    }

    public function getAvailableAttribute(): float
    {
        return $this->quantity - $this->hold_amount;
    }
}
