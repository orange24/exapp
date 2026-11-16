<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    /** currency_code ของ movement เงินบาทในลิ้นชัก (denomination_id เป็น null) */
    public const THB = 'THB';

    /** movement_type ที่ใช้เฉพาะกับเงินบาท */
    public const THB_IN = 'thb_in';
    public const THB_OUT = 'thb_out';

    protected $fillable = [
        'counter_id', 'currency_code', 'denomination_id', 'movement_type',
        'amount', 'unit_price', 'reference_type', 'reference_id',
        'note', 'moved_by', 'moved_at',
        'flag_cancel', 'cancel_reason', 'cancel_requested_by', 'cancel_requested_at',
        'cancel_approved_by', 'cancel_approved_at',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'cancel_approved_at' => 'datetime',
        ];
    }

    /** เฉพาะเงินตราต่างประเทศ — ตัด movement เงินบาทออก */
    public function scopeForeignCurrency(Builder $query): Builder
    {
        return $query->whereNotNull('denomination_id');
    }

    /** เฉพาะ movement เงินบาทในลิ้นชักที่ยังมีผล — ไม่รวมรายการที่อนุมัติลบแล้ว */
    public function scopeThb(Builder $query): Builder
    {
        return $this->scopeThbWithCancelled($query)->where('flag_cancel', '!=', 'Y');
    }

    /** movement เงินบาททั้งหมด รวมรายการที่ลบแล้ว — ใช้แสดงประวัติ ห้ามใช้คำนวณยอด */
    public function scopeThbWithCancelled(Builder $query): Builder
    {
        return $query->where('currency_code', self::THB)->whereNull('denomination_id');
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

    public function cancelRequestedBy()
    {
        return $this->belongsTo(User::class, 'cancel_requested_by');
    }

    public function cancelApprovedBy()
    {
        return $this->belongsTo(User::class, 'cancel_approved_by');
    }
}
