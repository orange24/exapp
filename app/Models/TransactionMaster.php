<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TransactionMaster extends Model
{
    protected $table = 'transactions_master';

    protected $fillable = [
        'trns_no', 'trns_type', 'counter_id', 'counter_name',
        'customer_id', 'cust_name', 'convert_currency_to',
        'is_discount_booth', 'discount_booth_code', 'flag_cancel',
        'cancel_reason', 'trns_datetime', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'trns_datetime' => 'datetime',
            'is_discount_booth' => 'boolean',
        ];
    }

    // ── Relationships ────────────────────────────────────────────────────

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function details()
    {
        return $this->hasMany(TransactionDetail::class, 'transaction_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Scopes ───────────────────────────────────────────────────────────

    /**
     * Exclude cancelled transactions.
     */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('flag_cancel', '!=', 'Y');
    }

    /**
     * Only transactions with pending cancel request.
     */
    public function scopeCancelPending(Builder $query): Builder
    {
        return $query->where('flag_cancel', 'R');
    }

    // ── Status helpers ───────────────────────────────────────────────────

    public function isCancelled(): bool
    {
        return $this->flag_cancel === 'Y';
    }

    public function isCancelPending(): bool
    {
        return $this->flag_cancel === 'R';
    }

    public function isNormal(): bool
    {
        return $this->flag_cancel === 'N';
    }

    // ── Accessors ────────────────────────────────────────────────────────

    public function getTotalThbAttribute(): float
    {
        return $this->details->sum('total');
    }
}
