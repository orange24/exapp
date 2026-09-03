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
        'last_working_branch_id',
        'role_id',
        'managed_branch_ids',
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
            'managed_branch_ids' => 'array',
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

    public function isBranchManager(): bool
    {
        return $this->role?->name === 'branch_manager';
    }

    public function isTrader(): bool
    {
        return $this->role?->name === 'trader';
    }

    public function canSwitchBranch(): bool
    {
        return $this->isAdmin(); // Only Admin/SuperAdmin can switch branches
    }

    public function getVisibleBranchIds(): array
    {
        if ($this->isAdmin()) {
            return Branch::pluck('id')->toArray(); // All branches
        }

        // Staff can switch which branch they're currently working at
        // (POST /switch-branch, sidebar "สาขาทำงาน" selector) — visibility must
        // follow that, not the branch they were hired into. Otherwise every
        // page built on this (Inventory Dashboard, reports, ...) keeps showing
        // their home branch while they're clocked in somewhere else.
        if ($this->role?->name === 'staff') {
            return [session('working_branch_id', $this->branch_id)];
        }

        return [$this->branch_id]; // Own branch only (Branch Manager, Auditor)
    }

    public function getManagedBranchIds(): array
    {
        if ($this->isAdmin()) {
            return Branch::pluck('id')->toArray(); // All branches
        }

        if ($this->isTrader()) {
            return $this->managed_branch_ids ?? []; // Assigned branches
        }

        return [$this->branch_id]; // Own branch only
    }

    public function canManageBranch(int $branchId): bool
    {
        return in_array($branchId, $this->getManagedBranchIds());
    }

    public function canChangeRates(): bool
    {
        return $this->isAdmin() || $this->isBranchManager();
    }

    public function canApproveCancellations(): bool
    {
        return $this->isAdmin() || $this->isBranchManager();
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

    // Counter Selection Methods
    public function requiresCounterAtLogin(): bool
    {
        return $this->role?->name === 'staff';
    }

    public function requiresCounterForBuySell(): bool
    {
        $role = $this->role?->name;
        return in_array($role, ['staff', 'branch_manager', 'admin', 'superadmin']);
    }

    public function canAccessBuySell(): bool
    {
        $role = $this->role?->name;
        return !in_array($role, ['trader', 'auditor']);
    }

    // Menu Access Methods
    public function getAccessibleMenus()
    {
        if (!$this->role_id) {
            return collect();
        }

        return \Illuminate\Support\Facades\Cache::remember(
            "menu_tree_role_{$this->role_id}",
            3600,
            function () {
                return Menu::whereHas('roles', function ($query) {
                    $query->where('roles.id', $this->role_id);
                })
                    ->where('is_active', true)
                    ->orderBy('parent_id')
                    ->orderBy('order')
                    ->get();
            }
        );
    }

    public function hasMenuAccess(string $menuKey): bool
    {
        return $this->getAccessibleMenus()
            ->where('key', $menuKey)
            ->isNotEmpty();
    }
}
