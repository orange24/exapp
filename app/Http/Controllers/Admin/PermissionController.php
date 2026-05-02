<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\RolePermission;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Show permission matrix (roles x modules grid).
     */
    public function index()
    {
        $roles = Role::whereIn('name', ['admin', 'staff', 'auditor'])->get();
        $permissions = Permission::orderBy('module')->orderBy('action')->get();

        $modules = $permissions->groupBy('module');
        $actions = ['read', 'write', 'print', 'export', 'delete'];

        // Build a lookup: role_id => [permission_id => true]
        $assigned = RolePermission::all()
            ->groupBy('role_id')
            ->map(fn ($items) => $items->pluck('permission_id')->flip()->map(fn () => true));

        return view('admin.permissions.index', compact('roles', 'modules', 'actions', 'permissions', 'assigned'));
    }

    /**
     * Save permission matrix changes.
     */
    public function update(Request $request)
    {
        $request->validate([
            'permissions'   => 'nullable|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        $roles = Role::whereIn('name', ['admin', 'staff', 'auditor'])->get();

        foreach ($roles as $role) {
            $rolePermissionIds = $request->input("role.{$role->id}", []);

            // Sync: delete all existing, re-insert selected
            RolePermission::where('role_id', $role->id)->delete();

            foreach ($rolePermissionIds as $permissionId) {
                RolePermission::create([
                    'role_id'       => $role->id,
                    'permission_id' => $permissionId,
                ]);
            }
        }

        return redirect()->route('admin.permissions.index')
            ->with('success', 'บันทึกสิทธิ์เรียบร้อยแล้ว (Permissions saved)');
    }
}
