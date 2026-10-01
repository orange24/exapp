<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SanctionSyncRun extends Model
{
    public const STATUS_RUNNING = 'running';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';
    public const STATUS_ABORTED_SANITY_CHECK = 'aborted_sanity_check';

    protected $fillable = [
        'list_code', 'started_at', 'finished_at', 'status', 'source_as_of',
        'source_adapter', 'forced_by',
        'entries_before', 'entries_parsed', 'entries_added', 'entries_removed', 'entries_updated',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'source_as_of' => 'date',
        ];
    }

    public function forcedBy()
    {
        return $this->belongsTo(User::class, 'forced_by');
    }

    public function hasEntryChanges(): bool
    {
        return ($this->entries_added + $this->entries_removed + $this->entries_updated) > 0;
    }
}
