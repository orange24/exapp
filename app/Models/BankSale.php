<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankSale extends Model
{
    const STATUS_DRAFT = 'draft';
    const STATUS_RESERVED = 'reserved';
    const STATUS_IN_TRANSIT = 'in_transit';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /** ฝั่งซื้อไม่มีการ reserve สต็อก จึงข้าม reserved/in_transit/delivered ไปเลย */
    const STATUS_ORDERED = 'ordered';

    const DIRECTION_SELL = 'sell';
    const DIRECTION_BUY = 'buy';

    const SETTLEMENT_PENDING = 'pending';

    protected $fillable = [
        'sale_no', 'direction', 'bank_name', 'bank_account', 'currency_code', 'denomination_id',
        'total_amount', 'bank_rate', 'total_thb', 'avg_cost_at_sale', 'total_cost',
        'profit_loss', 'settlement_method', 'status', 'notes',
        'journal_entry_id', 'created_by', 'approved_by', 'approved_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
            'total_amount' => 'float',
            'bank_rate' => 'float',
            'total_thb' => 'float',
            'avg_cost_at_sale' => 'float',
            'total_cost' => 'float',
            'profit_loss' => 'float',
        ];
    }

    public function sources()
    {
        return $this->hasMany(BankSaleSource::class);
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }

    public function journalEntry()
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedByUser()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopeDirection($query, string $direction)
    {
        return $query->where('direction', $direction);
    }

    public function isBuy(): bool
    {
        return $this->direction === self::DIRECTION_BUY;
    }

    /** ชื่อเอกสารตามทิศทาง — คอลัมน์ยังชื่อ sale_no ทั้งคู่ */
    public function docNo(): string
    {
        return $this->sale_no;
    }

    public function label(): string
    {
        return $this->isBuy() ? 'ซื้อจากธนาคาร' : 'ขายให้ธนาคาร';
    }

    public static function generateSaleNo(string $direction = self::DIRECTION_SELL): string
    {
        $prefix = $direction === self::DIRECTION_BUY ? 'BP' : 'BS';
        $today = now()->format('Ymd');
        $last = static::where('sale_no', 'like', $prefix . $today . '%')
            ->orderByDesc('sale_no')->first();
        $seq = $last ? (int) substr($last->sale_no, -4) + 1 : 1;
        return $prefix . $today . str_pad($seq, 4, '0', STR_PAD_LEFT);
    }
}
