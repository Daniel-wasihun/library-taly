<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DutyScheduleService;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionController extends Controller
{
    /**
     * List all system roles and their assigned permissions.
     */
    public function roles(): JsonResponse
    {
        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();

        return response()->json([
            'success' => true,
            'roles' => $roles,
            'permissions' => $permissions,
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
