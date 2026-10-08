<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class Menu extends Model
{
    protected $fillable = [
        'key',
        'label_th',
        'label_en',
        'route',
        'icon',
        'parent_id',
        'order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    // Relationships
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'menu_role');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orderBy('order');
    }

    public function scopeForRole($query, int $roleId)
    {
        return $query->whereHas('roles', function ($q) use ($roleId) {
            $q->where('roles.id', $roleId);
        });
    }

    // Helpers
    public function isParent(): bool
    {
        return $this->children()->exists();
    }

    public function hasRoute(): bool
    {
        return !empty($this->route);
    }

    /**
     * ล้าง cache เมนูของทุก role
     *
     * getAccessibleMenus() จำผลไว้ชั่วโมงหนึ่งต่อ role การเพิ่มเมนูลงฐานข้อมูล
     * ไม่ได้ไปแตะ cache นั้น เมนูใหม่จึงไม่โผล่จนกว่าจะครบชั่วโมง — ซึ่งดูเหมือน
     * เมนูเสียมากกว่าดูเหมือนรอ cache แล้วคนจะไปไล่หาสาเหตุผิดที่
     *
     * seeder ที่แตะเมนูหรือสิทธิ์ของ role ต้องเรียกตัวนี้ปิดท้ายเสมอ
     */
    public static function flushAccessCache(): void
    {
        foreach (Role::pluck('id') as $roleId) {
            Cache::forget("menu_tree_role_{$roleId}");
        }
    }
}
