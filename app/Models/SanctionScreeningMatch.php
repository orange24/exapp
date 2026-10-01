<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SanctionScreeningMatch extends Model
{
    public const TYPE_EXACT_NATIONAL_ID = 'exact_national_id';
    public const TYPE_EXACT_PASSPORT = 'exact_passport';
    public const TYPE_NAME_EXACT = 'name_exact';
    public const TYPE_TOKEN_CONTAINMENT = 'token_containment';
    public const TYPE_NAME_FUZZY = 'name_fuzzy';
    public const TYPE_SOUNDEX = 'soundex';

    protected $fillable = [
        'screening_id', 'sanction_entry_id', 'match_type', 'score', 'matched_on',
    ];

    protected function casts(): array
    {
        return ['score' => 'decimal:2'];
    }

    public function screening()
    {
        return $this->belongsTo(SanctionScreening::class, 'screening_id');
    }

    public function entry()
    {
        return $this->belongsTo(SanctionEntry::class, 'sanction_entry_id');
    }
}
