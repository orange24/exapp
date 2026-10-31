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

    public function isAuditor(): bool
    {
        return $this->role?->name === 'auditor';
    }

    /**
     * นั่งทำรายการที่เคาน์เตอร์จริงหรือไม่
     *
     * auditor ตรวจสอบทุกสาขาและ trader ดูแลคลัง — ทั้งคู่ไม่ได้ประจำเคาน์เตอร์
     * ไหน การขึ้น "เคาน์เตอร์ทำงาน" ให้เขาใน sidebar สื่อผิดว่าเขาผูกอยู่กับ
     * สาขาเดียว ทั้งที่ขอบเขตการมองเห็นของเขาไม่ได้ทำงานแบบนั้น
     */
    public function worksAtACounter(): bool
    {
        return ! $this->isAuditor() && ! $this->isTrader();
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class);
    }

    /**
     * บริษัทที่ผู้ใช้คนนี้เข้าถึงได้
     *
     * admin เข้าได้ทุกบริษัทเสมอ ไม่ต้องไล่ผูกให้ ส่วนคนอื่นดูจากที่ผูกไว้
     * ถ้ายังไม่เคยผูก (ผู้ใช้เดิมทั้งระบบก่อนมีฟีเจอร์นี้) ให้ตกไปใช้บริษัท
     * ของสาขาตัวเอง — ไม่งั้นวันที่ deploy ทุกคนจะเข้าระบบไม่ได้พร้อมกัน
     *
     * @return array<int, int>
     */
    public function accessibleCompanyIds(): array
    {
        if ($this->isAdmin()) {
            return Company::pluck('id')->toArray();
        }

        $assigned = $this->companies()->pluck('companies.id')->toArray();

        if ($assigned !== []) {
            return $assigned;
        }

        $own = $this->branch?->company_id;

        return $own ? [$own] : [];
    }

    /**
     * บริษัทที่กำลังทำงานอยู่
     *
     * ค่าใน session ต้องถูกตรวจกับสิทธิ์ปัจจุบันทุกครั้ง ไม่ใช่เชื่อตามที่เคย
     * ตั้งไว้ — ผู้ใช้ที่ถูกถอดสิทธิ์บริษัทระหว่างที่ยัง login ค้างอยู่ต้อง
     * หลุดออกจากบริษัทนั้นทันที ไม่ใช่รอจนกว่าเขาจะ logout เอง
     */
    public function activeCompanyId(): ?int
    {
        $allowed = $this->accessibleCompanyIds();

        if ($allowed === []) {
            return null;
        }

        $chosen = (int) session('active_company_id');

        return in_array($chosen, $allowed, true) ? $chosen : $allowed[0];
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

        $query = Branch::orderBy('branch_code');

        $companyId = $this->activeCompanyId();
        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        // staff ลงเวรได้เฉพาะสาขาที่ผูกไว้ ถ้ายังไม่เคยผูก (ผู้ใช้เดิมก่อนมี
        // ฟอร์มผูกสาขา) ให้เลือกได้ทุกสาขาเหมือนเดิม ไม่งั้นวัน deploy ทุกคน
        // จะเหลือสาขาให้เลือกแค่ต้นสังกัด
        $assigned = $this->isAdmin() ? [] : $this->assignedBranchIds();
        if ($assigned !== []) {
            $query->whereIn('id', $assigned);
        }

        return $query->get();
    }

    /**
     * สาขาที่ผูกไว้ผ่านฟอร์มผู้ใช้ (user_branches)
     *
     * @return array<int, int>
     */
    public function assignedBranchIds(): array
    {
        return $this->branches()->pluck('branches.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * สาขาที่ staff ควรลงเวรเมื่อยังไม่ได้เลือก (ตอน login หรือหลังสลับบริษัท)
     *
     * ต้องเป็นสาขาในบริษัทที่กำลังทำงานอยู่ — users.branch_id อาจเป็นสาขาของ
     * อีกบริษัท ถ้าใช้ตรง ๆ ทุกหน้าในบริษัทนี้จะว่างเปล่า
     */
    public function defaultWorkingBranchId(): ?int
    {
        $choices = $this->selectableBranches()->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ([$this->last_working_branch_id, $this->branch_id] as $preferred) {
            if ($preferred !== null && in_array((int) $preferred, $choices, true)) {
                return (int) $preferred;
            }
        }

        return $choices[0] ?? null;
    }

    /**
     * ลงเวรที่สาขานี้ พร้อมเลือกเคาน์เตอร์แรกที่เปิดอยู่ให้
     */
    public function seatAtWorkingBranch(?int $branchId): void
    {
        if ($branchId === null) {
            return;
        }

        session(['working_branch_id' => $branchId]);

        // จำไว้ให้ login ครั้งหน้ากลับมาที่สาขา (และบริษัท) เดิม
        if ((int) $this->last_working_branch_id !== $branchId) {
            $this->update(['last_working_branch_id' => $branchId]);
        }

        $counter = Counter::where('branch_id', $branchId)->where('is_active', true)->first();
        if ($counter) {
            session(['working_counter_id' => $counter->id, 'working_counter_name' => $counter->counter_name]);
        }
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
        return $this->narrowToActiveCompany($this->branchIdsByRole());
    }

    /**
     * ตัดสาขาที่ไม่ได้อยู่ในบริษัทที่กำลังทำงานอยู่ทิ้ง
     *
     * นี่คือจุดเดียวที่ทั้งระบบถูก scope ตามบริษัท — หน้า inventory, รายงาน,
     * การโอนสต็อก และอื่น ๆ เรียก getVisibleBranchIds() อยู่แล้วทั้งหมด
     * การบีบที่นี่จึงได้ผลทั่วระบบโดยไม่ต้องไล่แก้ทีละหน้า
     *
     * @param  array<int, int>  $branchIds
     * @return array<int, int>
     */
    private function narrowToActiveCompany(array $branchIds): array
    {
        $companyId = $this->activeCompanyId();

        if ($companyId === null) {
            return $branchIds;
        }

        return Branch::whereIn('id', $branchIds)
            ->where('company_id', $companyId)
            ->pluck('id')->toArray();
    }

    /**
     * ขอบเขตสาขาตาม role อย่างเดียว ยังไม่คิดเรื่องบริษัท
     *
     * @return array<int, int>
     */
    private function branchIdsByRole(): array
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

        // auditor ตรวจสอบทุกสาขา ไม่ได้ประจำสาขาไหน — ถ้าจำกัดไว้ที่สาขาเดียว
        // เขาจะตรวจได้แค่สาขาที่บังเอิญถูกผูกไว้ตอนสร้างผู้ใช้ และถ้าไม่ผูก
        // สาขาเลย (branch_id = null) เขาจะไม่เห็นอะไรเลยสักสาขา
        //
        // trader ก็เช่นกัน — ฟอร์มผู้ใช้ให้ trader เลือกแค่บริษัท ไม่ได้เลือกสาขา
        // จึงต้องเห็นทุกสาขาของบริษัทที่กำลังทำงานอยู่ (บีบต่อใน narrowToActiveCompany)
        if ($this->isAuditor() || $this->isTrader()) {
            return Branch::pluck('id')->toArray();
        }

        return $this->ownBranchIds(); // Branch Manager
    }

    /**
     * สาขาที่ประจำอยู่ — ตามที่ผูกไว้ในฟอร์มผู้ใช้ ถ้ายังไม่เคยผูกใช้ต้นสังกัด
     *
     * @return array<int, int>
     */
    private function ownBranchIds(): array
    {
        $assigned = $this->assignedBranchIds();

        return $assigned !== [] ? $assigned : [$this->branch_id];
    }

    public function getManagedBranchIds(): array
    {
        if ($this->isAdmin()) {
            return Branch::pluck('id')->toArray(); // All branches
        }

        if ($this->isTrader()) {
            return $this->managed_branch_ids ?? []; // Assigned branches
        }

        return $this->ownBranchIds();
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

    /**
     * ตั้งค่าเครื่องอ่านบัตรประชาชนที่เคาน์เตอร์ได้หรือไม่
     *
     * ไม่ใช่สิทธิ์ module1/write เพียวๆ เพราะ staff ไม่ได้ถือสิทธิ์จัดการระบบ
     * แต่เครื่องอ่านตั้งอยู่ตรงหน้าเขา ถ้าต้องรอ admin ลงทะเบียนให้ทุกครั้ง
     * เคาน์เตอร์จะหยุดรอทั้งที่งานนี้ใช้เวลาสิบวินาที — สิ่งที่กันไว้แทนคือ
     * ขอบเขตสาขา ไม่ใช่ตัวสิทธิ์เข้าหน้า (ดู getVisibleBranchIds())
     *
     * auditor ถือ read ทุก module แต่ไม่ได้นั่งเคาน์เตอร์ จึงไม่เข้าข่าย
     */
    public function canManageCardReaders(): bool
    {
        return $this->hasPermission('module1', 'write') || $this->role?->name === 'staff';
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
