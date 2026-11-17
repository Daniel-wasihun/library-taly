<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GroupShiftSchedule;
use App\Models\Library;
use App\Models\Shift;
use App\Models\ShiftGroup;
use App\Services\DutyScheduleService;
use App\Services\EthiopianCalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GroupShiftScheduleController extends Controller
{
    /**
     * Get the Ethiopian calendar monthly shift schedule matrix and conflict data.
     */
    public function matrix(Request $request): JsonResponse
    {
        $user = $request->user();
        $currentEc = EthiopianCalendarService::getCurrentEthiopianDate();

        $selectedYear = (int)($request->query('ethiopian_year') ?: $currentEc['year']);
        $selectedMonth = (int)($request->query('ethiopian_month') ?: $currentEc['month']);
        $libraryId = $request->query('library_id');

        // Scoping for branch managers
        if (!$user->canManageAllLibraries() && $user->library_id) {
            $libraryId = $user->library_id;
        }

        // Default to first library if none provided
        if (!$libraryId) {
            $firstLib = Library::where('is_active', true)->orderBy('id')->first();
            $libraryId = $firstLib ? $firstLib->id : 1;
        }

        $library = Library::find($libraryId);
        $shifts = Shift::where('is_active', true)->orderBy('start_time')->get();

        $groups = ShiftGroup::with(['users:id,name,username'])
            ->where('library_id', $libraryId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // Query existing schedules for this library, year, and month
        $schedules = GroupShiftSchedule::with(['group:id,name', 'shift:id,name,name_am,code'])
            ->where('library_id', $libraryId)
            ->where('ethiopian_year', $selectedYear)
            ->where('ethiopian_month', $selectedMonth)
            ->get();

        // 1. by_shift map: shift_id => [ day => { group_id, group_name } ]
        // Used to enforce Rule 2: Inter-group conflict in the same shift
        $byShift = [];
        // 2. by_group map: group_id => [ shift_id => [ days... ] ]
        // Used to enforce Rule 1: Intra-group cross-shift conflict
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
            'conflict_matrix' => [
                'by_shift' => $byShift,
                'by_group' => $byGroup,
            ],
        ]);
    }

    /**
     * Assign monthly Ethiopian calendar shift schedule for a group with strict conflict prevention.
     */
    public function assign(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageLibrary()) {
            return response()->json(['message' => 'Unauthorized to schedule shifts.'], 403);
        }

        $validated = $request->validate([
            'library_id' => ['required', 'exists:libraries,id'],
            'shift_group_id' => ['required', 'exists:shift_groups,id'],
            'ethiopian_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'ethiopian_month' => ['required', 'integer', 'min:1', 'max:13'],
            'shifts' => ['required', 'array'],
            'shifts.*.shift_id' => ['required', 'exists:shifts,id'],
            'shifts.*.days' => ['present', 'array'],
            'shifts.*.days.*' => ['integer', 'min:1', 'max:30'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $libraryId = (int)$validated['library_id'];
        $groupId = (int)$validated['shift_group_id'];
        $year = (int)$validated['ethiopian_year'];
        $month = (int)$validated['ethiopian_month'];

        // Scoping check for branch managers
        if (!$user->canManageAllLibraries() && $user->library_id && (int)$user->library_id !== $libraryId) {
            return response()->json(['message' => 'Unauthorized: You may only schedule shifts for your assigned branch.'], 403);
        }

        $group = ShiftGroup::findOrFail($groupId);
        $library = Library::findOrFail($libraryId);

        if ((int)$group->library_id !== $libraryId) {
            return response()->json([
                'message' => "Group '{$group->name}' does not belong to {$library->name}. Groups are set separately for each library branch.",
            ], 422);
        }

        // Fetch shift names for clear error reporting
        $allShifts = Shift::all()->keyBy('id');

        // =========================================================================
        // RULE 1: Intra-group cross-shift conflict check
        // "if the date is selected in one shift it must be disabled for another shif in the shchedule of the same scheduule assigning"
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
        // RULE 2: Inter-group same-shift same-library conflict check
        // "and after that in that same library for other group shift date assigning fo rthe shif date selction ui the date that are belonges to another group in that same shif must be diabled also meaning the date that is selceted in night shift is 3 this means in group B the night shif date list the day 3 must be diabled not able to selctable like this"
        // =========================================================================
        // Query existing schedules for OTHER groups in the same library, year, and month
        $otherGroupSchedules = GroupShiftSchedule::with('group')
            ->where('library_id', $libraryId)
            ->where('ethiopian_year', $year)
            ->where('ethiopian_month', $month)
            ->where('shift_group_id', '!=', $groupId)
            ->get();

        // Build index of other group days per shift: otherDays[shift_id][day] = group_name
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
                            'message' => "Inter-group Conflict: Day {$day} for '{$shiftTitle}' is already assigned to {$competingGroup} in {$library->name}. It cannot be double-booked.",
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
        // Save Schedules in a Transaction
        // =========================================================================
        DB::transaction(function () use ($validated, $libraryId, $groupId, $year, $month) {
            foreach ($validated['shifts'] as $shiftAssignment) {
                $shiftId = (int)$shiftAssignment['shift_id'];
                $cleanDays = array_values(array_unique(array_map('intval', $shiftAssignment['days'])));
                sort($cleanDays);

                GroupShiftSchedule::updateOrCreate(
                    [
                        'library_id' => $libraryId,
                        'shift_group_id' => $groupId,
                        'shift_id' => $shiftId,
                        'ethiopian_year' => $year,
                        'ethiopian_month' => $month,
                    ],
                    [
                        'days' => $cleanDays,
                        'status' => 'active',
                        'notes' => $validated['notes'] ?? null,
                    ]
                );
            }
        });

        // Clear duty schedule cache for all members of this group
        $dutyService = app(DutyScheduleService::class);
        foreach ($group->users as $member) {
            $dutyService->clearUserScheduleCache($member->id);
        }

        $monthName = EthiopianCalendarService::MONTHS[$month]['am'] ?? "ወር {$month}";

        return response()->json([
            'success' => true,
            'message' => "Monthly duty schedule for {$group->name} ({$monthName} {$year} E.C.) successfully saved.",
        ]);
    }

    /**
     * Delete or clear a group's schedule for a specific month and shift.
     */
    public function destroy(Request $request, GroupShiftSchedule $groupShiftSchedule): JsonResponse
    {
        $user = $request->user();
        if (!$user->canManageLibrary()) {
            return response()->json(['message' => 'Unauthorized to delete shift schedules.'], 403);
        }

        if (!$user->canManageAllLibraries() && $user->library_id && (int)$user->library_id !== (int)$groupShiftSchedule->library_id) {
            return response()->json(['message' => 'Unauthorized: Cannot delete schedules belonging to another branch.'], 403);
        }

        $groupShiftSchedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Group shift schedule successfully removed.',
        ]);
    }
}
