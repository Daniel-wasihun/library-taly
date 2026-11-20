<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Library;
use App\Models\ShiftGroup;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ShiftGroupController extends Controller
{
    /**
     * List all shift groups.
     * Shift groups belong to all library branches by default (library_id = null).
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermissionTo('schedules.view') && !$user->hasPermissionTo('libraries.view'))) {
            return response()->json(['message' => 'Unauthorized: Permission to view shift groups required.'], 403);
        }

        $libraryId = $request->query('library_id');

        $query = ShiftGroup::with([
            'library',
            'users:id,name,username,email,library_id',
            'users.library:id,name,code',
        ])
            ->withCount('users')
            ->orderBy('name', 'asc');

        if ($libraryId) {
            $query->where(function ($q) use ($libraryId) {
                $q->whereNull('library_id')->orWhere('library_id', $libraryId);
            });
        }

        $groups = $query->get();

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    /**
     * Create a new shift group.
     * "if the Group A is created it must belong to all library branches by default"
     * "one librarian must not be in more than one group at a time"
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.manage')) {
            return response()->json(['message' => 'Unauthorized: Permission to create shift groups required.'], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('shift_groups', 'name'),
            ],
            'library_id' => ['nullable', 'exists:libraries,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $targetLibraryId = !empty($validated['library_id']) ? (int)$validated['library_id'] : null;

        // Ensure no staff member is in more than one group at the same time
        if (!empty($validated['user_ids'])) {
            $this->validateNoDuplicateGroupMembership($validated['user_ids']);
        }

        $group = ShiftGroup::create([
            'name' => $validated['name'],
            'library_id' => $targetLibraryId, // null = all branches by default
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['user_ids'])) {
            $group->users()->sync($validated['user_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => "Shift Group '{$group->name}' created successfully (belongs to all branches).",
            'data' => $group->load(['library', 'users:id,name,username,email,library_id', 'users.library:id,name,code']),
        ], 201);
    }

    /**
     * Update an existing shift group and its members.
     */
    public function update(Request $request, ShiftGroup $shiftGroup): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.manage')) {
            return response()->json(['message' => 'Unauthorized: Permission to update shift groups required.'], 403);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('shift_groups', 'name')->ignore($shiftGroup->id),
            ],
            'library_id' => ['nullable', 'exists:libraries,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $targetLibraryId = array_key_exists('library_id', $validated)
            ? (!empty($validated['library_id']) ? (int)$validated['library_id'] : null)
            : $shiftGroup->library_id;

        if (isset($validated['user_ids'])) {
            $this->validateNoDuplicateGroupMembership($validated['user_ids'], $shiftGroup->id);
        }

        $shiftGroup->update([
            'name' => $validated['name'],
            'library_id' => $targetLibraryId,
            'description' => $validated['description'] ?? $shiftGroup->description,
            'is_active' => $validated['is_active'] ?? $shiftGroup->is_active,
        ]);

        if (isset($validated['user_ids'])) {
            $shiftGroup->users()->sync($validated['user_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => "Shift Group '{$shiftGroup->name}' updated successfully.",
            'data' => $shiftGroup->load(['library', 'users:id,name,username,email,library_id', 'users.library:id,name,code']),
        ]);
    }

    /**
     * Delete a shift group.
     */
    public function destroy(Request $request, ShiftGroup $shiftGroup): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.manage')) {
            return response()->json(['message' => 'Unauthorized: Permission to delete shift groups required.'], 403);
        }

        if ($shiftGroup->schedules()->exists()) {
            return response()->json([
                'message' => 'Cannot delete shift group with active scheduled shifts. Remove or archive its schedules first.',
            ], 422);
        }

        $shiftGroup->users()->detach();
        $shiftGroup->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shift group deleted successfully.',
        ]);
    }

    /**
     * Get eligible staff members (Librarians and Attendants) for group assignment.
     * Shows current group assignment if any, to guarantee 1 group per librarian.
     */
    public function eligibleStaff(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.manage')) {
            return response()->json(['message' => 'Unauthorized: Permission to view eligible staff required.'], 403);
        }
        $currentGroupId = $request->query('current_group_id') ? (int)$request->query('current_group_id') : null;

        $users = User::role(['Attendant', 'Librarian', 'Manager'])
            ->with(['library:id,name,code', 'shiftGroups:id,name'])
            ->select('id', 'name', 'username', 'email', 'library_id')
            ->orderBy('name')
            ->get()
            ->map(function ($u) use ($currentGroupId) {
                $assignedGroup = $u->shiftGroups->first();
                $isAvailable = !$assignedGroup || ($currentGroupId && $assignedGroup->id === $currentGroupId);

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'email' => $u->email,
                    'library_id' => $u->library_id,
                    'library' => $u->library,
                    'current_group_id' => $assignedGroup?->id,
                    'current_group_name' => $assignedGroup?->name,
                    'is_available' => $isAvailable,
                    'is_compatible' => true, // Groups are global, all staff are compatible with any group!
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Strictly enforce: One librarian cannot belong to more than one group at a time.
     */
    protected function validateNoDuplicateGroupMembership(array $userIds, ?int $currentGroupId = null): void
    {
        if (empty($userIds)) {
            return;
        }

        $overlap = DB::table('shift_group_user')
            ->join('shift_groups', 'shift_groups.id', '=', 'shift_group_user.shift_group_id')
            ->join('users', 'users.id', '=', 'shift_group_user.user_id')
            ->whereIn('shift_group_user.user_id', $userIds)
            ->when($currentGroupId, fn ($q) => $q->where('shift_group_user.shift_group_id', '!=', $currentGroupId))
            ->select('users.name as user_name', 'shift_groups.name as group_name')
            ->first();

        if ($overlap) {
            abort(response()->json([
                'message' => "Group Conflict: '{$overlap->user_name}' is already assigned to '{$overlap->group_name}'. A librarian cannot belong to more than one group at a time.",
            ], 422));
        }
    }
}
