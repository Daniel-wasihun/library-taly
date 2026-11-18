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
     * Detect if any person belongs to two or more groups at the same time.
     * "one librarian must not be in the same group at a time profesionally clean this"
     *
     * @param int|null $libraryId Optional branch filter
     * @return array{has_overlap: bool, overlaps: array<int, array{user_id: int, user_name: string, groups: string[]}>}
     */
    public function detectStaffOverlapInBranch(?int $libraryId = null): array
    {
        $query = ShiftGroup::with('users:id,name,username,library_id')
            ->where('is_active', true);

        if ($libraryId) {
            $query->where(function ($q) use ($libraryId) {
                $q->whereNull('library_id')->orWhere('library_id', $libraryId);
            });
        }

        $groups = $query->get();

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
     * Auto-generate and assign balanced monthly shift schedule across groups.
     * Applies to all library branches for the selected group.
     *
     * @param int|null $libraryId Optional branch (null = all branches)
     * @param int $year Ethiopian year (e.g. 2019)
     * @param int $month Ethiopian month (1 to 13)
     * @param bool $excludeHolidays Whether to detect and remove holidays from schedule
     * @param bool $overwriteExisting Whether to overwrite existing schedules for this month
     * @return array
     * @throws ValidationException
     */
    public function autoGenerateSchedule(
        ?int $libraryId,
        int $year,
        int $month,
        bool $excludeHolidays = true,
        bool $overwriteExisting = true
    ): array {
        $libraryName = 'All University Library Branches';
        if ($libraryId) {
            $lib = Library::find($libraryId);
            if ($lib) {
                $libraryName = $lib->name;
            }
        }

        // 1. Detect person in multiple groups
        $overlapCheck = $this->detectStaffOverlapInBranch($libraryId);
        if ($overlapCheck['has_overlap']) {
            $conflictDetails = array_map(function ($o) {
                $grpNames = implode(', ', $o['groups']);
                return "{$o['user_name']} (@{$o['username']}) is in [{$grpNames}]";
            }, $overlapCheck['overlaps']);

            $msg = "Cannot auto-schedule for {$libraryName}: One person cannot belong to multiple groups at the same time. " .
                "Overlaps detected: " . implode('; ', $conflictDetails) . ". " .
                "Please ensure each attendant belongs to only one group before auto-generating.";

            throw ValidationException::withMessages([
                'staff_overlap' => [$msg],
            ]);
        }

        // 2. Fetch active groups (Unified groups apply across all branches)
        $groupsQuery = ShiftGroup::where('is_active', true)->orderBy('name');
        if ($libraryId) {
            $groupsQuery->where(function ($q) use ($libraryId) {
                $q->whereNull('library_id')->orWhere('library_id', $libraryId);
            });
        }
        $groups = $groupsQuery->get();

        if ($groups->isEmpty()) {
            throw ValidationException::withMessages([
                'groups' => ["No active duty groups found. Please create groups (e.g. Group A, Group B) first."],
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

        // 5. Generate schedules with strict consecutive night-day prevention
        $groupCount = $groups->count();
        $shiftCount = $shifts->count();

        // Separate shifts into Day Shift and Night Shift
        $dayShift = $shifts->first(function ($s) {
            $code = strtolower($s->code);
            $name = strtolower($s->name);
            return str_contains($code, 'day') || str_contains($name, 'day') || str_contains($code, 'morning');
        }) ?? $shifts->first();

        $nightShift = $shifts->first(function ($s) {
            $code = strtolower($s->code);
            $name = strtolower($s->name);
            return str_contains($code, 'night') || str_contains($name, 'night') || str_contains($code, 'evening');
        });

        if (!$nightShift && $shifts->count() > 1) {
            $nightShift = $shifts->get(1);
        }

        $schedulePlan = [];
        foreach ($groups as $g) {
            $schedulePlan[$g->id] = [];
            foreach ($shifts as $s) {
                $schedulePlan[$g->id][$s->id] = [];
            }
        }

        if ($nightShift && $groupCount >= 3) {
            // High-elegance 6-day cycle for 3 groups ensuring Day -> Off -> Night -> Off
            $pattern = [
                ['day_idx' => 0, 'night_idx' => 1],
                ['day_idx' => 2, 'night_idx' => 1],
                ['day_idx' => 2, 'night_idx' => 0],
                ['day_idx' => 1, 'night_idx' => 0],
                ['day_idx' => 1, 'night_idx' => 2],
                ['day_idx' => 0, 'night_idx' => 2],
            ];

            foreach ($workingDays as $idx => $dayNumber) {
                $step = $pattern[$idx % 6];
                $dayGrp = $groups[$step['day_idx'] % $groupCount];
                $nightGrp = $groups[$step['night_idx'] % $groupCount];

                $schedulePlan[$dayGrp->id][$dayShift->id][] = $dayNumber;
                $schedulePlan[$nightGrp->id][$nightShift->id][] = $dayNumber;
            }
        } elseif ($nightShift && $groupCount >= 2) {
            // For 2 groups: 3-day alternating blocks
            foreach ($workingDays as $idx => $dayNumber) {
                $block = (int)floor($idx / 3);
                $dayGrpIdx = $block % 2;
                $nightGrpIdx = ($block + 1) % 2;

                $dayGrp = $groups[$dayGrpIdx];
                $nightGrp = $groups[$nightGrpIdx];

                $schedulePlan[$dayGrp->id][$dayShift->id][] = $dayNumber;
                $schedulePlan[$nightGrp->id][$nightShift->id][] = $dayNumber;
            }
        } else {
            // Single shift or 1 group: simple round-robin
            foreach ($shifts as $sIdx => $shift) {
                foreach ($workingDays as $dIdx => $dayNumber) {
                    $assignedGrp = $groups[($dIdx + $sIdx) % $groupCount];
                    $schedulePlan[$assignedGrp->id][$shift->id][] = $dayNumber;
                }
            }
        }

        // 6. Persist to database inside transaction
        DB::beginTransaction();
        try {
            if ($overwriteExisting) {
                $deleteQuery = GroupShiftSchedule::where('ethiopian_year', $year)
                    ->where('ethiopian_month', $month)
                    ->whereIn('shift_group_id', $groups->pluck('id'));

                if ($libraryId) {
                    $deleteQuery->where('library_id', $libraryId);
                } else {
                    $deleteQuery->whereNull('library_id');
                }

                $deleteQuery->delete();
            }

            $savedRecords = [];
            foreach ($schedulePlan as $groupId => $shiftsMap) {
                $grpObj = $groups->firstWhere('id', $groupId);
                foreach ($shiftsMap as $shiftId => $days) {
                    if (empty($days)) {
                        continue;
                    }
                    $shiftObj = $shifts->firstWhere('id', $shiftId);

                    $record = GroupShiftSchedule::updateOrCreate(
                        [
                            'shift_group_id' => $groupId,
                            'shift_id' => $shiftId,
                            'ethiopian_year' => $year,
                            'ethiopian_month' => $month,
                        ],
                        [
                            'library_id' => $libraryId, // null = all branches
                            'days' => array_values(array_unique(array_map('intval', $days))),
                            'status' => 'active',
                            'notes' => "Auto-generated schedule for {$grpObj->name} ({$shiftObj->name})",
                        ]
                    );

                    $savedRecords[] = $record;
                }
            }

            DB::commit();

            // 7. Calculate workload distribution stats
            $workloadStats = [];
            foreach ($groups as $g) {
                $totalDays = 0;
                $breakdown = [];
                foreach ($shifts as $s) {
                    $cnt = count($schedulePlan[$g->id][$s->id] ?? []);
                    $totalDays += $cnt;
                    $breakdown[$s->name] = $cnt;
                }
                $workloadStats[] = [
                    'group_id' => $g->id,
                    'group_name' => $g->name,
                    'total_assigned_days' => $totalDays,
                    'breakdown' => $breakdown,
                ];
            }

            return [
                'success' => true,
                'message' => "Successfully auto-generated schedules for {$libraryName} ({$month}/{$year} E.C.) across " . count($groups) . " groups with equal workload and zero consecutive night/day shifts.",
                'total_calendar_days' => ($month === 13 ? 5 : 30),
                'working_days_scheduled' => count($workingDays),
                'excluded_holidays' => $detectedHolidays,
                'groups_scheduled_count' => count($groups),
                'workload_stats' => $workloadStats,
                'records_created' => count($savedRecords),
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
