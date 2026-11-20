<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\DutyScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected DutyScheduleService $dutyService
    ) {}

    /**
     * List all users with their roles and scheduled duty status.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('users.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Permission required to view user accounts.',
            ], 403);
        }

        $search = $request->query('search');
        $roleFilter = $request->query('role');
        $statusFilter = $request->query('status');
        $libraryFilter = $request->query('library_id');

        $query = User::with(['roles', 'permissions', 'library'])
            // Super Admin must not be visible in the user list
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Super Admin'))
            ->latest();

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhere('username', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            });
        }

        if ($roleFilter && $roleFilter !== 'all') {
            $query->role($roleFilter);
        }

        if ($statusFilter && $statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($libraryFilter && $libraryFilter !== 'all') {
            if ($libraryFilter === 'general' || $libraryFilter === 'null') {
                $query->whereNull('library_id');
            } else {
                $query->where('library_id', $libraryFilter);
            }
        }

        $users = $query->paginate(15);

        $items = collect($users->items())->map(function ($u) {
            $dutyStatus = $this->dutyService->evaluateUserDutyStatus($u, 'attendance_desk');
            return [
                'id' => $u->id,
                'name' => $u->name,
                'username' => $u->username,
                'email' => $u->email,
                'phone' => $u->phone,
                'status' => $u->status,
                'is_admin' => $u->hasAdminPrivilege(),
                'library_id' => $u->library_id,
                'library' => $u->library ? [
                    'id' => $u->library->id,
                    'name' => $u->library->name,
                    'name_am' => $u->library->name_am,
                    'code' => $u->library->code,
                ] : null,
                'can_manage_all_libraries' => $u->canManageAllLibraries(),
                'roles' => $u->roles->map(fn ($r) => ['id' => $r->id, 'name' => $r->name]),
                'direct_permissions' => $u->getDirectPermissionsList(),
                'all_permissions' => $u->getAllPermissionsList(),
                'duty_status' => $dutyStatus,
                'created_at' => $u->created_at?->toISOString(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $items,
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
                'per_page' => $users->perPage(),
            ],
        ]);
    }

    /**
     * Create a new user with unique username, email, full name, password, and Spatie role.
     */
    public function store(Request $request): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasPermissionTo('users.create')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Permission required to create user accounts.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'library_id' => ['nullable', 'exists:libraries,id'],
            'status' => ['nullable', 'string', 'in:active,inactive,suspended'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validated['role'] === 'Super Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin role cannot be assigned.',
            ], 403);
        }

        $user = User::create([
            'name' => $validated['name'],
            'username' => strtolower($validated['username']),
            'email' => strtolower($validated['email']),
            'password' => Hash::make($validated['password']),
            'library_id' => $validated['library_id'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'phone' => $validated['phone'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        Cache::forget('dashboard_stats');

        return response()->json([
            'success' => true,
            'message' => 'User created successfully.',
            'user' => $user->load(['roles', 'library']),
        ], 201);
    }

    /**
     * Show single user details.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasPermissionTo('users.view')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Permission required to view user details.',
            ], 403);
        }

        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Super admin profile cannot be viewed or modified.',
            ], 403);
        }

        return response()->json([
            'success' => true,
            'user' => $user->load(['roles', 'permissions', 'dutySchedules', 'library']),
        ]);
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasPermissionTo('users.update')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Permission required to update user accounts.',
            ], 403);
        }

        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Super admin account and permissions cannot be modified by any means.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'alpha_dash', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', 'string', 'exists:roles,name'],
            'library_id' => ['nullable', 'exists:libraries,id'],
            'status' => ['required', 'string', 'in:active,inactive,suspended'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        if ($validated['role'] === 'Super Admin') {
            return response()->json([
                'success' => false,
                'message' => 'Super Admin role cannot be assigned.',
            ], 403);
        }

        $user->name = $validated['name'];
        $user->username = strtolower($validated['username']);
        $user->email = strtolower($validated['email']);
        $user->library_id = $validated['library_id'] ?? null;
        $user->status = $validated['status'];
        $user->phone = $validated['phone'] ?? null;

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();
        $user->syncRoles([$validated['role']]);

        $this->dutyService->clearUserScheduleCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully.',
            'user' => $user->load(['roles', 'library']),
        ]);
    }

    /**
     * Delete user (prevent deleting self and super admin).
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasPermissionTo('users.delete')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Permission required to delete user accounts.',
            ], 403);
        }

        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Super admin account cannot be deleted.',
            ], 403);
        }

        if ($currentUser->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

        $this->dutyService->clearUserScheduleCache($user->id);
        $user->delete();
        Cache::forget('dashboard_stats');

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully.',
        ]);
    }

    /**
     * Override direct permissions on a user (Spatie syncPermissions).
     */
    public function overridePermissions(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user();
        if (!$currentUser || !$currentUser->hasPermissionTo('users.update')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Permission required to override user permissions.',
            ], 403);
        }

        if ($user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Super admin permissions cannot be modified by any means.',
            ], 403);
        }

        $validated = $request->validate([
            'permissions' => ['required', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $user->syncPermissions($validated['permissions']);

        $this->dutyService->clearUserScheduleCache($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Direct permissions updated successfully.',
            'direct_permissions' => $user->getDirectPermissionsList(),
            'all_permissions' => $user->getAllPermissionsList(),
        ]);
    }
}
