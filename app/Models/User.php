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

    /**
     * เปลี่ยน "สาขาทำงาน" เองได้หรือไม่
     *
     * staff ย้ายสาขาได้อยู่แล้วผ่าน POST /switch-branch (dropdown "สาขาทำงาน"
     * ใน sidebar) เมธอดนี้เคยคืน isAdmin() เฉยๆ ซึ่งขัดกับพฤติกรรมจริง และ
     * layout ก็เลยไปเช็ค role === 'staff' ตรงๆ แทนที่จะใช้เมธอดนี้
     */
    public function canSwitchBranch(): bool
    {
        return $this->isAdmin() || $this->role?->name === 'staff';
    }

    /**
     * เปลี่ยน "เคาน์เตอร์ทำงาน" เองได้หรือไม่
     *
     * แยกจาก canSwitchBranch() เพราะขอบเขตไม่เท่ากัน — branch_manager ย้าย
     * สาขาไม่ได้ แต่ต้องเปลี่ยนเคาน์เตอร์ในสาขาตัวเองได้ ส่วน trader/auditor
     * ไม่ได้ทำรายการหน้าเคาน์เตอร์เลย (canAccessBuySell() เป็น false)
     */
    public function canSwitchCounter(): bool
    {
        return in_array($this->role?->name, ['superadmin', 'admin', 'staff', 'branch_manager'], true);
    }

    /**
     * สาขาที่ผู้ใช้เลือกเป็นสาขาทำงานได้
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Branch>
     */
    public function selectableBranches()
    {
        if (! $this->canSwitchBranch()) {
            return Branch::whereKey($this->branch_id)->get();
        }

        return Branch::orderBy('branch_code')->get();
    }

    /**
     * เคาน์เตอร์ที่ผู้ใช้เลือกเป็นเคาน์เตอร์ทำงานได้
     *
     * ขอบเขตต้องตรงกับที่ POST /switch-counter บังคับไว้ ไม่งั้นหน้าจอจะเสนอ
     * ตัวเลือกที่กด แล้ว firstOrFail() โยน 404 กลับมา
     *
     * staff ใช้ session('working_branch_id') ไม่ใช่ branch_id บน user record
     * เพราะเขาอาจกำลังทำงานอยู่สาขาอื่นที่ไม่ใช่สาขาต้นสังกัด
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Counter>
     */
    public function selectableCounters()
    {
        $query = Counter::where('is_active', true)
            ->with('branch')
            ->orderBy('branch_id')
            ->orderBy('counter_name');

        if ($this->isAdmin()) {
            return $query->get();
        }

        if ($this->role?->name === 'staff') {
            return $query->where('branch_id', session('working_branch_id', $this->branch_id))->get();
        }

        return $query->where('branch_id', $this->branch_id)->get();
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
