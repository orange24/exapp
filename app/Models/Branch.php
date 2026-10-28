<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['company_id', 'branch_code', 'branch_name', 'company_name', 'type', 'city', 'phone', 'address', 'tax_id', 'license_no', 'tenant_code', 'is_active', 'cut_off_time'];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function counters()
    {
        return $this->hasMany(Counter::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
