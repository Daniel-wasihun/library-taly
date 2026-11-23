<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DutySchedule;
use App\Models\GroupShiftSchedule;
use App\Models\Library;
use App\Models\LibraryAttendance;
use App\Models\User;
use App\Services\DutyScheduleService;
use App\Services\EthiopianCalendarService;
use App\Services\ShiftInactivityMonitorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(
        protected DutyScheduleService $dutyService,
        protected ShiftInactivityMonitorService $inactivityService
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
            $todayStudent = (int) (clone $todayAttendance)->where('user_type', 'student')->sum('visitor_count');
            $todayTeacher = (int) (clone $todayAttendance)->where('user_type', 'teacher')->sum('visitor_count');
            $todayStaff = (int) (clone $todayAttendance)->where('user_type', 'staff')->sum('visitor_count');

            $malePercent = $todayTotal > 0 ? round(($todayMale / $todayTotal) * 100, 1) : 0;
            $femalePercent = $todayTotal > 0 ? round(($todayFemale / $todayTotal) * 100, 1) : 0;
            $studentPercent = $todayTotal > 0 ? round(($todayStudent / $todayTotal) * 100, 1) : 0;
            $teacherPercent = $todayTotal > 0 ? round(($todayTeacher / $todayTotal) * 100, 1) : 0;
            $staffPercent = $todayTotal > 0 ? round(($todayStaff / $todayTotal) * 100, 1) : 0;

            $totalBranches = Library::count();
            $totalStaff = User::whereHas('roles', fn ($q) => $q->whereIn('name', ['Super Admin', 'Head Librarian', 'Desk Librarian', 'Cataloging Specialist', 'Archive Assistant']))->count();

            // Branch Breakdown for today
            $branchesList = Library::select('id', 'name', 'name_am', 'code')->get();
            $branchesTraffic = [];
            foreach ($branchesList as $b) {
                $bCount = (int) (clone $todayAttendance)->where('library_id', $b->id)->sum('visitor_count');
                $bPercent = $todayTotal > 0 ? round(($bCount / $todayTotal) * 100, 1) : 0;
                $branchesTraffic[] = [
                    'id' => $b->id,
                    'name' => $b->name,
                    'name_am' => $b->name_am ?? $b->name,
                    'code' => $b->code,
                    'total' => $bCount,
                    'percent' => $bPercent,
                ];
            }
            usort($branchesTraffic, fn ($a, $b) => $b['total'] <=> $a['total']);
            $busiestBranch = $branchesTraffic[0]['name'] ?? 'General Library';

            // 7-day and 30-day aggregate trend
            $weekAgo = Carbon::today()->subDays(6)->toDateString();
            $weekTotal = (int) (clone $baseAttendance)->whereBetween('entry_at', [$weekAgo . ' 00:00:00', $today . ' 23:59:59'])->sum('visitor_count');

            // Staff on duty today (combines Ethiopian calendar GroupShiftSchedule and individual DutySchedule)
            $todayCarbon = Carbon::parse($today, 'Africa/Addis_Ababa');
            [$ey, $em, $ed] = EthiopianCalendarService::gregorianToEthiopian((int) $todayCarbon->year, (int) $todayCarbon->month, (int) $todayCarbon->day);
            $currentTimeStr = Carbon::now('Africa/Addis_Ababa')->format('H:i');

            $groupSchedules = GroupShiftSchedule::with(['shift', 'group.users.library', 'group.users.roles'])
                ->where('ethiopian_year', $ey)
                ->where('ethiopian_month', $em)
                ->where('status', 'active')
                ->get()
                ->filter(function ($sched) use ($ed) {
                    $days = is_array($sched->days) ? $sched->days : [];
                    return in_array((int) $ed, array_map('intval', $days), true);
                });

            $todayShiftGroups = [];
            $allOnDutyStaff = collect();
            $currentShiftStaffCount = 0;

            foreach ($groupSchedules as $sched) {
                $shift = $sched->shift;
                $group = $sched->group;
                if (!$shift || !$group) {
                    continue;
                }

                $isOvernight = ($shift->start_time > $shift->end_time);
                $isCurrentShift = false;
                if (!$isOvernight) {
                    $isCurrentShift = ($currentTimeStr >= $shift->start_time && $currentTimeStr <= $shift->end_time);
                } else {
                    $isCurrentShift = ($currentTimeStr >= $shift->start_time || $currentTimeStr <= $shift->end_time);
                }

                $groupUsers = $group->users;
                if (!$user->canManageAllLibraries() && $user->library_id) {
                    $groupUsers = $groupUsers->filter(fn ($u) => (int) $u->library_id === (int) $user->library_id);
                }

                $branchStaffMap = [];
                foreach ($groupUsers as $u) {
                    $branchId = $u->library_id ?? 0;
                    $branchName = $u->library?->name ?? 'All Branches';
                    $branchNameAm = $u->library?->name_am ?? $branchName;
                    $branchCode = $u->library?->code ?? '—';

                    if (!isset($branchStaffMap[$branchId])) {
                        $branchStaffMap[$branchId] = [
                            'branch_id' => $branchId,
                            'branch_name' => $branchName,
                            'branch_name_am' => $branchNameAm,
                            'branch_code' => $branchCode,
                            'attendants' => [],
                        ];
                    }

                    $staffData = [
                        'user_id' => $u->id,
                        'user_name' => $u->name,
                        'user_username' => $u->username ?? '',
                        'email' => $u->email,
                        'phone' => $u->phone ?? '—',
                        'role' => $u->roles->first()?->name ?? 'Attendant',
                        'library_id' => $branchId,
                        'library_name' => $branchName,
                        'library_code' => $branchCode,
                        'shift_id' => $shift->id,
                        'shift_name' => $shift->name,
                        'shift_name_am' => $shift->name_am ?? $shift->name,
                        'shift_group_id' => $group->id,
                        'shift_group_name' => $group->name,
                        'task_key' => 'attendance_desk',
                        'task_name' => 'Physical Attendance & Desk Logging',
                        'start_time' => $shift->start_time,
                        'end_time' => $shift->end_time,
                        'ethiopian_start_time' => $shift->ethiopian_start_time,
                        'ethiopian_end_time' => $shift->ethiopian_end_time,
                        'is_current_shift' => $isCurrentShift,
                    ];

                    $branchStaffMap[$branchId]['attendants'][] = $staffData;
                    $allOnDutyStaff->push($staffData);

                    if ($isCurrentShift) {
                        $currentShiftStaffCount++;
                    }
                }

                $startTime12 = Carbon::parse($shift->start_time)->format('h:i A');
                $endTime12 = Carbon::parse($shift->end_time)->format('h:i A');

                $todayShiftGroups[] = [
                    'shift_id' => $shift->id,
                    'shift_name' => $shift->name,
                    'shift_name_am' => $shift->name_am ?? $shift->name,
                    'shift_code' => $shift->code,
                    'start_time' => $startTime12,
                    'end_time' => $endTime12,
                    'ethiopian_start_time' => $shift->ethiopian_start_time,
                    'ethiopian_end_time' => $shift->ethiopian_end_time,
                    'time_window' => "{$startTime12} – {$endTime12}",
                    'ethiopian_time_window' => "{$shift->ethiopian_start_time} – {$shift->ethiopian_end_time}",
                    'group_id' => $group->id,
                    'group_name' => $group->name,
                    'is_current_shift' => $isCurrentShift,
                    'total_staff_count' => $groupUsers->count(),
                    'branches' => array_values($branchStaffMap),
                ];
            }

            // Also merge any individual DutySchedule records
            $onDutyStaffQuery = DutySchedule::where('shift_date', $today)
                ->whereIn('status', ['scheduled', 'active'])
                ->with(['user:id,name,username,email,phone', 'library:id,name,code']);

            if (!$user->canManageAllLibraries() && $user->library_id) {
                $onDutyStaffQuery->where(function ($q) use ($user) {
                    $q->where('library_id', $user->library_id)->orWhereNull('library_id');
                });
            }

            $individualDutySchedules = $onDutyStaffQuery->get();
            foreach ($individualDutySchedules as $indiv) {
                if (!$allOnDutyStaff->contains('user_id', $indiv->user_id)) {
                    $staffData = [
                        'user_id' => $indiv->user_id,
                        'user_name' => $indiv->user?->name ?? 'Staff',
                        'user_username' => $indiv->user?->username ?? '',
                        'email' => $indiv->user?->email ?? '',
                        'phone' => $indiv->user?->phone ?? '—',
                        'role' => $indiv->user?->roles->first()?->name ?? 'Staff',
                        'library_id' => $indiv->library_id,
                        'library_name' => $indiv->library?->name ?? 'All Branches',
                        'library_code' => $indiv->library?->code ?? '',
                        'shift_id' => null,
                        'shift_name' => 'Individual Duty',
                        'shift_name_am' => 'የግል ተረኝነት',
                        'shift_group_id' => null,
                        'shift_group_name' => 'Individual',
                        'task_key' => $indiv->task_key,
                        'task_name' => DutyScheduleService::TASKS[$indiv->task_key] ?? $indiv->task_key,
                        'start_time' => $indiv->start_time,
                        'end_time' => $indiv->end_time,
                        'ethiopian_start_time' => null,
                        'ethiopian_end_time' => null,
                        'is_current_shift' => true,
                    ];
                    $allOnDutyStaff->push($staffData);
                    $currentShiftStaffCount++;
                }
            }

            return [
                'today_total' => $todayTotal,
                'today_male' => $todayMale,
                'today_female' => $todayFemale,
                'today_student' => $todayStudent,
                'today_teacher' => $todayTeacher,
                'today_staff' => $todayStaff,
                'male_percent' => $malePercent,
                'female_percent' => $femalePercent,
                'student_percent' => $studentPercent,
                'teacher_percent' => $teacherPercent,
                'staff_percent' => $staffPercent,
                'total_branches' => $totalBranches,
                'total_staff' => $totalStaff,
                'busiest_branch' => $busiestBranch,
                'branches_traffic' => $branchesTraffic,
                'week_total' => $weekTotal,
                'on_duty_staff_today' => $allOnDutyStaff->values()->all(),
                'today_shift_groups' => $todayShiftGroups,
                'current_shift_staff_count' => $currentShiftStaffCount,
                'total_staff_on_duty_today' => $allOnDutyStaff->count(),
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

        // Real-time shift inactivity alerts (> 30 minutes without counting)
        $branchId = (!$user->canManageAllLibraries() && $user->library_id) ? $user->library_id : null;
        $inactivityAlerts = $this->inactivityService->getCurrentShiftInactivityAlerts($branchId);

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'recent_entries' => $recentEntries,
            'inactivity_alerts' => $inactivityAlerts,
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
