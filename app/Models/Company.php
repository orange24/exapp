<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'company_code',
        'company_name',
        'tax_id',
        'license_no',
        'address',
        'phone',
        'is_active',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }
}
