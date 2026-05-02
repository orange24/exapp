<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    protected $fillable = [
        'entry_no', 'entry_date', 'description', 'type',
        'source_type', 'source_id', 'branch_id', 'posted_by', 'is_posted',
    ];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
            'is_posted' => 'boolean',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function lines()
    {
        return $this->hasMany(JournalLine::class);
    }
}
