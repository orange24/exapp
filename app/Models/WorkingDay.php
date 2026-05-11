<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkingDay extends Model
{
    protected $fillable = [
        'counter_id', 'work_date', 'status',
        'opened_by', 'opened_at', 'closed_by', 'closed_at', 'note',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function counter()
    {
        return $this->belongsTo(Counter::class);
    }

    public function openedByUser()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedByUser()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
