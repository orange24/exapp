<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankSaleSource extends Model
{
    protected $fillable = [
        'bank_sale_id', 'counter_id', 'amount', 'avg_cost',
        'status', 'transit_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'transit_at' => 'datetime',
            'delivered_at' => 'datetime',
            'amount' => 'float',
            'avg_cost' => 'float',
        ];
    }

    public function bankSale()
    {
        return $this->belongsTo(BankSale::class);
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }
}
