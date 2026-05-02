<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
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
        $branches = Branch::where('is_active', true)->orderBy('branch_name')->get();
        $roles = Role::orderBy('name')->get();

        return view('admin.users.form', [
            'user'     => new User(),
            'branches' => $branches,
            'roles'    => $roles,
            'isEdit'   => false,
        ]);
    }

    /**
     * Validate and create user.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => 'required|email|unique:users,email',
            'password'  => 'required|string|min:8|confirmed',
            'branch_id' => 'required|exists:branches,id',
            'role_id'   => 'required|exists:roles,id',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');
        $validated['password'] = Hash::make($validated['password']);

        User::create($validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'สร้างผู้ใช้เรียบร้อยแล้ว (User created)');
    }

    /**
     * Show edit form.
     */
    public function edit(User $user)
    {
        $branches = Branch::where('is_active', true)->orderBy('branch_name')->get();
        $roles = Role::orderBy('name')->get();

        return view('admin.users.form', [
            'user'     => $user,
            'branches' => $branches,
            'roles'    => $roles,
            'isEdit'   => true,
        ]);
    }

    /**
     * Save changes to user.
     */
    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name'      => 'required|string|max:255',
            'email'     => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password'  => 'nullable|string|min:8|confirmed',
            'branch_id' => 'required|exists:branches,id',
            'role_id'   => 'required|exists:roles,id',
            'is_active' => 'boolean',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $user->update($validated);

        return redirect()->route('admin.users.index')
            ->with('success', 'แก้ไขผู้ใช้เรียบร้อยแล้ว (User updated)');
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
