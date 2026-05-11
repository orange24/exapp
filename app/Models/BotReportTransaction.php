<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BotReportTransaction extends Model
{
    protected $fillable = [
        'bot_report_id', 'source_transaction_id', 'source_detail_id',
        'trns_type', 'trns_date', 'customer_type', 'customer_name',
        'id_type_code', 'id_number', 'nationality', 'purpose',
        'fx_point', 'fx_channel', 'currency_code', 'exchange_rate',
        'fx_amount', 'thb_point', 'thb_channel', 'thb_amount', 'remark',
    ];

    protected function casts(): array
    {
        return ['trns_date' => 'date'];
    }

    public function report()
    {
        return $this->belongsTo(BotReportMaster::class, 'bot_report_id');
    }
}
