<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DutySchedule;
use App\Models\Library;
use App\Models\LibraryAttendance;
use App\Models\User;
use App\Services\DutyScheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(
        protected DutyScheduleService $dutyService
    ) {}

    /**
     * Get aggregate library attendance statistics and user duty status.
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Enforce dashboard viewing authorization
        if (!$user->canViewDashboard()) {
            return response()->json([
                'message' => 'Unauthorized: Dashboard view permission is required.',
            ], 403);
        }

        $today = Carbon::today()->toDateString();

        // Overall physical attendance stats
        $stats = Cache::remember('dashboard_stats_' . ($user->library_id ?? 'all'), 30, function () use ($today, $user) {
            $baseAttendance = LibraryAttendance::query();
            if (!$user->canManageAllLibraries() && $user->library_id) {
                $baseAttendance->where('library_id', $user->library_id);
            }

            $todayAttendance = (clone $baseAttendance)->whereDate('entry_at', $today);
            $todayTotal = (int) (clone $todayAttendance)->sum('visitor_count');
            $todayMale = (int) (clone $todayAttendance)->where('gender', 'male')->sum('visitor_count');
            $todayFemale = (int) (clone $todayAttendance)->where('gender', 'female')->sum('visitor_count');

            $totalBranches = Library::count();
            $totalStaff = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Head Librarian', 'Desk Librarian', 'Cataloging Specialist', 'Archive Assistant']))->count();

            // Staff on duty today
            $onDutyStaffQuery = DutySchedule::where('shift_date', $today)
                ->whereIn('status', ['scheduled', 'active'])
                ->with(['user:id,name,username,email', 'library:id,name,code']);

            if (!$user->canManageAllLibraries() && $user->library_id) {
                $onDutyStaffQuery->where(function ($q) use ($user) {
                    $q->where('library_id', $user->library_id)->orWhereNull('library_id');
                });
            }

            $onDutyStaff = $onDutyStaffQuery->get()
                ->map(fn ($shift) => [
                    'user_id' => $shift->user_id,
                    'user_name' => $shift->user?->name ?? 'Staff',
                    'user_username' => $shift->user?->username ?? '',
                    'library_name' => $shift->library?->name ?? 'All Branches',
                    'task_key' => $shift->task_key,
                    'task_name' => DutyScheduleService::TASKS[$shift->task_key] ?? $shift->task_key,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                ]);

            return [
                'today_total' => $todayTotal,
                'today_male' => $todayMale,
                'today_female' => $todayFemale,
                'total_branches' => $totalBranches,
                'total_staff' => $totalStaff,
                'on_duty_staff_today' => $onDutyStaff,
            ];
        });

        // User specific duty check (live)
        $deskDuty = $this->dutyService->canPerformTask($user, 'attendance_desk', $today, $user->library_id);

        $recentEntriesQuery = LibraryAttendance::with(['library:id,name,code', 'staff:id,name,username'])
            ->latest('entry_at')
            ->limit(8);

        if (!$user->canManageAllLibraries() && $user->library_id) {
            $recentEntriesQuery->where('library_id', $user->library_id);
        }

        $recentEntries = $recentEntriesQuery->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'recent_entries' => $recentEntries,
            'current_user_duty' => [
                'is_admin' => $user->hasAdminPrivilege(),
                'authorized' => $deskDuty['allowed'],
                'reason' => $deskDuty['reason'],
                'next_turn' => $deskDuty['next_turn'] ?? null,
                'task_name' => $deskDuty['task_name'] ?? 'Physical Attendance & Desk Logging',
            ],
        ]);
    }
}
