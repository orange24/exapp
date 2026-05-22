<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperrichRateBatch extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_DISTRIBUTED = 'distributed';

    protected $fillable = [
        'status', 'rates_data', 'target_counter_ids', 'source_fetched_at',
        'notes', 'created_by', 'reviewed_by', 'reviewed_at', 'distributed_at',
    ];

    protected function casts(): array
    {
        return [
            'rates_data' => 'array',
            'target_counter_ids' => 'array',
            'source_fetched_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'distributed_at' => 'datetime',
        ];
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedByUser()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
