<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EthiopianHoliday;
use App\Models\GroupShiftSchedule;
use App\Models\Library;
use App\Models\Shift;
use App\Models\ShiftGroup;
use App\Services\EthiopianCalendarService;
use App\Services\EthiopianHolidayService;
use App\Services\ShiftAutoSchedulerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupShiftScheduleController extends Controller
{
    public function __construct(
        protected ShiftAutoSchedulerService $autoScheduler
    ) {}

    /**
     * Get the Ethiopian calendar monthly shift schedule matrix and conflict data.
     * Schedule matrix applies across all library branches by default.
     */
    public function matrix(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.view')) {
            return response()->json(['message' => 'Unauthorized: Permission to view schedule matrix required.'], 403);
        }

        $currentEc = EthiopianCalendarService::getCurrentEthiopianDate();

        $selectedYear = (int)($request->query('ethiopian_year') ?: $currentEc['year']);
        $selectedMonth = (int)($request->query('ethiopian_month') ?: $currentEc['month']);
        $libraryId = $request->query('library_id') ? (int)$request->query('library_id') : null;

        $library = $libraryId ? Library::find($libraryId) : null;
        $shifts = Shift::where('is_active', true)->orderBy('start_time')->get();

        // Shift groups belong to all branches by default
        $groupsQuery = ShiftGroup::with([
            'library',
            'users:id,name,username,email,library_id',
            'users.library:id,name,code',
        ])
            ->where('is_active', true)
            ->orderBy('name');

        if ($libraryId) {
            $groupsQuery->where(function ($q) use ($libraryId) {
                $q->whereNull('library_id')->orWhere('library_id', $libraryId);
            });
        }
        $groups = $groupsQuery->get();

        // Query existing schedules for year & month (global or branch)
        $schedQuery = GroupShiftSchedule::with(['group:id,name', 'shift:id,name,name_am,code'])
            ->where('ethiopian_year', $selectedYear)
            ->where('ethiopian_month', $selectedMonth);

        if ($libraryId) {
            $schedQuery->where(function ($q) use ($libraryId) {
                $q->whereNull('library_id')->orWhere('library_id', $libraryId);
            });
        }
        $schedules = $schedQuery->get();

        // 1. by_shift map: shift_id => [ day => { group_id, group_name } ]
        // Enforces Rule 2: Inter-group conflict in the same shift across all branches
        $byShift = [];
        // 2. by_group map: group_id => [ shift_id => [ days... ] ]
        // Enforces Rule 1: Intra-group cross-shift conflict
        $byGroup = [];

        foreach ($schedules as $sched) {
            $shiftId = (int)$sched->shift_id;
            $groupId = (int)$sched->shift_group_id;
            $groupName = $sched->group ? $sched->group->name : "Group #{$groupId}";
            $days = is_array($sched->days) ? $sched->days : [];

            if (!isset($byShift[$shiftId])) {
                $byShift[$shiftId] = [];
            }
            if (!isset($byGroup[$groupId])) {
                $byGroup[$groupId] = [];
            }
            if (!isset($byGroup[$groupId][$shiftId])) {
                $byGroup[$groupId][$shiftId] = [];
            }

            foreach ($days as $day) {
                $d = (int)$day;
                $byShift[$shiftId][$d] = [
                    'group_id' => $groupId,
                    'group_name' => $groupName,
                    'shift_id' => $shiftId,
                    'shift_name' => $sched->shift ? $sched->shift->name : "Shift #{$shiftId}",
                ];
                $byGroup[$groupId][$shiftId][] = $d;
            }
        }

        return response()->json([
            'success' => true,
            'current_ethiopian_date' => $currentEc,
            'ethiopian_months' => EthiopianCalendarService::getMonthsList(),
            'ethiopian_year' => $selectedYear,
            'ethiopian_month' => $selectedMonth,
            'library' => $library,
            'shifts' => $shifts,
            'groups' => $groups,
            'schedules' => $schedules,
            'holidays' => EthiopianHolidayService::getHolidaysForMonth($selectedYear, $selectedMonth, $libraryId),
            'conflict_matrix' => [
                'by_shift' => $byShift,
                'by_group' => $byGroup,
            ],
        ]);
    }

    /**
     * Auto-generate balanced shift schedule across groups with holiday exclusion.
     */
    public function autoGenerate(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.manage')) {
            return response()->json(['message' => 'Unauthorized: Permission to manage schedules required.'], 403);
        }

        $validated = $request->validate([
            'library_id' => ['nullable', 'exists:libraries,id'],
            'ethiopian_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'ethiopian_month' => ['required', 'integer', 'min:1', 'max:13'],
            'exclude_holidays' => ['nullable', 'boolean'],
            'overwrite_existing' => ['nullable', 'boolean'],
        ]);

        $libraryId = !empty($validated['library_id']) ? (int)$validated['library_id'] : null;
        $year = (int)$validated['ethiopian_year'];
        $month = (int)$validated['ethiopian_month'];
        $excludeHolidays = $validated['exclude_holidays'] ?? true;
        $overwriteExisting = $validated['overwrite_existing'] ?? true;

        $result = $this->autoScheduler->autoGenerateSchedule(
            $libraryId,
            $year,
            $month,
            $excludeHolidays,
            $overwriteExisting
        );

        return response()->json($result);
    }

    /**
     * Check for staff overlapping multiple groups before scheduling.
     */
    public function checkOverlap(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.view')) {
            return response()->json(['message' => 'Unauthorized: Permission to view schedules required.'], 403);
        }

        $libraryId = $request->query('library_id') ? (int)$request->query('library_id') : null;
        $result = $this->autoScheduler->detectStaffOverlapInBranch($libraryId);
        return response()->json($result);
    }

    /**
     * Get Ethiopian holidays for a specific month (manual user-inserted holidays).
     */
    public function holidays(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.view')) {
            return response()->json(['message' => 'Unauthorized: Permission to view holidays required.'], 403);
        }

        $year = (int)($request->query('ethiopian_year') ?: 2019);
        $month = (int)($request->query('ethiopian_month') ?: 1);
        $libraryId = $request->query('library_id') ? (int)$request->query('library_id') : null;

        $holidays = EthiopianHolidayService::getHolidaysForMonth($year, $month, $libraryId);

        return response()->json([
            'success' => true,
            'ethiopian_year' => $year,
            'ethiopian_month' => $month,
            'total' => count($holidays),
            'holidays' => $holidays,
        ]);
    }

    /**
     * Insert manual Ethiopian holiday for a month and year.
     * "the holiday must be inserted manually by the user not auto detected"
     */
    public function storeHoliday(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.manage')) {
            return response()->json(['message' => 'Unauthorized: Permission to manage holidays required.'], 403);
        }

        $validated = $request->validate([
            'ethiopian_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'ethiopian_month' => ['required', 'integer', 'min:1', 'max:13'],
            'ethiopian_day' => ['required', 'integer', 'min:1', 'max:30'],
            'name' => ['required', 'string', 'max:255'],
            'name_am' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'library_id' => ['nullable', 'exists:libraries,id'],
        ]);

        $holiday = EthiopianHoliday::updateOrCreate(
            [
                'ethiopian_year' => (int)$validated['ethiopian_year'],
                'ethiopian_month' => (int)$validated['ethiopian_month'],
                'ethiopian_day' => (int)$validated['ethiopian_day'],
                'library_id' => $validated['library_id'] ?? null,
            ],
            [
                'name' => $validated['name'],
                'name_am' => $validated['name_am'] ?? $validated['name'],
                'description' => $validated['description'] ?? null,
                'created_by' => $user->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Holiday '{$holiday->name}' successfully inserted for Day {$holiday->ethiopian_day}.",
            'holiday' => $holiday,
        ], 201);
    }

    /**
     * Delete manual Ethiopian holiday.
     */
    public function destroyHoliday(Request $request, EthiopianHoliday $holiday): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.manage')) {
            return response()->json(['message' => 'Unauthorized: Permission to manage holidays required.'], 403);
        }

        $day = $holiday->ethiopian_day;
        $name = $holiday->name;
        $holiday->delete();

        return response()->json([
            'success' => true,
            'message' => "Holiday '{$name}' for Day {$day} has been deleted.",
        ]);
    }

    /**
     * Assign monthly Ethiopian calendar shift schedule for a group with strict conflict prevention.
     * "the day is same for all the library branch in a given group mean that the user is just select the month and the group to schedule the days for the group not the library branch"
     */
    public function assign(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.manage')) {
            return response()->json(['message' => 'Unauthorized: Permission to schedule shifts required.'], 403);
        }

        $validated = $request->validate([
            'library_id' => ['nullable', 'exists:libraries,id'],
            'shift_group_id' => ['required', 'exists:shift_groups,id'],
            'ethiopian_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'ethiopian_month' => ['required', 'integer', 'min:1', 'max:13'],
            'shifts' => ['required', 'array'],
            'shifts.*.shift_id' => ['required', 'exists:shifts,id'],
            'shifts.*.days' => ['present', 'array'],
            'shifts.*.days.*' => ['integer', 'min:1', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $libraryId = !empty($validated['library_id']) ? (int)$validated['library_id'] : null;
        $groupId = (int)$validated['shift_group_id'];
        $year = (int)$validated['ethiopian_year'];
        $month = (int)$validated['ethiopian_month'];

        $group = ShiftGroup::findOrFail($groupId);
        $allShifts = Shift::all()->keyBy('id');

        // =========================================================================
        // RULE 0: Ethiopian Holiday cancellation check
        // "and that day will be canceled in the schedule ui cleanly"
        // =========================================================================
        $monthHolidays = EthiopianHolidayService::getHolidaysForMonth($year, $month, $libraryId);
        foreach ($validated['shifts'] as $shiftAssignment) {
            $shiftId = (int)$shiftAssignment['shift_id'];
            $days = array_values(array_unique(array_map('intval', $shiftAssignment['days'])));
            $shiftObj = $allShifts->get($shiftId);
            $shiftTitle = $shiftObj ? $shiftObj->name : "Shift #{$shiftId}";

            foreach ($days as $day) {
                if (isset($monthHolidays[$day])) {
                    $holidayMeta = $monthHolidays[$day];
                    return response()->json([
                        'message' => "Holiday Conflict: Day {$day} is an official Ethiopian Holiday ({$holidayMeta['name_am']} / {$holidayMeta['name_en']}). All duty shifts are canceled on this day.",
                        'conflict_type' => 'HOLIDAY_CONFLICT',
                        'day' => $day,
                        'shift' => $shiftTitle,
                        'holiday' => $holidayMeta,
                    ], 422);
                }
            }
        }

        // =========================================================================
        // RULE 1: Intra-group cross-shift conflict check
        // "if the date is selected in one shift it must be disabled for another shift in the schedule of the same schedule assigning"
        // =========================================================================
        $seenDaysInGroup = [];
        foreach ($validated['shifts'] as $shiftAssignment) {
            $shiftId = (int)$shiftAssignment['shift_id'];
            $days = array_values(array_unique(array_map('intval', $shiftAssignment['days'])));
            $shiftObj = $allShifts->get($shiftId);
            $shiftTitle = $shiftObj ? $shiftObj->name : "Shift #{$shiftId}";

            foreach ($days as $day) {
                if (isset($seenDaysInGroup[$day])) {
                    $otherShiftTitle = $seenDaysInGroup[$day]['shift_name'];
                    return response()->json([
                        'message' => "Intra-group Conflict: Day {$day} cannot be selected for both '{$shiftTitle}' and '{$otherShiftTitle}' in {$group->name}.",
                        'conflict_type' => 'INTRA_GROUP_OVERLAP',
                        'day' => $day,
                        'shift_1' => $shiftTitle,
                        'shift_2' => $otherShiftTitle,
                    ], 422);
                }
                $seenDaysInGroup[$day] = [
                    'shift_id' => $shiftId,
                    'shift_name' => $shiftTitle,
                ];
            }
        }

        // =========================================================================
        // RULE 1.5: No consecutive Night and Day shift for the same group
        // "if a group is in night shift that group must not be the next day morning shift or the previous day shift"
        // =========================================================================
        $dayShiftDays = [];
        $nightShiftDays = [];

        foreach ($validated['shifts'] as $shiftAssignment) {
            $shiftId = (int)$shiftAssignment['shift_id'];
            $days = array_values(array_unique(array_map('intval', $shiftAssignment['days'])));
            $shiftObj = $allShifts->get($shiftId);
            $code = strtolower($shiftObj ? $shiftObj->code : '');
            $name = strtolower($shiftObj ? $shiftObj->name : '');
            $nameAm = $shiftObj ? ($shiftObj->name_am ?? '') : '';

            $isNight = str_contains($code, 'night') || str_contains($name, 'night') || str_contains($nameAm, 'ማታ');
            $isDay = str_contains($code, 'day') || str_contains($name, 'day') || str_contains($code, 'morning') || str_contains($nameAm, 'ቀን') || !$isNight;

            if ($isNight) {
                $nightShiftDays = array_merge($nightShiftDays, $days);
            } elseif ($isDay) {
                $dayShiftDays = array_merge($dayShiftDays, $days);
            }
        }

        $dayShiftDays = array_unique($dayShiftDays);
        $nightShiftDays = array_unique($nightShiftDays);

        foreach ($nightShiftDays as $nDay) {
            // Check next day morning shift (n + 1)
            if (in_array($nDay + 1, $dayShiftDays, true)) {
                $nextDay = $nDay + 1;
                return response()->json([
                    'message' => "Consecutive Shift Restriction: Group '{$group->name}' is assigned Night Shift on Day {$nDay} and cannot work Day Shift the next morning (Day {$nextDay}). Attendants must not work consecutive night and morning shifts.",
                    'conflict_type' => 'CONSECUTIVE_NIGHT_DAY_OVERLAP',
                    'night_day' => $nDay,
                    'day_shift_day' => $nextDay,
                ], 422);
            }

            // Check previous day morning/day shift (n - 1)
            if (in_array($nDay - 1, $dayShiftDays, true)) {
                $prevDay = $nDay - 1;
                return response()->json([
                    'message' => "Consecutive Shift Restriction: Group '{$group->name}' cannot be assigned Night Shift on Day {$nDay} because they work Day Shift the previous day (Day {$prevDay}). Attendants must not work consecutive day and night shifts.",
                    'conflict_type' => 'CONSECUTIVE_NIGHT_DAY_OVERLAP',
                    'night_day' => $nDay,
                    'day_shift_day' => $prevDay,
                ], 422);
            }
        }

        // =========================================================================
        // RULE 2: Inter-group same-shift conflict check across the system
        // "the day is same for all the library branch in a given group"
        // =========================================================================
        $otherGroupSchedulesQuery = GroupShiftSchedule::with('group')
            ->where('ethiopian_year', $year)
            ->where('ethiopian_month', $month)
            ->where('shift_group_id', '!=', $groupId);

        if ($libraryId) {
            $otherGroupSchedulesQuery->where(function ($q) use ($libraryId) {
                $q->whereNull('library_id')->orWhere('library_id', $libraryId);
            });
        }
        $otherGroupSchedules = $otherGroupSchedulesQuery->get();

        $otherDaysByShift = [];
        foreach ($otherGroupSchedules as $otherSched) {
            $sId = (int)$otherSched->shift_id;
            $otherGName = $otherSched->group ? $otherSched->group->name : "Group #{$otherSched->shift_group_id}";
            $days = is_array($otherSched->days) ? $otherSched->days : [];

            if (!isset($otherDaysByShift[$sId])) {
                $otherDaysByShift[$sId] = [];
            }

            foreach ($days as $d) {
                $otherDaysByShift[$sId][(int)$d] = $otherGName;
            }
        }

        // Check each submitted shift's days against other groups in the same shift
        foreach ($validated['shifts'] as $shiftAssignment) {
            $shiftId = (int)$shiftAssignment['shift_id'];
            $days = array_values(array_unique(array_map('intval', $shiftAssignment['days'])));
            $shiftObj = $allShifts->get($shiftId);
            $shiftTitle = $shiftObj ? $shiftObj->name : "Shift #{$shiftId}";

            if (isset($otherDaysByShift[$shiftId])) {
                foreach ($days as $day) {
                    if (isset($otherDaysByShift[$shiftId][$day])) {
                        $competingGroup = $otherDaysByShift[$shiftId][$day];
                        return response()->json([
                            'message' => "Inter-group Conflict: Day {$day} for '{$shiftTitle}' is already assigned to {$competingGroup}. It cannot be double-booked across groups.",
                            'conflict_type' => 'INTER_GROUP_OVERLAP',
                            'day' => $day,
                            'shift' => $shiftTitle,
                            'assigned_group' => $competingGroup,
                        ], 422);
                    }
                }
            }
        }

        // =========================================================================
        // Persist Schedules: applies globally to all library branches
        // =========================================================================
        DB::beginTransaction();
        try {
            $savedSchedules = [];

            foreach ($validated['shifts'] as $shiftAssignment) {
                $shiftId = (int)$shiftAssignment['shift_id'];
                $days = array_values(array_unique(array_map('intval', $shiftAssignment['days'])));
                sort($days);

                $record = GroupShiftSchedule::updateOrCreate(
                    [
                        'shift_group_id' => $groupId,
                        'shift_id' => $shiftId,
                        'ethiopian_year' => $year,
                        'ethiopian_month' => $month,
                    ],
                    [
                        'library_id' => $libraryId, // null = all branches
                        'days' => $days,
                        'status' => 'active',
                        'notes' => $validated['notes'] ?? null,
                    ]
                );

                $savedSchedules[] = $record->load(['group:id,name', 'shift:id,name,name_am,code']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Shift schedule for {$group->name} ({$month}/{$year} E.C.) successfully saved across all library branches.",
                'data' => $savedSchedules,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * List group schedules filterable by year, month, or group.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasPermissionTo('schedules.view')) {
            return response()->json(['message' => 'Unauthorized: Permission to view schedules required.'], 403);
        }

        $year = $request->query('ethiopian_year');
        $month = $request->query('ethiopian_month');
        $groupId = $request->query('shift_group_id');
        $libraryId = $request->query('library_id');

        $query = GroupShiftSchedule::with(['group:id,name', 'shift:id,name,name_am,code', 'library:id,name,code']);

        if ($year) {
            $query->where('ethiopian_year', (int)$year);
        }
        if ($month) {
            $query->where('ethiopian_month', (int)$month);
        }
        if ($groupId) {
            $query->where('shift_group_id', (int)$groupId);
        }
        if ($libraryId) {
            $query->where(function ($q) use ($libraryId) {
                $q->whereNull('library_id')->orWhere('library_id', (int)$libraryId);
            });
        }

        $schedules = $query->get();

        return response()->json([
            'success' => true,
            'data' => $schedules,
        ]);
    }
}
