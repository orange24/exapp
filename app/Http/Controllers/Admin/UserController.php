<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserController extends Controller
{
    /** บริษัทที่ติ๊กไว้ให้ตั้งแต่เปิดฟอร์มเพิ่มผู้ใช้ */
    private const DEFAULT_COMPANY_CODE = 'YESSOUTH';

    /**
     * List users with branch, role info.
     */
    public function index()
    {
        $users = User::with(['branch', 'role'])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    /**
     * Show form to add user.
     */
    public function create()
    {
        return view('admin.users.form', $this->formData(new User(), false));
    }

    public function store(Request $request)
    {
        $validated = $this->validateUser($request);
        $validated['password'] = Hash::make($validated['password']);

        $user = new User();
        $user->fill($validated);
        $user->branch_id = $this->homeBranchId($request, null);
        $user->save();

        $this->syncAssignment($request, $user);

        return redirect()->route('admin.users.index')
            ->with('success', 'สร้างผู้ใช้เรียบร้อยแล้ว (User created)');
    }

    /**
     * Show edit form.
     */
    public function edit(User $user)
    {
        return view('admin.users.form', $this->formData($user, true));
    }

    /**
     * Save changes to user.
     */
    public function update(Request $request, User $user)
    {
        $validated = $this->validateUser($request, $user);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->fill($validated);
        $user->branch_id = $this->homeBranchId($request, $user->branch_id);
        $user->save();

        $this->syncAssignment($request, $user);

        return redirect()->route('admin.users.index')
            ->with('success', 'แก้ไขผู้ใช้เรียบร้อยแล้ว (User updated)');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(User $user, bool $isEdit): array
    {
        $companies = Company::where('is_active', true)
            ->with(['branches' => fn ($q) => $q->where('is_active', true)->orderBy('branch_code')])
            ->orderBy('company_code')
            ->get();

        // สาขาที่ยังไม่มีบริษัท (ระบบที่ยังไม่ได้ตั้งบริษัทเลย) ต้องเลือกได้ด้วย
        $looseBranches = Branch::where('is_active', true)->whereNull('company_id')
            ->orderBy('branch_code')->get();

        return [
            'user'          => $user,
            'companies'     => $companies,
            'looseBranches' => $looseBranches,
            'roles'         => Role::orderBy('name')->get(),
            'isEdit'        => $isEdit,
            'companyIds'    => $user->exists
                ? $user->companies()->pluck('companies.id')->all()
                : $companies->where('company_code', self::DEFAULT_COMPANY_CODE)->pluck('id')->all(),
            'branchIds'     => $user->exists ? $user->assignedBranchIds() : [],
        ];
    }

    /**
     * role ที่ส่งมาในฟอร์ม ห่อเป็น User ชั่วคราวเพื่อถามคุณสมบัติของ role
     */
    private function probe(Request $request): User
    {
        $role = Role::find($request->input('role_id'));

        $probe = new User(['role_id' => $role?->id]);
        $probe->setRelation('role', $role);

        return $probe;
    }

    /**
     * ต้องเลือกบริษัทไหม — admin เข้าได้ทุกบริษัทอยู่แล้ว และระบบที่ยังไม่มี
     * บริษัทเลยก็ไม่มีอะไรให้เลือก
     */
    private function needsCompanies(Request $request): bool
    {
        return ! $this->probe($request)->isAdmin()
            && Company::where('is_active', true)->exists();
    }

    /**
     * ต้องเลือกสาขาไหม — staff/branch_manager ทำงานประจำสาขา ส่วน auditor/
     * trader ดูทุกสาขาของบริษัทที่ผูกไว้ และ admin ดูทุกสาขาอยู่แล้ว
     */
    private function needsBranches(Request $request): bool
    {
        $probe = $this->probe($request);

        return $probe->worksAtACounter() && ! $probe->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateUser(Request $request, ?User $user = null): array
    {
        $needsCompanies = $this->needsCompanies($request);
        $needsBranches = $this->needsBranches($request);

        $validator = validator($request->all(), [
            'name'          => 'required|string|max:255',
            'email'         => ['required', 'email', $user ? Rule::unique('users')->ignore($user->id) : Rule::unique('users')],
            'password'      => $user ? 'nullable|string|min:8|confirmed' : 'required|string|min:8|confirmed',
            'role_id'       => 'required|exists:roles,id',
            'is_active'     => 'boolean',
            'company_ids'   => [$needsCompanies ? 'required' : 'nullable', 'array'],
            'company_ids.*' => 'integer|exists:companies,id',
            'branch_ids'    => [$needsBranches ? 'required' : 'nullable', 'array'],
            'branch_ids.*'  => 'integer|exists:branches,id',
        ], [
            'company_ids.required' => 'กรุณาเลือกบริษัทอย่างน้อย 1 บริษัท',
            'branch_ids.required'  => 'กรุณาเลือกสาขาอย่างน้อย 1 สาขา',
        ]);

        $validator->after(function (Validator $v) use ($request, $needsCompanies, $needsBranches) {
            if (! $needsCompanies || ! $needsBranches || $v->errors()->isNotEmpty()) {
                return;
            }

            $companyIds = array_map('intval', $request->input('company_ids', []));
            $branchCompany = Branch::whereIn('id', $request->input('branch_ids', []))
                ->pluck('company_id', 'id')->map(fn ($id) => (int) $id);

            if ($branchCompany->contains(fn ($companyId) => ! in_array($companyId, $companyIds, true))) {
                $v->errors()->add('branch_ids', 'มีสาขาที่ไม่อยู่ในบริษัทที่เลือก');

                return;
            }

            // ติ๊กบริษัทแต่ไม่มีสาขา สลับไปบริษัทนั้นแล้วจะไม่มีที่ลงเวร
            $empty = Company::whereIn('id', array_diff($companyIds, $branchCompany->all()))->pluck('company_name');
            if ($empty->isNotEmpty()) {
                $v->errors()->add('branch_ids', 'เลือกสาขาของ ' . $empty->implode(', ') . ' อย่างน้อย 1 สาขา');
            }
        });

        $validated = $validator->validate();
        $validated['is_active'] = $request->boolean('is_active');
        unset($validated['company_ids'], $validated['branch_ids']);

        return $validated;
    }

    /**
     * สาขาต้นสังกัด (users.branch_id) — หลายหน้ายังใช้เป็นค่าตั้งต้น
     *
     * คนที่ประจำสาขาใช้สาขาเดิมถ้ายังอยู่ในรายการ ไม่งั้นสาขาแรกที่เลือก
     * ส่วนคนที่ไม่ได้ประจำสาขาคงค่าเดิมไว้ (ผู้ใช้ใหม่ = ว่าง)
     */
    private function homeBranchId(Request $request, ?int $current): ?int
    {
        if (! $this->needsBranches($request)) {
            return $current;
        }

        $branchIds = array_map('intval', $request->input('branch_ids', []));

        return in_array($current, $branchIds, true) ? $current : ($branchIds[0] ?? $current);
    }

    private function syncAssignment(Request $request, User $user): void
    {
        $user->companies()->sync(
            $this->needsCompanies($request) ? array_map('intval', $request->input('company_ids', [])) : []
        );

        $user->branches()->sync(
            $this->needsBranches($request) ? array_map('intval', $request->input('branch_ids', [])) : []
        );
    }

    /**
     * Toggle is_active status.
     */
    public function toggleActive(User $user)
    {
        $user->update(['is_active' => !$user->is_active]);

        $status = $user->is_active ? 'เปิดใช้งาน' : 'ปิดใช้งาน';

        return redirect()->route('admin.users.index')
            ->with('success', "{$user->name} — {$status}");
    }
}
