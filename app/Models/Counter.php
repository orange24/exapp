<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Counter extends Model
{
    protected $fillable = ['counter_code', 'counter_name', 'branch_id', 'tenant_code', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function rates()
    {
        return $this->hasMany(CounterRate::class);
    }

    public function latestRates()
    {
        return $this->hasMany(CounterRate::class)
            ->whereDate('rate_date', today())
            ->orWhere(function ($q) {
                $q->whereNull('rate_date');
            })
            ->latest('updated_at');
    }
}
