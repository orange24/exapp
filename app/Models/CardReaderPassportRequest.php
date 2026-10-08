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

    /**
     * กุญแจที่ใช้ได้จริง — null เมื่อถอดรหัสไม่ออก
     *
     * ถอดไม่ออกเกิดได้เมื่อ APP_KEY เปลี่ยน หรือแถวถูกคัดลอกข้ามสภาพแวดล้อม
     * ถ้าปล่อยให้ exception หลุดออกไป สัญญาณชีพจะล้มทั้งคำขอ แล้วไฟสถานะกับ
     * การอ่านบัตรประชาชนของเคาน์เตอร์นั้นจะตายตามไปด้วย ทั้งที่ไม่เกี่ยวกัน
     *
     * @return array{document_no: string, date_of_birth: string, expiry_date: string}|null
     */
    public function key(): ?array
    {
        try {
            $mrz = $this->mrz;
        } catch (\Throwable $e) {
            return null;
        }

        foreach (['document_no', 'date_of_birth', 'expiry_date'] as $field) {
            if (empty($mrz[$field])) {
                return null;
            }
        }

        return $mrz;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
