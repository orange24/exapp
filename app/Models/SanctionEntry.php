<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SanctionEntry extends Model
{
    public const LIST_FREEZE_04_UN = 'freeze_04_un';
    public const LIST_FREEZE_05_TH = 'freeze_05_th';
    public const LIST_HR_02 = 'hr_02';
    public const LIST_HR_08 = 'hr_08';

    /**
     * บัญชีที่มีหน้าที่อายัดตามกฎหมาย — เฉพาะสองตัวนี้เท่านั้นที่บล็อกแข็งได้
     * hr_02 / hr_08 เป็นข้อมูลประกอบการประเมินความเสี่ยง ห้ามบล็อก
     */
    public const FREEZE_LISTS = [self::LIST_FREEZE_04_UN, self::LIST_FREEZE_05_TH];

    protected $fillable = [
        'list_code', 'source_ref', 'reference_number', 'notification_number', 'section',
        'name_th', 'name_en', 'aka', 'date_of_birth', 'nationality',
        'address_1', 'address_2', 'phone', 'email',
        'national_id', 'company_registration_number', 'group_name', 'status',
        'listed_on', 'as_of_date', 'content_hash', 'source_row_hash',
        'first_seen_at', 'last_seen_at', 'delisted_at',
    ];

    protected function casts(): array
    {
        return [
            'listed_on' => 'date',
            'as_of_date' => 'date',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'delisted_at' => 'datetime',
        ];
    }

    public function identifiers()
    {
        return $this->hasMany(SanctionEntryIdentifier::class);
    }

    public function names()
    {
        return $this->hasMany(SanctionEntryName::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('delisted_at');
    }

    public function isFreezeList(): bool
    {
        return in_array($this->list_code, self::FREEZE_LISTS, true);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name_en ?: ($this->name_th ?: '(ไม่ระบุชื่อ)');
    }
}
