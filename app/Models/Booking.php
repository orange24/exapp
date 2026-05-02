<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Booking extends Model
{
    protected $fillable = [
        'customer_id', 'counter_id', 'currency_code', 'amount',
        'rate', 'type', 'status', 'hold_amount', 'expires_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'pending')->where('expires_at', '>', now());
    }
}
