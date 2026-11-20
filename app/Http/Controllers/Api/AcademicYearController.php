<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AcademicYearController extends Controller
{
    /**
     * Display a listing of academic years.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!$user->canViewAcademicYears()) {
            return response()->json(['message' => 'Unauthorized: Permission to view academic calendar required.'], 403);
        }

        $years = AcademicYear::query()
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'data' => $years,
            'current' => AcademicYear::current(),
        ]);
    }

    /**
     * Store a newly created academic year.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageAcademicYears()) {
            return response()->json(['message' => 'Unauthorized: Academic calendar management permission required.'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:50|unique:academic_years,code',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_current' => 'boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($validated) {
            if (!empty($validated['is_current'])) {
                AcademicYear::query()->update(['is_current' => false]);
            } else {
                // If this is the only academic year, force it as current
                if (AcademicYear::count() === 0) {
                    $validated['is_current'] = true;
                }
            }

            $academicYear = AcademicYear::create($validated);

            return response()->json([
                'message' => 'Academic year registered successfully.',
                'data' => $academicYear,
            ], 201);
        });
    }

    /**
     * Display the specified academic year.
     */
    public function show(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $user = $request->user();
        if ($user && !$user->canViewAcademicYears()) {
            return response()->json(['message' => 'Unauthorized: Permission to view academic calendar required.'], 403);
        }

        return response()->json([
            'data' => $academicYear,
        ]);
    }

    /**
     * Update the specified academic year.
     */
    public function update(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageAcademicYears()) {
            return response()->json(['message' => 'Unauthorized: Academic calendar management permission required.'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:100',
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('academic_years', 'code')->ignore($academicYear->id)],
            'start_date' => 'sometimes|required|date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'is_current' => 'boolean',
            'description' => 'nullable|string|max:1000',
        ]);

        return DB::transaction(function () use ($validated, $academicYear) {
            if (!empty($validated['is_current'])) {
                AcademicYear::where('id', '!=', $academicYear->id)->update(['is_current' => false]);
            }

            $academicYear->update($validated);

            return response()->json([
                'message' => 'Academic year updated successfully.',
                'data' => $academicYear->fresh(),
            ]);
        });
    }

    /**
     * Remove the specified academic year.
     */
    public function destroy(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageAcademicYears()) {
            return response()->json(['message' => 'Unauthorized: Academic calendar management permission required.'], 403);
        }

        if (AcademicYear::count() <= 1) {
            return response()->json([
                'message' => 'Cannot delete the only configured academic year.',
            ], 422);
        }

        $wasCurrent = $academicYear->is_current;
        $academicYear->delete();

        if ($wasCurrent) {
            $latest = AcademicYear::orderBy('start_date', 'desc')->first();
            if ($latest) {
                $latest->update(['is_current' => true]);
            }
        }

        return response()->json([
            'message' => 'Academic year deleted successfully.',
        ]);
    }

    /**
     * Set the specified academic year as the current active year.
     */
    public function setCurrent(Request $request, AcademicYear $academicYear): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageAcademicYears()) {
            return response()->json(['message' => 'Unauthorized: Academic calendar management permission required.'], 403);
        }

        DB::transaction(function () use ($academicYear) {
            AcademicYear::where('id', '!=', $academicYear->id)->update(['is_current' => false]);
            $academicYear->update(['is_current' => true]);
        });

        return response()->json([
            'message' => "Academic year '{$academicYear->name}' is now active.",
            'data' => $academicYear->fresh(),
        ]);
    }
}
