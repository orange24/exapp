<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SanctionFpClearance extends Model
{
    protected $fillable = [
        'customer_id', 'sanction_entry_id', 'cleared_by', 'cleared_at', 'reason',
        'entry_content_hash', 'customer_identity_hash',
    ];

    protected function casts(): array
    {
        return ['cleared_at' => 'datetime'];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function entry()
    {
        return $this->belongsTo(SanctionEntry::class, 'sanction_entry_id');
    }

    /**
     * ตัวตนของลูกค้าที่มีผลต่อการ match — ถ้าเปลี่ยน clearance ต้องหมดอายุ
     * ใช้ทั้งตอนสร้าง clearance และตอนตรวจว่า clearance ยังใช้ได้ไหม
     */
    public static function identityHashFor(Customer $customer): string
    {
        return hash('sha256', implode('|', [
            (string) $customer->name_th,
            (string) $customer->name_en,
            (string) $customer->first_name,
            (string) $customer->last_name,
            (string) $customer->id_type,
            (string) $customer->id_number,
            (string) $customer->nationality,
            (string) $customer->date_of_birth,
        ]));
    }
}
