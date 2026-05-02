<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateSettingMaster extends Model
{
    protected $fillable = ['group_name', 'main_counter_id', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function mainCounter()
    {
        return $this->belongsTo(Counter::class, 'main_counter_id');
    }

    public function counters()
    {
        return $this->belongsToMany(Counter::class, 'rate_setting_counters', 'setting_master_id', 'counter_id');
    }

    public function adjustments()
    {
        return $this->hasMany(RateSettingAdjustment::class, 'setting_master_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
