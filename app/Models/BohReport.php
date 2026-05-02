<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BohReport extends Model
{
    protected $fillable = ['branch_id', 'period', 'data', 'status', 'submitted_at', 'submitted_by'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
