<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Library;
use App\Models\ShiftGroup;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftGroupController extends Controller
{
    /**
     * List all shift groups, filterable by library branch.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $libraryId = $request->query('library_id');

        // Scoping: If user is scoped branch manager, restrict to their branch
        if (!$user->canManageAllLibraries() && $user->library_id) {
            $libraryId = $user->library_id;
        }

        $query = ShiftGroup::with(['library', 'users:id,name,username,email,library_id'])
            ->withCount('users')
            ->orderBy('name', 'asc');

        if ($libraryId) {
            $query->where(function ($q) use ($libraryId) {
                $q->where('library_id', $libraryId)
                  ->orWhereNull('library_id');
            });
        }

        $groups = $query->get();

        return response()->json([
            'success' => true,
            'data' => $groups,
        ]);
    }

    /**
     * Create a new shift group with branch validation.
     * "a group is created with specific branch that a user can be assigned to that group if they are same in library branch or if the group or librarian have general branch"
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageLibrary()) {
            return response()->json(['message' => 'Unauthorized to create shift groups.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'library_id' => ['nullable', 'exists:libraries,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $targetLibraryId = $validated['library_id'] ?? null;

        // If scoped manager, force branch to their own branch
        if (!$user->canManageAllLibraries() && $user->library_id) {
            $targetLibraryId = $user->library_id;
        }

        // Validate branch compatibility of assigned members
        if (!empty($validated['user_ids'])) {
            $this->validateMembersBranchCompatibility($validated['user_ids'], $targetLibraryId);
        }

        $group = ShiftGroup::create([
            'name' => $validated['name'],
            'library_id' => $targetLibraryId,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (!empty($validated['user_ids'])) {
            $group->users()->sync($validated['user_ids']);
        }

        return response()->json([
            'success' => true,
            'message' => "Shift Group '{$group->name}' created successfully.",
            'data' => $group->load(['library', 'users:id,name,username,email,library_id']),
        ], 201);
    }

    /**
     * Update an existing shift group and its members.
     */
    public function update(Request $request, ShiftGroup $shiftGroup): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageLibrary()) {
            return response()->json(['message' => 'Unauthorized to update shift groups.'], 403);
        }

        // Scoped check
        if (!$user->canManageAllLibraries() && $user->library_id && $shiftGroup->library_id !== $user->library_id) {
            return response()->json(['message' => 'Cannot modify a shift group belonging to another branch.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'library_id' => ['nullable', 'exists:libraries,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $targetLibraryId = array_key_exists('library_id', $validated)
            ? $validated['library_id']
            : $shiftGroup->library_id;

        if (!$user->canManageAllLibraries() && $user->library_id) {
            $targetLibraryId = $user->library_id;
        }

        if (isset($validated['user_ids'])) {
            $this->validateMembersBranchCompatibility($validated['user_ids'], $targetLibraryId);
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
            'data' => $shiftGroup->load(['library', 'users:id,name,username,email,library_id']),
        ]);
    }

    /**
     * Delete a shift group.
     */
    public function destroy(Request $request, ShiftGroup $shiftGroup): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageLibrary()) {
            return response()->json(['message' => 'Unauthorized to delete shift groups.'], 403);
        }

        if (!$user->canManageAllLibraries() && $user->library_id && $shiftGroup->library_id !== $user->library_id) {
            return response()->json(['message' => 'Cannot delete a shift group belonging to another branch.'], 403);
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
     */
    public function eligibleStaff(Request $request): JsonResponse
    {
        $targetLibraryId = $request->query('library_id');

        $users = User::role(['Attendant', 'Librarian', 'Manager'])
            ->with('library:id,name,code')
            ->select('id', 'name', 'username', 'email', 'library_id')
            ->orderBy('name')
            ->get()
            ->map(function ($u) use ($targetLibraryId) {
                // Compatible if: target is null OR user has no library (general) OR user branch equals target branch
                $isCompatible = empty($targetLibraryId)
                    || is_null($u->library_id)
                    || (int)$u->library_id === (int)$targetLibraryId;

                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username,
                    'email' => $u->email,
                    'library_id' => $u->library_id,
                    'library' => $u->library,
                    'is_compatible' => $isCompatible,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $users,
        ]);
    }

    /**
     * Helper to validate branch compatibility according to user rule:
     * "a user can be assigned to that group if they are same in library branch or if the group or librarian have general branch"
     */
    protected function validateMembersBranchCompatibility(array $userIds, ?int $groupLibraryId): void
    {
        if (empty($userIds) || is_null($groupLibraryId)) {
            // General group can take attendants from any branch or general
            return;
        }

        $groupLibrary = Library::find($groupLibraryId);
        $groupLibName = $groupLibrary ? $groupLibrary->name : "Branch #{$groupLibraryId}";

        $incompatibleUsers = User::with('library')
            ->whereIn('id', $userIds)
            ->whereNotNull('library_id')
            ->where('library_id', '!=', $groupLibraryId)
            ->get();

        if ($incompatibleUsers->isNotEmpty()) {
            $userNames = $incompatibleUsers->map(function ($u) {
                $userBranch = $u->library ? $u->library->name : "Branch #{$u->library_id}";
                return "{$u->name} ({$userBranch})";
            })->join(', ');

            abort(response()->json([
                'message' => "Branch Conflict: The following staff members belong to other branches and cannot be assigned to '{$groupLibName}': {$userNames}. Only staff assigned to {$groupLibName} or General Branch may join this group.",
            ], 422));
        }
    }
}
