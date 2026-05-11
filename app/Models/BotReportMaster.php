<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotReportMaster extends Model
{
    protected $fillable = [
        'report_month', 'branch_id', 'institution_code', 'license_no',
        'company_name', 'branch_name', 'branch_address', 'status',
        'generated_by', 'generated_at', 'confirmed_by', 'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function generatedByUser()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function transactions()
    {
        return $this->hasMany(BotReportTransaction::class, 'bot_report_id');
    }

    public function buyTransactions()
    {
        return $this->transactions()->where('trns_type', 'BUY');
    }

    public function sellTransactions()
    {
        return $this->transactions()->where('trns_type', 'SELL');
    }
}
