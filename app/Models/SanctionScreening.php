<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SanctionScreening extends Model
{
    public const RESULT_CLEAR = 'clear';
    public const RESULT_POTENTIAL_MATCH = 'potential_match';
    public const RESULT_CONFIRMED_MATCH = 'confirmed_match';

    public const TRIGGER_TRANSACTION = 'transaction';
    public const TRIGGER_CUSTOMER_CREATE = 'customer_create';
    public const TRIGGER_RESCAN = 'rescan';

    public const DECISION_FALSE_POSITIVE = 'false_positive';
    public const DECISION_TRUE_MATCH = 'true_match';
    public const DECISION_ESCALATED = 'escalated';

    protected $fillable = [
        'customer_id', 'transaction_id', 'branch_id', 'counter_id',
        'screened_by', 'screened_at',
        'input_name', 'input_id_type', 'input_id_number', 'input_nationality', 'input_dob',
        'sync_run_id', 'trigger', 'result', 'top_score',
        'decision', 'decided_by', 'decided_at', 'decision_reason',
    ];

    protected function casts(): array
    {
        return [
            'screened_at' => 'datetime',
            'decided_at' => 'datetime',
            'top_score' => 'decimal:2',
        ];
    }

    public function matches()
    {
        return $this->hasMany(SanctionScreeningMatch::class, 'screening_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction()
    {
        return $this->belongsTo(TransactionMaster::class, 'transaction_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function screenedBy()
    {
        return $this->belongsTo(User::class, 'screened_by');
    }

    public function decidedBy()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isBlocked(): bool
    {
        return $this->result === self::RESULT_CONFIRMED_MATCH;
    }

    public function needsDecision(): bool
    {
        return $this->result === self::RESULT_POTENTIAL_MATCH && $this->decision === null;
    }

    public function scopeAwaitingDecision(Builder $query): Builder
    {
        return $query->where('result', self::RESULT_POTENTIAL_MATCH)->whereNull('decision');
    }
}
