<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['setting_key', 'setting_value', 'description', 'tenant_code'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::where('setting_key', $key)->first();
        return $setting?->setting_value ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(
            ['setting_key' => $key],
            ['setting_value' => $value]
        );
    }
}
