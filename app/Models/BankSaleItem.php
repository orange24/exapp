<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * แถวธนบัตรในใบซื้อจากธนาคาร 1 ใบ
 */
class BankSaleItem extends Model
{
    protected $fillable = [
        'bank_sale_id', 'currency_code', 'denomination_id',
        'amount', 'bank_rate', 'total_thb',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'bank_rate' => 'float',
            'total_thb' => 'float',
        ];
    }

    public function bankSale()
    {
        return $this->belongsTo(BankSale::class);
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }
}
