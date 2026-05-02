<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DenominationLog extends Model
{
    protected $fillable = [
        'counter_id', 'currency_code', 'denomination', 'quantity',
        'subtotal', 'type', 'transaction_id', 'created_by',
    ];

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function transaction()
    {
        return $this->belongsTo(TransactionMaster::class, 'transaction_id');
    }
}
