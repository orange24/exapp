<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CardReaderRead extends Model
{
    public const KIND_NATIONAL_ID = 'national_id';
    public const KIND_PASSPORT = 'passport';

    /** ไม่มีใครมารับภายในเวลานี้ถือว่าไม่มีใครต้องการ แล้วลบทิ้ง */
    public const LIFETIME_SECONDS = 120;

    protected $fillable = [
        'counter_id', 'device_id', 'kind', 'payload', 'read_at', 'consumed_at', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            // ข้อมูลประชาชนไม่ควรนอนเป็น plaintext แม้จะอยู่แค่สองนาที
            'payload' => 'encrypted:array',
            'read_at' => 'datetime',
            'consumed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function scopeWaitingFor(Builder $query, int $counterId): Builder
    {
        return $query->where('counter_id', $counterId)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('read_at');
    }
}
