<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkingDay extends Model
{
    protected $fillable = [
        'counter_id', 'work_date', 'opening_thb_cash', 'status',
        'opened_by', 'opened_at', 'closed_by', 'closed_at', 'note',
        'closing_items', 'closing_status', 'closing_approved_by', 'closing_approved_at', 'closing_notes',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'closing_items' => 'array',
            'closing_approved_at' => 'datetime',
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

    public function closingApprovedByUser()
    {
        return $this->belongsTo(User::class, 'closing_approved_by');
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isPendingApproval(): bool
    {
        return $this->status === 'closed' && $this->closing_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->closing_status === 'approved';
    }
}
