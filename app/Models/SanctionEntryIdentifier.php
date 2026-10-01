<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SanctionEntryIdentifier extends Model
{
    public const TYPE_NATIONAL_ID = 'national_id';
    public const TYPE_PASSPORT = 'passport';
    public const TYPE_COMPANY_REG = 'company_reg';

    protected $fillable = [
        'sanction_entry_id', 'type', 'value_raw', 'value_normalized', 'issuing_country',
    ];

    public function entry()
    {
        return $this->belongsTo(SanctionEntry::class, 'sanction_entry_id');
    }
}
