<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use App\Services\EthiopianCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShiftController extends Controller
{
    /**
     * List all shifts.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermissionTo('schedules.view') && !$user->hasPermissionTo('libraries.view'))) {
            return response()->json(['message' => 'Unauthorized: Permission to view shifts required.'], 403);
        }

        $shifts = Shift::orderBy('start_time', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => $shifts,
        ]);
    }

    /**
     * Create a new shift definition with Ethiopian local time support.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.manage')) {
            return response()->json(['message' => 'Unauthorized: Permission to manage shifts required.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_am' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', 'unique:shifts,code'],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'ethiopian_start_time' => ['nullable', 'string', 'max:50'],
            'ethiopian_end_time' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $code = !empty($validated['code'])
            ? Str::slug($validated['code'], '_')
            : Str::slug($validated['name'], '_');

        // Auto-compute Ethiopian local time if not provided
        $ethiopianStart = !empty($validated['ethiopian_start_time'])
            ? $validated['ethiopian_start_time']
            : EthiopianCalendarService::convert24ToEthiopianTime($validated['start_time']);

        $ethiopianEnd = !empty($validated['ethiopian_end_time'])
            ? $validated['ethiopian_end_time']
            : EthiopianCalendarService::convert24ToEthiopianTime($validated['end_time']);

        $shift = Shift::create([
            'name' => $validated['name'],
            'name_am' => $validated['name_am'] ?? null,
            'code' => $code,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'ethiopian_start_time' => $ethiopianStart,
            'ethiopian_end_time' => $ethiopianEnd,
            'description' => $validated['description'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Shift '{$shift->name}' created successfully.",
            'data' => $shift,
        ], 201);
    }

    /**
     * Update an existing shift definition.
     */
    public function update(Request $request, Shift $shift): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.manage')) {
            return response()->json(['message' => 'Unauthorized: Permission to update shifts required.'], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_am' => ['nullable', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:50', 'unique:shifts,code,' . $shift->id],
            'start_time' => ['required', 'string'],
            'end_time' => ['required', 'string'],
            'ethiopian_start_time' => ['nullable', 'string', 'max:50'],
            'ethiopian_end_time' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        $ethiopianStart = !empty($validated['ethiopian_start_time'])
            ? $validated['ethiopian_start_time']
            : EthiopianCalendarService::convert24ToEthiopianTime($validated['start_time']);

        $ethiopianEnd = !empty($validated['ethiopian_end_time'])
            ? $validated['ethiopian_end_time']
            : EthiopianCalendarService::convert24ToEthiopianTime($validated['end_time']);

        $shift->update([
            'name' => $validated['name'],
            'name_am' => $validated['name_am'] ?? $shift->name_am,
            'code' => !empty($validated['code']) ? Str::slug($validated['code'], '_') : $shift->code,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'ethiopian_start_time' => $ethiopianStart,
            'ethiopian_end_time' => $ethiopianEnd,
            'description' => $validated['description'] ?? $shift->description,
            'is_active' => $validated['is_active'] ?? $shift->is_active,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Shift '{$shift->name}' updated successfully.",
            'data' => $shift,
        ]);
    }

    /**
     * Delete a shift definition.
     */
    public function destroy(Request $request, Shift $shift): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.manage')) {
            return response()->json(['message' => 'Unauthorized: Permission to delete shifts required.'], 403);
        }

        if ($shift->schedules()->exists()) {
            return response()->json([
                'message' => 'Cannot delete shift because it is linked to active monthly duty schedules. Deactivate it instead.',
            ], 422);
        }

        $shift->delete();

        return response()->json([
            'success' => true,
            'message' => 'Shift successfully deleted.',
        ]);
    }
}
