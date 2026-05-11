<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'counter_id', 'currency_code', 'denomination_id', 'movement_type',
        'amount', 'unit_price', 'reference_type', 'reference_id',
        'note', 'moved_by', 'moved_at',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
        ];
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }

    public function movedBy()
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
