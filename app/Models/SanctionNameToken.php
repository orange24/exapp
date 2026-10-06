<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SanctionNameToken extends Model
{
    protected $fillable = ['token', 'document_frequency'];

    protected function casts(): array
    {
        return ['document_frequency' => 'integer'];
    }
}
