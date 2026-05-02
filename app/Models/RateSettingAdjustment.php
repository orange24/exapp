<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RateSettingAdjustment extends Model
{
    protected $fillable = ['setting_master_id', 'denomination_id', 'cal_rate_buy', 'cal_rate_sell'];

    public function settingMaster()
    {
        return $this->belongsTo(RateSettingMaster::class, 'setting_master_id');
    }

    public function denomination()
    {
        return $this->belongsTo(CurrencyDenomination::class, 'denomination_id');
    }
}
