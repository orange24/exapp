<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransfer extends Model
{
    protected $fillable = [
        'transfer_no', 'transfer_type', 'from_counter_id', 'to_counter_id',
        'currency_code', 'denomination_id', 'amount', 'unit_price',
        'status', 'related_transfer_id', 'note',
        'created_by', 'approved_by', 'transferred_at',
    ];

    protected function casts(): array
    {
        return [
            'transferred_at' => 'datetime',
        ];
    }

    public function fromCounter()
    {
        return $this->belongsTo(Counter::class, 'from_counter_id');
    }

    public function toCounter()
    {
        return $this->belongsTo(Counter::class, 'to_counter_id');
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }

    public function currency()
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'currency_code');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function relatedTransfer()
    {
        return $this->belongsTo(StockTransfer::class, 'related_transfer_id');
    }

    /**
     * Generate transfer number: T{type_prefix}{date}{seq}
     * e.g. TB20260503001 (borrow), TR20260503001 (return), TD20260503001 (disbursement), TI20260503001 (intraday)
     */
    public static function generateTransferNo(string $type): string
    {
        $prefixMap = [
            'borrow' => 'TB',
            'return' => 'TR',
            'disbursement' => 'TD',
            'intraday_return' => 'TI',
        ];

        $prefix = $prefixMap[$type] ?? 'TX';
        $date = now()->format('Ymd');
        $pattern = $prefix . $date;

        $last = static::where('transfer_no', 'like', $pattern . '%')
            ->orderByDesc('transfer_no')
            ->value('transfer_no');

        if ($last) {
            $seq = (int) substr($last, strlen($pattern)) + 1;
        } else {
            $seq = 1;
        }

        return $pattern . str_pad($seq, 3, '0', STR_PAD_LEFT);
    }
}
