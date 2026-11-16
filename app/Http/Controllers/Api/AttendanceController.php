<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibraryAttendance;
use App\Models\Library;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    /**
     * List recent attendance entries with filtering and branch isolation.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = LibraryAttendance::query()->with(['library', 'staff']);

        // Enforce branch-level isolation for users who cannot manage all branches
        if (!$user->canManageAllLibraries()) {
            $userBranchId = $user->library_id;
            if (!$userBranchId) {
                return response()->json([
                    'data' => [],
                    'total' => 0,
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $request->integer('per_page', 25),
                ]);
            }
            $query->where('library_id', $userBranchId);
        } elseif ($request->filled('library_id')) {
            $query->where('library_id', $request->library_id);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_at', '<=', $request->date_to);
        }

        $perPage = $request->integer('per_page', 25);
        $attendances = $query->orderBy('entry_at', 'desc')->paginate($perPage);

        return response()->json($attendances);
    }

    /**
     * Log a physical library attendance count (+1 or batch).
     */
    public function logEntry(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'library_id' => ['required', 'exists:libraries,id'],
            'user_type' => ['required', 'in:student,teacher,staff'],
            'gender' => ['required', 'in:male,female'],
            'count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $libraryId = (int) $validated['library_id'];

        // Security check: Ensure staff logs into their own branch unless global admin
        if (!$user->canManageAllLibraries()) {
            if (!$user->library_id || (int) $user->library_id !== $libraryId) {
                return response()->json([
                    'message' => 'Unauthorized: You can only record attendance for your assigned library branch.',
                ], 403);
            }
        }

        $count = (int) ($validated['count'] ?? 1);

        $attendance = LibraryAttendance::create([
            'library_id' => $libraryId,
            'user_type' => $validated['user_type'],
            'gender' => $validated['gender'],
            'visitor_count' => $count,
            'staff_id' => $user->id,
            'entry_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Logged {$count} {$validated['gender']} {$validated['user_type']}(s) successfully.",
            'data' => $attendance->load(['library', 'staff']),
            'count' => $count,
        ], 201);
    }

    /**
     * Attendance statistics and distributions (Total, Male/Female, User Types, Trends).
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $restrictedLibraryId = null;
        if (!$user->canManageAllLibraries()) {
            $restrictedLibraryId = $user->library_id;
            if (!$restrictedLibraryId) {
                return response()->json([
                    'total_users' => 0,
                    'total_male' => 0,
                    'total_female' => 0,
                    'gender_distribution' => [],
                    'type_distribution' => [],
                    'library_distribution' => [],
                    'trends' => [],
                ]);
            }
        }

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfDay();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $baseQuery = LibraryAttendance::whereBetween('entry_at', [$dateFrom, $dateTo])
            ->when($restrictedLibraryId, fn($q) => $q->where('library_id', $restrictedLibraryId))
            ->when($request->filled('library_id') && $user->canManageAllLibraries(), fn($q) => $q->where('library_id', $request->library_id));

        $totalCount = (int) (clone $baseQuery)->sum('visitor_count');

        $genderBreakdown = (clone $baseQuery)
            ->select('gender', DB::raw('SUM(visitor_count) as count'))
            ->groupBy('gender')
            ->get();

        $totalMale = (int) $genderBreakdown->where('gender', 'male')->sum('count');
        $totalFemale = (int) $genderBreakdown->where('gender', 'female')->sum('count');

        $typeBreakdown = (clone $baseQuery)
            ->select('user_type', DB::raw('SUM(visitor_count) as count'))
            ->groupBy('user_type')
            ->get();

        $libraryDistribution = (clone $baseQuery)
            ->select('library_id', 'user_type', 'gender', DB::raw('SUM(visitor_count) as count'))
            ->groupBy('library_id', 'user_type', 'gender')
            ->with('library:id,name,name_am,code')
            ->get();

        $periodicTrends = (clone $baseQuery)
            ->select(DB::raw('DATE(entry_at) as date'), DB::raw('SUM(visitor_count) as count'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'total_users' => $totalCount,
            'total_male' => $totalMale,
            'total_female' => $totalFemale,
            'gender_distribution' => $genderBreakdown,
            'type_distribution' => $typeBreakdown,
            'library_distribution' => $libraryDistribution,
            'trends' => $periodicTrends,
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
        ]);
    }

    /**
     * Export attendance data as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();

        $query = LibraryAttendance::with(['library', 'staff']);

        if (!$user->canManageAllLibraries()) {
            $query->where('library_id', $user->library_id);
        } elseif ($request->filled('library_id')) {
            $query->where('library_id', $request->library_id);
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_at', '<=', $request->date_to);
        }

        $attendances = $query->orderBy('entry_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="attendance_report_' . now()->format('Y_m_d_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($attendances) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM
            fputcsv($file, ['Log ID', 'Library Branch', 'User Type', 'Gender', 'Visitor Count', 'Recorded At', 'Staff Member']);

            foreach ($attendances as $log) {
                fputcsv($file, [
                    $log->slug,
                    $log->library?->name ?? 'General',
                    ucfirst($log->user_type),
                    ucfirst($log->gender),
                    $log->visitor_count,
                    $log->entry_at?->toDateTimeString() ?? '',
                    $log->staff?->name ?? 'System',
                ]);
            }
            fclose($file);
        }, 200, $headers);
    }

    /**
     * Update visitor count for an attendance entry.
     */
    public function updateCount(Request $request, string $slug): JsonResponse
    {
        $attendance = LibraryAttendance::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        if (!$user->canManageAllLibraries() && (int) $attendance->library_id !== (int) $user->library_id) {
            return response()->json(['message' => 'Unauthorized to update attendance for this branch.'], 403);
        }

        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $attendance->update(['visitor_count' => $validated['count']]);

        return response()->json([
            'success' => true,
            'data' => $attendance->load(['library', 'staff']),
        ]);
    }

    /**
     * Delete an entry within the 2-minute safety window.
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $attendance = LibraryAttendance::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        if (!$user->canManageAllLibraries() && (int) $attendance->library_id !== (int) $user->library_id) {
            return response()->json(['message' => 'Unauthorized to delete attendance for this branch.'], 403);
        }

        // 2-minute rule from LMS: deletion allowed only within 2 minutes of logging
        if (Carbon::parse($attendance->entry_at)->diffInMinutes(now()) > 2 && !$user->hasAdminPrivilege()) {
            return response()->json([
                'message' => 'Cannot delete this log: entries can only be cancelled within 2 minutes of recording.',
            ], 422);
        }

        $attendance->delete();

        return response()->json(['success' => true, 'message' => 'Attendance log deleted.']);
    }
}
