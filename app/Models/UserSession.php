<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserSession extends Model
{
    protected $fillable = [
        'user_id', 'session_id', 'ip_address', 'user_agent',
        'device_type', 'browser', 'os', 'country', 'city',
        'last_activity', 'is_terminated', 'terminated_by',
        'terminated_at', 'terminated_reason',
    ];

    protected function casts(): array
    {
        return [
            'last_activity' => 'datetime',
            'terminated_at' => 'datetime',
            'is_terminated' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function terminatedBy()
    {
        return $this->belongsTo(User::class, 'terminated_by');
    }
}
