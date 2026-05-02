<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerDocument extends Model
{
    protected $fillable = ['customer_id', 'doc_type', 'file_path', 'original_name', 'captured_at', 'expiry_date'];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'expiry_date' => 'date',
        ];
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
