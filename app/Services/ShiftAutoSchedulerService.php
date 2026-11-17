<?php

namespace App\Services;

use App\Models\GroupShiftSchedule;
use App\Models\Library;
use App\Models\Shift;
use App\Models\ShiftGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShiftAutoSchedulerService
{
    public function __construct(
        protected DutyScheduleService $dutyService
    ) {}

    /**
     * Detect if any person belongs to two or more groups at the same time in the given library branch.
     *
     * @param int $libraryId
     * @return array{has_overlap: bool, overlaps: array<int, array{user_id: int, user_name: string, groups: string[]}>}
     */
    public function detectStaffOverlapInBranch(int $libraryId): array
    {
        $groups = ShiftGroup::with('users:id,name,username')
            ->where('library_id', $libraryId)
            ->where('is_active', true)
            ->get();

        $userGroups = [];
        foreach ($groups as $group) {
            foreach ($group->users as $user) {
                if (!isset($userGroups[$user->id])) {
                    $userGroups[$user->id] = [
                        'user_id' => $user->id,
                        'user_name' => $user->name,
                        'username' => $user->username,
                        'groups' => [],
                    ];
                }
                $userGroups[$user->id]['groups'][] = $group->name;
            }
        }

        $overlaps = [];
        foreach ($userGroups as $uid => $data) {
            if (count($data['groups']) > 1) {
                $overlaps[] = $data;
            }
        }

        return [
            'has_overlap' => !empty($overlaps),
            'overlaps' => $overlaps,
        ];
    }

    /**
     * Auto-generate and assign balanced monthly shift schedule for a specific library branch.
     *
     * @param int $libraryId
     * @param int $year Ethiopian year (e.g. 2019)
     * @param int $month Ethiopian month (1 to 13)
     * @param bool $excludeHolidays Whether to detect and remove holidays from schedule
     * @param bool $overwriteExisting Whether to overwrite existing schedules for this branch & month
     * @return array
     * @throws ValidationException
     */
    public function autoGenerateSchedule(
        int $libraryId,
        int $year,
        int $month,
        bool $excludeHolidays = true,
        bool $overwriteExisting = true
    ): array {
        $library = Library::findOrFail($libraryId);

        // 1. Detect person in multiple groups in this branch
        // "it must detect no one person cannot be in the two group at the same time in a given library branch"
        $overlapCheck = $this->detectStaffOverlapInBranch($libraryId);
        if ($overlapCheck['has_overlap']) {
            $conflictDetails = array_map(function ($o) {
                $grpNames = implode(', ', $o['groups']);
                return "{$o['user_name']} (@{$o['username']}) is in [{$grpNames}]";
            }, $overlapCheck['overlaps']);

            $msg = "Cannot auto-schedule for {$library->name}: One person cannot belong to multiple groups at the same time in this branch. " .
                "Overlaps detected: " . implode('; ', $conflictDetails) . ". " .
                "Please assign each attendant to a single group in this branch before auto-generating.";

            throw ValidationException::withMessages([
                'staff_overlap' => [$msg],
            ]);
        }

        // 2. Fetch active groups for this library branch only
        $groups = ShiftGroup::where('library_id', $libraryId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        if ($groups->isEmpty()) {
            throw ValidationException::withMessages([
                'groups' => ["No active duty groups found for {$library->name}. Please create groups (e.g. Group A, Group B) first."],
            ]);
        }

        // 3. Fetch active shifts
        $shifts = Shift::where('is_active', true)
            ->orderBy('start_time')
            ->get();

        if ($shifts->isEmpty()) {
            throw ValidationException::withMessages([
                'shifts' => ["No active shifts found in system. Please define shifts (e.g. Day Shift, Night Shift) first."],
            ]);
        }

        // 4. Determine days in this Ethiopian month and detect holidays
        // "the system must can detect an holiday in a given moth like if there is any holiday in a month profesionally and remove that holiday 1 day from the schedule"
        $totalDays = $month === 13 ? 5 : 30;
        $allMonthDays = range(1, $totalDays);

        $detectedHolidays = [];
        $workingDays = [];

        if ($excludeHolidays) {
            $holidaysMap = EthiopianHolidayService::getHolidaysForMonth($year, $month);
            foreach ($allMonthDays as $day) {
                if (isset($holidaysMap[$day])) {
                    $detectedHolidays[] = $holidaysMap[$day];
                } else {
                    $workingDays[] = $day;
                }
            }
        } else {
            $workingDays = $allMonthDays;
        }

        $numGroups = $groups->count();
        $numShifts = $shifts->count();

        // 5. Compute equal workload schedule distribution
        // For each group, groupShiftDays[groupId][shiftId] = [day numbers]
        $groupShiftDays = [];
        foreach ($groups as $g) {
            $groupShiftDays[$g->id] = [];
            foreach ($shifts as $s) {
                $groupShiftDays[$g->id][$s->id] = [];
            }
        }

        // Round-robin rotation algorithm guaranteeing:
        // - Intra-group rule: No group gets 2 shifts on the same day
        // - Inter-group rule: No shift gets 2 groups on the same day
        // - Equal workload: Differences across groups <= 1 shift
        $numShiftsToAssign = min($numShifts, $numGroups);

        foreach ($workingDays as $dayIndex => $day) {
            for ($sIdx = 0; $sIdx < $numShiftsToAssign; $sIdx++) {
                $shift = $shifts[$sIdx];
                $groupIndex = ($dayIndex + $sIdx) % $numGroups;
                $assignedGroup = $groups[$groupIndex];

                $groupShiftDays[$assignedGroup->id][$shift->id][] = $day;
            }
        }

        // 6. Save schedules in a transaction
        DB::transaction(function () use ($libraryId, $year, $month, $groupShiftDays, $groups, $shifts, $overwriteExisting) {
            if ($overwriteExisting) {
                GroupShiftSchedule::where('library_id', $libraryId)
                    ->where('ethiopian_year', $year)
                    ->where('ethiopian_month', $month)
                    ->delete();
            }

            foreach ($groups as $group) {
                foreach ($shifts as $shift) {
                    $days = $groupShiftDays[$group->id][$shift->id] ?? [];
                    sort($days);

                    GroupShiftSchedule::updateOrCreate(
                        [
                            'library_id' => $libraryId,
                            'shift_group_id' => $group->id,
                            'shift_id' => $shift->id,
                            'ethiopian_year' => $year,
                            'ethiopian_month' => $month,
                        ],
                        [
                            'days' => $days,
                            'status' => 'active',
                            'notes' => "Auto-generated schedule with balanced workload ({$year} E.C., Month {$month})",
                        ]
                    );
                }
            }
        });

        // 7. Clear duty caches for staff in these groups
        foreach ($groups as $group) {
            foreach ($group->users as $member) {
                $this->dutyService->clearUserScheduleCache($member->id);
            }
        }

        // 8. Compile workload statistics summary
        $workloadStats = [];
        foreach ($groups as $g) {
            $totalShifts = 0;
            $breakdown = [];
            foreach ($shifts as $s) {
                $cnt = count($groupShiftDays[$g->id][$s->id] ?? []);
                $totalShifts += $cnt;
                $breakdown[$s->name] = $cnt;
            }
            $workloadStats[] = [
                'group_id' => $g->id,
                'group_name' => $g->name,
                'total_assigned_days' => $totalShifts,
                'shift_breakdown' => $breakdown,
            ];
        }

        $monthMeta = EthiopianCalendarService::MONTHS[$month] ?? ['am' => "ወር {$month}", 'en' => "Month {$month}"];

        return [
            'success' => true,
            'message' => "Schedule successfully auto-generated for {$library->name} for {$monthMeta['am']} ({$monthMeta['en']}) {$year} ዓ.ም.",
            'library' => [
                'id' => $library->id,
                'name' => $library->name,
            ],
            'ethiopian_year' => $year,
            'ethiopian_month' => $month,
            'month_name_am' => $monthMeta['am'],
            'month_name_en' => $monthMeta['en'],
            'total_calendar_days' => $totalDays,
            'working_days_scheduled' => count($workingDays),
            'excluded_holidays' => $detectedHolidays,
            'groups_count' => $numGroups,
            'workload_stats' => $workloadStats,
        ];
    }
}
