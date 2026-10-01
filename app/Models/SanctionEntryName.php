<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SanctionEntryName extends Model
{
    public const SCRIPT_TH = 'th';
    public const SCRIPT_LATIN = 'latin';

    protected $fillable = [
        'sanction_entry_id', 'name_raw', 'name_normalized', 'name_soundex', 'script', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function entry()
    {
        return $this->belongsTo(SanctionEntry::class, 'sanction_entry_id');
    }
}
