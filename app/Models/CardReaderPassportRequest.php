<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CardReaderPassportRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_READING = 'reading';
    public const STATUS_DONE = 'done';
    public const STATUS_FAILED = 'failed';

    /** เท่ากับอายุข้อมูลบัตร — ในนี้คือกุญแจที่เปิดชิปพาสปอร์ตได้ */
    public const LIFETIME_SECONDS = 120;

    protected $fillable = [
        'counter_id', 'mrz', 'status', 'progress', 'error', 'requested_by', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'mrz' => 'encrypted:array',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeLiveFor(Builder $query, int $counterId): Builder
    {
        return $query->where('counter_id', $counterId)
            ->where('expires_at', '>', now())
            ->orderByDesc('id');
    }

    /** คำขอที่ agent ควรหยิบไปทำ — ยังไม่มีใครเริ่ม และยังไม่หมดอายุ */
    public function scopeWaitingForAgent(Builder $query, int $counterId): Builder
    {
        return $query->liveFor($counterId)->where('status', self::STATUS_PENDING);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
