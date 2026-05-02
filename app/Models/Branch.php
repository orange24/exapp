<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = ['branch_code', 'branch_name', 'type', 'city', 'phone', 'address', 'tenant_code', 'is_active'];

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
