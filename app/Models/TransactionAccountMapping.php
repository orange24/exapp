<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionAccountMapping extends Model
{
    protected $fillable = [
        'trns_type', 'description',
        'debit_account_id', 'credit_account_id',
        'pl_gain_account_id', 'pl_loss_account_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function debitAccount()
    {
        return $this->belongsTo(Account::class, 'debit_account_id');
    }

    public function creditAccount()
    {
        return $this->belongsTo(Account::class, 'credit_account_id');
    }

    public function plGainAccount()
    {
        return $this->belongsTo(Account::class, 'pl_gain_account_id');
    }

    public function plLossAccount()
    {
        return $this->belongsTo(Account::class, 'pl_loss_account_id');
    }
}
