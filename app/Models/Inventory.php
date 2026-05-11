<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventory';

    protected $fillable = [
        'branch_id', 'counter_id', 'currency_code', 'denomination_id', 'date',
        'opening_balance', 'buy_total', 'sell_total', 'transfer_in', 'transfer_out',
        'closing_balance', 'avg_cost', 'total_value',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }
}
