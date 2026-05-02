<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventory';

    protected $fillable = [
        'branch_id', 'currency_code', 'date', 'opening_balance',
        'buy_total', 'sell_total', 'transfer_in', 'transfer_out', 'closing_balance',
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
}
