<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'branch_id',
        'role_id',
        'is_active',
        'tenant_code',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function sessions()
    {
        return $this->hasMany(UserSession::class);
    }

    public function activeSessions()
    {
        return $this->hasMany(UserSession::class)->where('is_terminated', false);
    }

    public function isAdmin(): bool
    {
        return $this->role?->name === 'admin' || $this->role?->name === 'superadmin';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->name === 'superadmin';
    }

    public function branches()
    {
        return $this->belongsToMany(Branch::class, 'user_branches');
    }

    public function hasPermission(string $module, string $action): bool
    {
        return $this->role?->permissions()
            ->where('module', $module)
            ->where('action', $action)
            ->exists() ?? false;
    }
}
