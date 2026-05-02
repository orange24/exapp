<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmlaReport extends Model
{
    protected $fillable = [
        'transaction_id', 'customer_id', 'threshold_flag',
        'amount_thb', 'reason', 'status', 'reported_at', 'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'reported_at' => 'datetime',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(TransactionMaster::class, 'transaction_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }
}
