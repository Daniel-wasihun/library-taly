<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DutyScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    /**
     * List all system roles and their assigned permissions.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->get()->map(function ($role) {
            return [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => $role->users()->count(),
            ];
        });

        $permissions = Permission::all()->map(fn ($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'group' => explode('.', $p->name)[0] ?? 'general',
        ]);

        return response()->json([
            'success' => true,
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    /**
     * Create a new role with permissions.
     */
    public function storeRole(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create(['name' => $validated['name']]);

        if (!empty($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully.',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => 0,
            ],
        ], 201);
    }

    /**
     * Update role name and assigned permissions.
     */
    public function updateRole(Request $request, Role $role): JsonResponse
    {
        if ($role->name === 'Super Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin role and its permissions cannot be modified.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:roles,name,' . $role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->name = $validated['name'];
        $role->save();

        if (isset($validated['permissions'])) {
            $role->syncPermissions($validated['permissions']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully.',
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->pluck('name'),
                'users_count' => $role->users()->count(),
            ],
        ]);
    }

    /**
     * Delete role (prevent deleting built-in Super Admin).
     */
    public function destroyRole(Role $role): JsonResponse
    {
        if ($role->name === 'Super Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin role cannot be deleted.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully.',
        ]);
    }

    /**
     * List available schedule duty tasks.
     */
    public function tasks(): JsonResponse
    {
        $tasks = collect(DutyScheduleService::TASKS)->map(fn ($name, $key) => [
            'key' => $key,
            'name' => $name,
        ])->values();

        return response()->json([
            'success' => true,
            'tasks' => $tasks,
        ]);
    }
}
