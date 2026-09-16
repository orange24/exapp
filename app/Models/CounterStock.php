<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class CounterStock extends Model
{
    /** currency_code ของแถวที่เก็บยอดเงินบาทในลิ้นชัก (denomination_id เป็น null) */
    public const THB = 'THB';

    protected $table = 'counter_stock';

    protected $fillable = [
        'counter_id', 'currency_code', 'denomination_id',
        'quantity', 'hold_amount', 'avg_cost', 'total_cost_value',
    ];

    /**
     * เฉพาะเงินตราต่างประเทศ — ตัดแถวเงินบาทออก
     *
     * เงินบาทใช้ denomination_id = null เป็นตัวแยก ไม่ได้อยู่ในตาราง currencies
     * ด้วยซ้ำ query ฝั่ง FC เกือบทุกจุดกรอง whereNotNull('denomination_id')
     * เองอยู่แล้ว scope นี้มีไว้ให้จุดใหม่ๆ เขียนเจตนาให้ชัดโดยไม่ต้องรู้เบื้องหลัง
     */
    public function scopeForeignCurrency(Builder $query): Builder
    {
        return $query->whereNotNull('denomination_id');
    }

    /** เฉพาะแถวเงินบาทในลิ้นชัก */
    public function scopeThb(Builder $query): Builder
    {
        return $query->where('currency_code', self::THB)->whereNull('denomination_id');
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'currency_code');
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }

    public function getAvailableAttribute(): float
    {
        return $this->quantity - $this->hold_amount;
    }
}
