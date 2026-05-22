<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperrichAdjustment extends Model
{
    protected $fillable = [
        'denomination_id', 'adj_rate_buy', 'adj_rate_sell', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'adj_rate_buy' => 'float',
            'adj_rate_sell' => 'float',
        ];
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
