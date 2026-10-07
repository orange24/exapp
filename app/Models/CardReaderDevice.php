<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CardReaderDevice extends Model
{
    public const STATUS_READY = 'ready';
    public const STATUS_NO_READER = 'no_reader';
    public const STATUS_ERROR = 'error';

    /** ไม่ได้ยินจากเครื่องนานกว่านี้ถือว่าออฟไลน์ — สามเท่าของจังหวะสัญญาณชีพ */
    public const OFFLINE_AFTER_SECONDS = 120;

    protected $fillable = [
        'name', 'counter_id', 'token_hash', 'last_seen_at', 'last_status',
        'last_error', 'agent_version', 'revoked_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at');
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds(self::OFFLINE_AFTER_SECONDS));
    }

    /** ready | error | offline — ใช้เลือกสีไฟสถานะหน้าเคาน์เตอร์ */
    public function health(): string
    {
        if (! $this->isOnline()) {
            return 'offline';
        }

        return $this->last_status === self::STATUS_READY ? 'ready' : 'error';
    }
}
