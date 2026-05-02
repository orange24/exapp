<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'counter_id', 'currency_code', 'movement_type', 'amount',
        'reference_type', 'reference_id', 'note', 'moved_by', 'moved_at',
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

    public function movedBy()
    {
        return $this->belongsTo(User::class, 'moved_by');
    }
}
