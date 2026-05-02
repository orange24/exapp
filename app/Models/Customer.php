<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'type', 'id_type', 'id_number', 'name_th', 'name_en',
        'first_name', 'last_name', 'nationality', 'date_of_birth',
        'passport_expiry', 'passport_mrz', 'passport_photo',
        'phone', 'address', 'kyc_status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'passport_expiry' => 'date',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->name_th ?? $this->name_en ?? ($this->first_name . ' ' . $this->last_name);
    }

    public function documents()
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function transactions()
    {
        return $this->hasMany(TransactionMaster::class, 'customer_id');
    }
}
