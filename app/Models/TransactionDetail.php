<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionDetail extends Model
{
    protected $table = 'transactions_detail';

    protected $fillable = [
        'transaction_id', 'currency_code', 'denomination_id', 'currency_name',
        'unit_price', 'amount', 'total', 'discount_rate_sell', 'created_by',
    ];

    public function transaction()
    {
        return $this->belongsTo(TransactionMaster::class, 'transaction_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'currency_code');
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }
}
