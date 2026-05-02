<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    protected $fillable = ['account_code', 'name_th', 'name_en', 'type', 'parent_code', 'level', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent()
    {
        return $this->belongsTo(Account::class, 'parent_code', 'account_code');
    }

    public function children()
    {
        return $this->hasMany(Account::class, 'parent_code', 'account_code');
    }

    public function journalLines()
    {
        return $this->hasMany(JournalLine::class);
    }
}
