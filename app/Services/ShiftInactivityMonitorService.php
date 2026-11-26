<?php

namespace App\Services;

use App\Models\GroupShiftSchedule;
use App\Models\Library;
use App\Models\LibraryAttendance;
use App\Models\Shift;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShiftInactivityMonitorService
{
    public const INACTIVITY_THRESHOLD_MINUTES = 30;

    /**
     * Get real-time shift inactivity alerts for the currently active shift.
     * Evaluates whether attendants on duty have failed to record visitor counts for >= 30 minutes.
     *
     * @param int|null $libraryId Optional branch filter
     * @param Carbon|null $now Current time instance (defaults to Africa/Addis_Ababa now)
     * @return array
     */
    public function getCurrentShiftInactivityAlerts(?int $libraryId = null, ?Carbon $now = null, ?string $targetDate = null, ?int $targetShiftId = null): array
    {
        $nowInstance = ($now ?? Carbon::now('Africa/Addis_Ababa'))->setTimezone('Africa/Addis_Ababa');
        $todayDateStr = $nowInstance->toDateString();
        $targetSchedDate = $targetDate ?: $todayDateStr;

        // 1. Determine shift to monitor (explicit target shift or auto-detected live shift)
        $allShifts = Shift::where('is_active', true)->get();
        $activeShift = null;
        $shiftStartDateTime = null;
        $shiftEndDateTime = null;

        if ($targetShiftId) {
            $activeShift = $allShifts->firstWhere('id', (int) $targetShiftId);
        }

        if ($activeShift) {
            $isOvernight = ($activeShift->start_time > $activeShift->end_time);
            $shiftStartDateTime = Carbon::parse("{$targetSchedDate} {$activeShift->start_time}", 'Africa/Addis_Ababa');
            $shiftEndDateTime = $isOvernight
                ? Carbon::parse("{$targetSchedDate} {$activeShift->end_time}", 'Africa/Addis_Ababa')->addDay()
                : Carbon::parse("{$targetSchedDate} {$activeShift->end_time}", 'Africa/Addis_Ababa');

            if ($targetSchedDate === $todayDateStr) {
                $evalTime = clone $nowInstance;
                if ($evalTime->gt($shiftEndDateTime)) {
                    $evalTime = clone $shiftEndDateTime;
                }
                if ($evalTime->lt($shiftStartDateTime)) {
                    $evalTime = clone $shiftStartDateTime;
                }
            } else {
                $evalTime = clone $shiftEndDateTime;
            }
        } else {
            // Auto-detect based on current time window for today or target date
            $evalTime = clone $nowInstance;
            $currentTimeStr = $evalTime->format('H:i');

            foreach ($allShifts as $shift) {
                $isOvernight = ($shift->start_time > $shift->end_time);

                if (!$isOvernight) {
                    if ($currentTimeStr >= $shift->start_time && $currentTimeStr <= $shift->end_time) {
                        $activeShift = $shift;
                        $shiftStartDateTime = Carbon::parse("{$todayDateStr} {$shift->start_time}", 'Africa/Addis_Ababa');
                        $shiftEndDateTime = Carbon::parse("{$todayDateStr} {$shift->end_time}", 'Africa/Addis_Ababa');
                        $targetSchedDate = $todayDateStr;
                        break;
                    }
                } else {
                    if ($currentTimeStr >= $shift->start_time) {
                        $activeShift = $shift;
                        $shiftStartDateTime = Carbon::parse("{$todayDateStr} {$shift->start_time}", 'Africa/Addis_Ababa');
                        $shiftEndDateTime = Carbon::parse("{$todayDateStr} {$shift->end_time}", 'Africa/Addis_Ababa')->addDay();
                        $targetSchedDate = $todayDateStr;
                        break;
                    } elseif ($currentTimeStr <= $shift->end_time) {
                        $activeShift = $shift;
                        $yesterdayDate = (clone $evalTime)->subDay()->toDateString();
                        $shiftStartDateTime = Carbon::parse("{$yesterdayDate} {$shift->start_time}", 'Africa/Addis_Ababa');
                        $shiftEndDateTime = Carbon::parse("{$todayDateStr} {$shift->end_time}", 'Africa/Addis_Ababa');
                        $targetSchedDate = $yesterdayDate;
                        break;
                    }
                }
            }

            // Fallback: if no shift is currently running (e.g. between 05:00 and 08:00), default to the primary Day Shift
            if (!$activeShift && $allShifts->isNotEmpty()) {
                $activeShift = $allShifts->first();
                $isOvernight = ($activeShift->start_time > $activeShift->end_time);
                $shiftStartDateTime = Carbon::parse("{$targetSchedDate} {$activeShift->start_time}", 'Africa/Addis_Ababa');
                $shiftEndDateTime = $isOvernight
                    ? Carbon::parse("{$targetSchedDate} {$activeShift->end_time}", 'Africa/Addis_Ababa')->addDay()
                    : Carbon::parse("{$targetSchedDate} {$activeShift->end_time}", 'Africa/Addis_Ababa');
            }
        }

        if (!$activeShift || !$shiftStartDateTime) {
            return [
                'has_active_incidents' => false,
                'incident_count' => 0,
                'critical_count' => 0,
                'warning_count' => 0,
                'has_active_branches' => false,
                'active_branches_count' => 0,
                'total_branches_monitored' => 0,
                'threshold_minutes' => self::INACTIVITY_THRESHOLD_MINUTES,
                'evaluated_at' => $evalTime->toIso8601String(),
                'evaluated_at_formatted' => $evalTime->format('M j, Y h:i A (T)'),
                'active_shift' => null,
                'alerts' => [],
                'active_branches' => [],
            ];
        }

        // 2. Convert shift start date to Ethiopian calendar to query GroupShiftSchedule
        [$eYear, $eMonth, $eDay] = EthiopianCalendarService::gregorianToEthiopian(
            (int) $shiftStartDateTime->year,
            (int) $shiftStartDateTime->month,
            (int) $shiftStartDateTime->day
        );

        $schedules = GroupShiftSchedule::with(['group.users.roles', 'shift'])
            ->where('shift_id', $activeShift->id)
            ->where('ethiopian_year', $eYear)
            ->where('ethiopian_month', $eMonth)
            ->where('status', 'active')
            ->get();

        $activeGroupSchedule = $schedules->first(function ($sched) use ($eDay) {
            $days = is_array($sched->days) ? $sched->days : [];
            return in_array((int) $eDay, array_map('intval', $days), true);
        });

        // 3. Target branches query
        $branchesQuery = Library::query();
        if ($libraryId) {
            $branchesQuery->where('id', $libraryId);
        }
        $branches = $branchesQuery->orderBy('name')->get();

        $shiftElapsedMinutes = $shiftStartDateTime->diffInMinutes($evalTime);
        $alerts = [];
        $activeBranches = [];
        $pastLapseAlerts = [];
        $criticalCount = 0;
        $warningCount = 0;

        foreach ($branches as $branch) {
            // Find attendants scheduled for this branch in the active group
            $scheduledAttendants = collect();

            if ($activeGroupSchedule && $activeGroupSchedule->group) {
                $scheduledAttendants = $activeGroupSchedule->group->users
                    ->filter(function ($u) use ($branch) {
                        return (int) $u->library_id === (int) $branch->id;
                    })
                    ->values();
            }

            // Fallback: If no group schedule users matched or group is unassigned, check DutySchedule
            if ($scheduledAttendants->isEmpty()) {
                $individualDutyUsers = User::whereHas('dutySchedules', function ($q) use ($targetSchedDate, $branch) {
                    $q->where('shift_date', $targetSchedDate)
                        ->where('task_key', 'attendance_desk')
                        ->whereIn('status', ['scheduled', 'active'])
                        ->where(function ($sq) use ($branch) {
                            $sq->whereNull('library_id')->orWhere('library_id', $branch->id);
                        });
                })->get();

                if ($individualDutyUsers->isNotEmpty()) {
                    $scheduledAttendants = $individualDutyUsers;
                }
            }

            // Query attendance records logged in this branch during this active shift (using resilient multi-format timestamps)
            $shiftStartUtc = (clone $shiftStartDateTime)->utc();
            $evalTimeUtc = (clone $evalTime)->utc();

            $records = LibraryAttendance::where('library_id', $branch->id)
                ->where(function ($q) use ($shiftStartDateTime, $evalTime, $shiftStartUtc, $evalTimeUtc) {
                    $q->where(function ($sq) use ($shiftStartDateTime, $evalTime) {
                        $sq->where('entry_at', '>=', $shiftStartDateTime->toDateTimeString())
                           ->where('entry_at', '<=', $evalTime->toDateTimeString());
                    })->orWhere(function ($sq) use ($shiftStartUtc, $evalTimeUtc) {
                        $sq->where('entry_at', '>=', $shiftStartUtc->toDateTimeString())
                           ->where('entry_at', '<=', $evalTimeUtc->toDateTimeString());
                    })->orWhere(function ($sq) use ($shiftStartDateTime, $evalTime) {
                        $sq->where('entry_at', '>=', $shiftStartDateTime->toIso8601String())
                           ->where('entry_at', '<=', $evalTime->toIso8601String());
                    });
                })
                ->orderBy('entry_at', 'asc')
                ->get(['id', 'entry_at', 'visitor_count', 'staff_id', 'created_at']);

            $totalShiftEntries = (int) $records->sum('visitor_count');

            // Compute any resolved past inactivity lapses (>= 30 mins) earlier in this shift
            // NOTE: Per requirement, the inactive duration counts ONLY the time AFTER 30 minutes (30m grace excluded)
            $pastLapses = [];
            if ($records->isNotEmpty()) {
                // 1. Shift Start Delay: Did attendants delay >= 30m before first recording?
                $firstRecord = $records->first();
                $firstRecordAt = $this->parseEntryDateTime($firstRecord->entry_at);
                $startGap = (int) $shiftStartDateTime->diffInMinutes($firstRecordAt);
                if ($startGap >= self::INACTIVITY_THRESHOLD_MINUTES) {
                    $countedStartGap = $startGap - self::INACTIVITY_THRESHOLD_MINUTES;
                    $pastLapses[] = [
                        'type' => 'SHIFT_START_DELAY',
                        'type_label' => 'Shift Start Delay',
                        'type_label_am' => 'የፈረቃ መጀመሪያ መዘግየት',
                        'from' => $shiftStartDateTime->toIso8601String(),
                        'to' => $firstRecordAt->toIso8601String(),
                        'from_formatted' => $shiftStartDateTime->format('h:i A'),
                        'to_formatted' => $firstRecordAt->format('h:i A'),
                        'interval_formatted' => "{$shiftStartDateTime->format('h:i A')} – {$firstRecordAt->format('h:i A')}",
                        'interval_ethiopian' => "{$this->formatEthiopianTime($shiftStartDateTime)} – {$this->formatEthiopianTime($firstRecordAt)}",
                        'duration_minutes' => $countedStartGap,
                        'duration_human' => $this->formatDurationHuman($countedStartGap),
                        'raw_gap_minutes' => $startGap,
                        'resumed_at' => $firstRecordAt->format('h:i A'),
                        'resumed_at_ethiopian' => $this->formatEthiopianTime($firstRecordAt),
                    ];
                }

                // 2. Intermittent Inactivity: Gaps >= 30m between consecutive counts
                for ($i = 0; $i < count($records) - 1; $i++) {
                    $currAt = $this->parseEntryDateTime($records[$i]->entry_at);
                    $nextAt = $this->parseEntryDateTime($records[$i + 1]->entry_at);
                    $midGap = (int) $currAt->diffInMinutes($nextAt);
                    if ($midGap >= self::INACTIVITY_THRESHOLD_MINUTES) {
                        $countedMidGap = $midGap - self::INACTIVITY_THRESHOLD_MINUTES;
                        $pastLapses[] = [
                            'type' => 'INTERMITTENT_INACTIVITY',
                            'type_label' => 'Intermittent Inactivity Lapse',
                            'type_label_am' => 'በቆጠራዎች መካከል የነበረ ክፍተት',
                            'from' => $currAt->toIso8601String(),
                            'to' => $nextAt->toIso8601String(),
                            'from_formatted' => $currAt->format('h:i A'),
                            'to_formatted' => $nextAt->format('h:i A'),
                            'interval_formatted' => "{$currAt->format('h:i A')} – {$nextAt->format('h:i A')}",
                            'interval_ethiopian' => "{$this->formatEthiopianTime($currAt)} – {$this->formatEthiopianTime($nextAt)}",
                            'duration_minutes' => $countedMidGap,
                            'duration_human' => $this->formatDurationHuman($countedMidGap),
                            'raw_gap_minutes' => $midGap,
                            'resumed_at' => $nextAt->format('h:i A'),
                            'resumed_at_ethiopian' => $this->formatEthiopianTime($nextAt),
                        ];
                    }
                }
            }

            // Evaluate current inactivity:
            // Shift must have run for at least 30 minutes to trigger inactivity
            if ($shiftElapsedMinutes < self::INACTIVITY_THRESHOLD_MINUTES) {
                if ($records->isNotEmpty()) {
                    // Already counting early in the shift - mark as active
                    $isInactive = false;
                } else {
                    // Startup grace period without counts yet
                    continue;
                }
            } else {
                $isInactive = false;
                $inactiveSince = null;
                $inactiveDurationMinutes = 0;
                $rawInactiveGap = 0;
                $statusType = 'COMPLIANT';
                $lastEntryTimeStr = null;

                if ($records->isEmpty()) {
                    // Zero records logged throughout the shift so far!
                    $isInactive = true;
                    $inactiveSince = $shiftStartDateTime;
                    $rawGap = (int) $shiftStartDateTime->diffInMinutes($evalTime);
                    $inactiveDurationMinutes = max(0, $rawGap - self::INACTIVITY_THRESHOLD_MINUTES);
                    $rawInactiveGap = $rawGap;
                    $statusType = 'CRITICAL_NO_COUNTS';
                } else {
                    // Records exist: check gap since the last recorded entry
                    $lastRecord = $records->last();
                    $lastEntryAt = $this->parseEntryDateTime($lastRecord->entry_at);
                    $gapMinutes = (int) $lastEntryAt->diffInMinutes($evalTime);
                    $lastEntryTimeStr = $lastEntryAt->format('h:i A');

                    if ($gapMinutes >= self::INACTIVITY_THRESHOLD_MINUTES) {
                        $isInactive = true;
                        $inactiveSince = $lastEntryAt;
                        $inactiveDurationMinutes = max(0, $gapMinutes - self::INACTIVITY_THRESHOLD_MINUTES);
                        $rawInactiveGap = $gapMinutes;
                        $statusType = 'INACTIVE_GAP';
                    }
                }
            }

            if ($isInactive) {
                $severity = ($inactiveDurationMinutes >= 60) ? 'critical' : 'warning';
                if ($severity === 'critical') {
                    $criticalCount++;
                } else {
                    $warningCount++;
                }

                $sinceGregorian = $inactiveSince->format('h:i A');
                $nowGregorian = $evalTime->format('h:i A');
                $intervalFormatted = "{$sinceGregorian} – {$nowGregorian}";

                // Ethiopian time representation
                $etSince = $this->formatEthiopianTime($inactiveSince);
                $etNow = $this->formatEthiopianTime($evalTime);
                $intervalEthiopian = "{$etSince} – {$etNow}";

                $attendantList = $scheduledAttendants->map(function ($u) {
                    return [
                        'id' => $u->id,
                        'name' => $u->name,
                        'username' => $u->username ?? '',
                        'email' => $u->email,
                        'phone' => $u->phone ?? '—',
                        'role' => $u->roles->first()?->name ?? 'Attendant',
                    ];
                })->toArray();

                $shift12hWindow = Carbon::parse($activeShift->start_time)->format('h:i A') . ' – ' . Carbon::parse($activeShift->end_time)->format('h:i A');

                $alerts[] = [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'branch_name_am' => $branch->name_am ?? $branch->name,
                    'branch_code' => $branch->code,
                    'shift_id' => $activeShift->id,
                    'shift_name' => $activeShift->name,
                    'shift_name_am' => $activeShift->name_am ?? $activeShift->name,
                    'shift_time_window' => $shift12hWindow,
                    'shift_time_ethiopian' => "{$activeShift->ethiopian_start_time} – {$activeShift->ethiopian_end_time}",
                    'shift_group_id' => $activeGroupSchedule?->shift_group_id,
                    'shift_group_name' => $activeGroupSchedule?->group?->name ?? 'Assigned Shift Group',
                    'scheduled_attendants_count' => count($attendantList),
                    'attendants' => $attendantList,
                    'inactive_since' => $inactiveSince->toIso8601String(),
                    'inactive_since_formatted' => $inactiveSince->format('h:i A'),
                    'inactive_duration_minutes' => $inactiveDurationMinutes,
                    'inactive_duration_human' => $this->formatDurationHuman($inactiveDurationMinutes),
                    'raw_gap_minutes' => $rawInactiveGap,
                    'interval_formatted' => $intervalFormatted,
                    'interval_ethiopian' => $intervalEthiopian,
                    'last_entry_at' => $lastEntryTimeStr,
                    'total_entries_recorded' => $totalShiftEntries,
                    'status' => $statusType,
                    'severity' => $severity,
                    'has_past_lapses' => count($pastLapses) > 0,
                    'past_lapses_count' => count($pastLapses),
                    'past_lapses' => $pastLapses,
                ];

                if (count($pastLapses) > 0) {
                    $firstLapse = $pastLapses[0];
                    $pastLapseAlerts[] = [
                        'branch_id' => $branch->id,
                        'branch_name' => $branch->name,
                        'branch_name_am' => $branch->name_am ?? $branch->name,
                        'branch_code' => $branch->code,
                        'shift_name' => $activeShift->name,
                        'lapses' => $pastLapses,
                        'total_lapses' => count($pastLapses),
                        'total_lapse_minutes' => array_sum(array_column($pastLapses, 'duration_minutes')),
                        'total_lapse_human' => $this->formatDurationHuman(array_sum(array_column($pastLapses, 'duration_minutes'))),
                        'duration_human' => $firstLapse['duration_human'] ?? $this->formatDurationHuman(array_sum(array_column($pastLapses, 'duration_minutes'))),
                        'duration_minutes' => $firstLapse['duration_minutes'] ?? 0,
                        'interval_formatted' => $firstLapse['interval_formatted'] ?? '—',
                        'interval_ethiopian' => $firstLapse['interval_ethiopian'] ?? '—',
                        'resumed_at' => $firstLapse['resumed_at'] ?? '—',
                        'resumed_at_ethiopian' => $firstLapse['resumed_at_ethiopian'] ?? '—',
                        'type_label' => $firstLapse['type_label'] ?? 'Inactivity Lapse',
                        'type_label_am' => $firstLapse['type_label_am'] ?? 'የሥራ ማቆም ክፍተት',
                        'attendants' => $attendantList,
                        'is_currently_active' => false,
                    ];
                }
            } elseif ($records->isNotEmpty()) {
                // Branch is ACTIVE & COMPLIANT: At least one attendant has logged counts within the 30-min window!
                $lastRecord = $records->last();
                $lastEntryAt = $this->parseEntryDateTime($lastRecord->entry_at);
                $gapMinutes = (int) $lastEntryAt->diffInMinutes($evalTime);

                // Group entries by staff_id to calculate activity per attendant / manager
                $staffActivity = $records->groupBy('staff_id');
                $staffIds = $staffActivity->keys()->filter()->map(fn ($id) => (int) $id)->all();
                $staffUsers = User::with('roles')->whereIn('id', $staffIds)->get()->keyBy('id');

                $hasManagementOperator = false;
                $workingAttendants = [];
                foreach ($staffActivity as $sId => $staffRecords) {
                    $sUser = $staffUsers->get($sId);
                    $sLastRecord = $staffRecords->last();
                    $sLastAt = $this->parseEntryDateTime($sLastRecord->entry_at);
                    $sGapMinutes = (int) $sLastAt->diffInMinutes($evalTime);

                    $roleName = $sUser?->roles->first()?->name ?? 'Attendant';
                    $isManagement = ($sUser && ($sUser->hasAdminPrivilege() || $sUser->hasRole('Manager') || $sUser->can('libraries.manage')));
                    if ($isManagement) {
                        $hasManagementOperator = true;
                    }

                    $workingAttendants[] = [
                        'id' => (int) $sId,
                        'name' => $sUser?->name ?? "Staff #{$sId}",
                        'username' => $sUser?->username ?? '',
                        'email' => $sUser?->email ?? '',
                        'phone' => $sUser?->phone ?? '—',
                        'role' => $roleName,
                        'is_management' => $isManagement,
                        'counts_logged' => (int) $staffRecords->sum('visitor_count'),
                        'batches_logged' => $staffRecords->count(),
                        'last_entry_at' => $sLastAt->toIso8601String(),
                        'last_entry_formatted' => $sLastAt->format('h:i A'),
                        'last_entry_ethiopian' => $this->formatEthiopianTime($sLastAt),
                        'last_entry_minutes_ago' => $sGapMinutes,
                        'is_current_logger' => ((int) $sId === (int) $lastRecord->staff_id),
                    ];
                }

                // Sort working attendants: most recently active first
                usort($workingAttendants, fn ($a, $b) => $a['last_entry_minutes_ago'] <=> $b['last_entry_minutes_ago']);

                $scheduledAttendantList = $scheduledAttendants->map(function ($u) {
                    return [
                        'id' => (int) $u->id,
                        'name' => $u->name,
                        'username' => $u->username ?? '',
                        'email' => $u->email,
                        'phone' => $u->phone ?? '—',
                        'role' => $u->roles->first()?->name ?? 'Attendant',
                    ];
                })->toArray();

                // Check whether scheduled attendants logged or if management stepped in
                $scheduledAttendantIds = $scheduledAttendants->pluck('id')->map(fn ($id) => (int) $id)->all();
                $scheduledAttendantsLoggedCount = count(array_intersect($staffIds, $scheduledAttendantIds));
                $managementSteppedIn = ($hasManagementOperator && $scheduledAttendantsLoggedCount === 0);

                // Non-working scheduled attendants (scheduled on duty but management or colleagues are handling counts)
                $workingUserIds = array_column($workingAttendants, 'id');
                $otherScheduledAttendants = array_values(array_filter(
                    $scheduledAttendantList,
                    fn ($att) => !in_array((int) $att['id'], $workingUserIds, true)
                ));

                $shift12hWindow = Carbon::parse($activeShift->start_time)->format('h:i A') . ' – ' . Carbon::parse($activeShift->end_time)->format('h:i A');

                $activeBranches[] = [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'branch_name_am' => $branch->name_am ?? $branch->name,
                    'branch_code' => $branch->code,
                    'shift_id' => $activeShift->id,
                    'shift_name' => $activeShift->name,
                    'shift_name_am' => $activeShift->name_am ?? $activeShift->name,
                    'shift_time_window' => $shift12hWindow,
                    'shift_time_ethiopian' => "{$activeShift->ethiopian_start_time} – {$activeShift->ethiopian_end_time}",
                    'shift_group_id' => $activeGroupSchedule?->shift_group_id,
                    'shift_group_name' => $activeGroupSchedule?->group?->name ?? 'Assigned Shift Group',
                    'total_entries_recorded' => $totalShiftEntries,
                    'total_batches_count' => $records->count(),
                    'last_entry_at' => $lastEntryAt->toIso8601String(),
                    'last_entry_formatted' => $lastEntryAt->format('h:i A'),
                    'last_entry_ethiopian' => $this->formatEthiopianTime($lastEntryAt),
                    'last_entry_minutes_ago' => $gapMinutes,
                    'working_attendants_count' => count($workingAttendants),
                    'working_attendants' => $workingAttendants,
                    'scheduled_attendants_count' => count($scheduledAttendantList),
                    'scheduled_attendants' => $scheduledAttendantList,
                    'other_scheduled_attendants' => $otherScheduledAttendants,
                    'has_management_operator' => $hasManagementOperator,
                    'management_stepped_in' => $managementSteppedIn,
                    'management_notice' => $managementSteppedIn ? 'Desk operated by Management (Shift attendants inactive or absent)' : null,
                    'management_notice_am' => $managementSteppedIn ? 'ቆጠራው በአስተዳደር እየተካሄደ ነው (የፈረቃው ሠራተኞች አልመዘገቡም)' : null,
                    'has_past_lapses' => count($pastLapses) > 0,
                    'past_lapses_count' => count($pastLapses),
                    'past_lapses' => $pastLapses,
                    'status' => $managementSteppedIn ? 'ACTIVE_MANAGEMENT_OVERRIDE' : 'ACTIVE_COMPLIANT',
                ];

                if (count($pastLapses) > 0) {
                    $firstLapse = $pastLapses[0];
                    $pastLapseAlerts[] = [
                        'branch_id' => $branch->id,
                        'branch_name' => $branch->name,
                        'branch_name_am' => $branch->name_am ?? $branch->name,
                        'branch_code' => $branch->code,
                        'shift_name' => $activeShift->name,
                        'lapses' => $pastLapses,
                        'total_lapses' => count($pastLapses),
                        'total_lapse_minutes' => array_sum(array_column($pastLapses, 'duration_minutes')),
                        'total_lapse_human' => $this->formatDurationHuman(array_sum(array_column($pastLapses, 'duration_minutes'))),
                        'duration_human' => $firstLapse['duration_human'] ?? $this->formatDurationHuman(array_sum(array_column($pastLapses, 'duration_minutes'))),
                        'duration_minutes' => $firstLapse['duration_minutes'] ?? 0,
                        'interval_formatted' => $firstLapse['interval_formatted'] ?? '—',
                        'interval_ethiopian' => $firstLapse['interval_ethiopian'] ?? '—',
                        'resumed_at' => $firstLapse['resumed_at'] ?? '—',
                        'resumed_at_ethiopian' => $firstLapse['resumed_at_ethiopian'] ?? '—',
                        'type_label' => $firstLapse['type_label'] ?? 'Inactivity Lapse',
                        'type_label_am' => $firstLapse['type_label_am'] ?? 'የሥራ ማቆም ክፍተት',
                        'attendants' => $scheduledAttendantList,
                        'is_currently_active' => true,
                    ];
                }
            }
        }

        // Sort alerts by longest inactive duration descending
        usort($alerts, fn ($a, $b) => $b['inactive_duration_minutes'] <=> $a['inactive_duration_minutes']);

        // Sort active branches by most recent activity ascending (lowest gap first)
        usort($activeBranches, fn ($a, $b) => $a['last_entry_minutes_ago'] <=> $b['last_entry_minutes_ago']);

        $activeShift12hWindow = Carbon::parse($activeShift->start_time)->format('h:i A') . ' – ' . Carbon::parse($activeShift->end_time)->format('h:i A');

        return [
            'has_active_incidents' => count($alerts) > 0,
            'incident_count' => count($alerts),
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'has_past_lapses' => count($pastLapseAlerts) > 0,
            'past_lapses_count' => count($pastLapseAlerts),
            'past_lapse_alerts' => $pastLapseAlerts,
            'has_active_branches' => count($activeBranches) > 0,
            'active_branches_count' => count($activeBranches),
            'total_branches_monitored' => $branches->count(),
            'threshold_minutes' => self::INACTIVITY_THRESHOLD_MINUTES,
            'selected_date' => $targetSchedDate,
            'selected_shift_id' => $activeShift->id,
            'available_shifts' => $allShifts->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'name_am' => $s->name_am ?? $s->name,
                'time_window' => Carbon::parse($s->start_time)->format('h:i A') . ' – ' . Carbon::parse($s->end_time)->format('h:i A'),
                'time_window_ethiopian' => "{$s->ethiopian_start_time} – {$s->ethiopian_end_time}",
            ])->values()->toArray(),
            'evaluated_at' => $evalTime->toIso8601String(),
            'evaluated_at_formatted' => $evalTime->format('M j, Y h:i A (T)'),
            'active_shift' => [
                'id' => $activeShift->id,
                'name' => $activeShift->name,
                'name_am' => $activeShift->name_am ?? $activeShift->name,
                'time_window' => $activeShift12hWindow,
                'time_window_ethiopian' => "{$activeShift->ethiopian_start_time} – {$activeShift->ethiopian_end_time}",
                'group_name' => $activeGroupSchedule?->group?->name ?? 'Duty Group',
            ],
            'alerts' => $alerts,
            'active_branches' => $activeBranches,
        ];
    }

    /**
     * Generate an audit report of all inactivity incidents for a specific date or shift.
     * Identifies all intervals >= 30 minutes without counting activity.
     *
     * @param string|null $date Gregorian date string (defaults to today)
     * @param int|null $libraryId Optional branch filter
     * @param int|null $shiftId Optional shift filter
     * @return array
     */
    public function getShiftInactivityReport(?string $date = null, ?int $libraryId = null, ?int $shiftId = null): array
    {
        $now = Carbon::now('Africa/Addis_Ababa');
        $targetDate = $date ?? $now->toDateString();
        $targetCarbon = Carbon::parse($targetDate, 'Africa/Addis_Ababa');

        [$eYear, $eMonth, $eDay] = EthiopianCalendarService::gregorianToEthiopian(
            (int) $targetCarbon->year,
            (int) $targetCarbon->month,
            (int) $targetCarbon->day
        );

        $ethiopianDateFormatted = EthiopianDateService::formatDT($targetCarbon, true);

        // Fetch shifts to audit
        $shiftQuery = Shift::where('is_active', true);
        if ($shiftId) {
            $shiftQuery->where('id', $shiftId);
        }
        $shifts = $shiftQuery->get();

        // Fetch branches
        $branchQuery = Library::query();
        if ($libraryId) {
            $branchQuery->where('id', $libraryId);
        }
        $branches = $branchQuery->orderBy('name')->get();

        $incidents = [];
        $totalMonitoredShifts = 0;
        $compliantShifts = 0;
        $nonCompliantShifts = 0;
        $totalInactiveMinutes = 0;

        foreach ($shifts as $shift) {
            $isOvernight = ($shift->start_time > $shift->end_time);

            $shiftStart = Carbon::parse("{$targetDate} {$shift->start_time}", 'Africa/Addis_Ababa');
            $shiftEnd = $isOvernight
                ? Carbon::parse("{$targetDate} {$shift->end_time}", 'Africa/Addis_Ababa')->addDay()
                : Carbon::parse("{$targetDate} {$shift->end_time}", 'Africa/Addis_Ababa');

            // Find group schedule for this shift
            $schedules = GroupShiftSchedule::with(['group.users.roles', 'shift'])
                ->where('shift_id', $shift->id)
                ->where('ethiopian_year', $eYear)
                ->where('ethiopian_month', $eMonth)
                ->where('status', 'active')
                ->get();

            $activeGroupSched = $schedules->first(function ($sched) use ($eDay) {
                $days = is_array($sched->days) ? $sched->days : [];
                return in_array((int) $eDay, array_map('intval', $days), true);
            });

            // Determine evaluation end point (if shift is still in progress, evaluate up to $now)
            $evalEnd = $now->lt($shiftEnd) ? (clone $now) : $shiftEnd;

            // If shift hasn't even started yet on target date, skip
            if ($now->lt($shiftStart) && $targetDate === $now->toDateString()) {
                continue;
            }

            foreach ($branches as $branch) {
                $totalMonitoredShifts++;

                // Assigned attendants for this branch
                $scheduledAttendants = collect();
                if ($activeGroupSched && $activeGroupSched->group) {
                    $scheduledAttendants = $activeGroupSched->group->users
                        ->filter(fn ($u) => (int) $u->library_id === (int) $branch->id)
                        ->values();
                }

                if ($scheduledAttendants->isEmpty()) {
                    $scheduledAttendants = User::whereHas('dutySchedules', function ($q) use ($targetDate, $branch) {
                        $q->where('shift_date', $targetDate)
                            ->where('task_key', 'attendance_desk')
                            ->whereIn('status', ['scheduled', 'active'])
                            ->where(function ($sq) use ($branch) {
                                $sq->whereNull('library_id')->orWhere('library_id', $branch->id);
                            });
                    })->get();
                }

                $attendantsList = $scheduledAttendants->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'username' => $u->username ?? '',
                    'email' => $u->email,
                    'phone' => $u->phone ?? '—',
                    'role' => $u->roles->first()?->name ?? 'Attendant',
                ])->toArray();

                // Attendance logs during this shift window (using resilient multi-format timestamps)
                $shiftStartUtc = (clone $shiftStart)->utc();
                $evalEndUtc = (clone $evalEnd)->utc();

                $records = LibraryAttendance::where('library_id', $branch->id)
                    ->where(function ($q) use ($shiftStart, $evalEnd, $shiftStartUtc, $evalEndUtc) {
                        $q->where(function ($sq) use ($shiftStart, $evalEnd) {
                            $sq->where('entry_at', '>=', $shiftStart->toDateTimeString())
                               ->where('entry_at', '<=', $evalEnd->toDateTimeString());
                        })->orWhere(function ($sq) use ($shiftStartUtc, $evalEndUtc) {
                            $sq->where('entry_at', '>=', $shiftStartUtc->toDateTimeString())
                               ->where('entry_at', '<=', $evalEndUtc->toDateTimeString());
                        })->orWhere(function ($sq) use ($shiftStart, $evalEnd) {
                            $sq->where('entry_at', '>=', $shiftStart->toIso8601String())
                               ->where('entry_at', '<=', $evalEnd->toIso8601String());
                        });
                    })
                    ->orderBy('entry_at', 'asc')
                    ->get(['id', 'entry_at', 'visitor_count', 'staff_id']);

                $totalCount = (int) $records->sum('visitor_count');
                $branchShiftIncidents = [];

                if ($records->isEmpty()) {
                    // Entire monitored interval had 0 counts
                    $duration = (int) $shiftStart->diffInMinutes($evalEnd);
                    if ($duration >= self::INACTIVITY_THRESHOLD_MINUTES) {
                        $countedDuration = max(0, $duration - self::INACTIVITY_THRESHOLD_MINUTES);
                        $branchShiftIncidents[] = [
                            'gap_type' => 'COMPLETE_SHIFT_INACTIVITY',
                            'from' => $shiftStart,
                            'to' => $evalEnd,
                            'duration_minutes' => $countedDuration,
                            'raw_gap_minutes' => $duration,
                            'is_ongoing' => ($evalEnd == $now && $now->lt($shiftEnd)),
                            'resumed_at' => null,
                        ];
                    }
                } else {
                    // Check gap from shift start to first log
                    $firstLogAt = $this->parseEntryDateTime($records->first()->entry_at);
                    $startGap = (int) $shiftStart->diffInMinutes($firstLogAt);
                    if ($startGap >= self::INACTIVITY_THRESHOLD_MINUTES) {
                        $countedStartGap = max(0, $startGap - self::INACTIVITY_THRESHOLD_MINUTES);
                        $branchShiftIncidents[] = [
                            'gap_type' => 'SHIFT_START_DELAY',
                            'from' => $shiftStart,
                            'to' => $firstLogAt,
                            'duration_minutes' => $countedStartGap,
                            'raw_gap_minutes' => $startGap,
                            'is_ongoing' => false,
                            'resumed_at' => $firstLogAt,
                        ];
                    }

                    // Check consecutive record gaps
                    for ($i = 0; $i < count($records) - 1; $i++) {
                        $curr = $this->parseEntryDateTime($records[$i]->entry_at);
                        $next = $this->parseEntryDateTime($records[$i + 1]->entry_at);
                        $midGap = (int) $curr->diffInMinutes($next);

                        if ($midGap >= self::INACTIVITY_THRESHOLD_MINUTES) {
                            $countedMidGap = max(0, $midGap - self::INACTIVITY_THRESHOLD_MINUTES);
                            $branchShiftIncidents[] = [
                                'gap_type' => 'INTERMITTENT_INACTIVITY',
                                'from' => $curr,
                                'to' => $next,
                                'duration_minutes' => $countedMidGap,
                                'raw_gap_minutes' => $midGap,
                                'is_ongoing' => false,
                                'resumed_at' => $next,
                            ];
                        }
                    }

                    // Check gap from last record to evalEnd
                    $lastLogAt = $this->parseEntryDateTime($records->last()->entry_at);
                    $tailGap = (int) $lastLogAt->diffInMinutes($evalEnd);
                    if ($tailGap >= self::INACTIVITY_THRESHOLD_MINUTES) {
                        $countedTailGap = max(0, $tailGap - self::INACTIVITY_THRESHOLD_MINUTES);
                        $branchShiftIncidents[] = [
                            'gap_type' => 'TRAILING_INACTIVITY',
                            'from' => $lastLogAt,
                            'to' => $evalEnd,
                            'duration_minutes' => $countedTailGap,
                            'raw_gap_minutes' => $tailGap,
                            'is_ongoing' => ($evalEnd == $now && $now->lt($shiftEnd)),
                            'resumed_at' => null,
                        ];
                    }
                }

                if (count($branchShiftIncidents) > 0) {
                    $nonCompliantShifts++;
                    foreach ($branchShiftIncidents as $inc) {
                        $totalInactiveMinutes += $inc['duration_minutes'];
                        $shift12hWindow = Carbon::parse($shift->start_time)->format('h:i A') . ' – ' . Carbon::parse($shift->end_time)->format('h:i A');
                        $incidents[] = [
                            'incident_id' => 'INC-' . strtoupper(substr(md5($branch->id . $shift->id . $inc['from']->timestamp), 0, 8)),
                            'branch_id' => $branch->id,
                            'branch_name' => $branch->name,
                            'branch_name_am' => $branch->name_am ?? $branch->name,
                            'branch_code' => $branch->code,
                            'shift_id' => $shift->id,
                            'shift_name' => $shift->name,
                            'shift_name_am' => $shift->name_am ?? $shift->name,
                            'shift_window' => $shift12hWindow,
                            'shift_window_ethiopian' => "{$shift->ethiopian_start_time} – {$shift->ethiopian_end_time}",
                            'shift_group_name' => $activeGroupSched?->group?->name ?? 'Duty Group',
                            'attendants' => $attendantsList,
                            'attendants_count' => count($attendantsList),
                            'gap_type' => $inc['gap_type'],
                            'gap_category' => $inc['is_ongoing'] ? 'ONGOING_LAPSE' : 'RESOLVED_PAST_LAPSE',
                            'type_label' => $this->getGapTypeLabel($inc['gap_type'], false),
                            'type_label_am' => $this->getGapTypeLabel($inc['gap_type'], true),
                            'from_time' => $inc['from']->format('h:i A'),
                            'to_time' => $inc['to']->format('h:i A'),
                            'interval_formatted' => "{$inc['from']->format('h:i A')} – {$inc['to']->format('h:i A')}",
                            'interval_ethiopian' => "{$this->formatEthiopianTime($inc['from'])} – {$this->formatEthiopianTime($inc['to'])}",
                            'duration_minutes' => $inc['duration_minutes'],
                            'duration_human' => $this->formatDurationHuman($inc['duration_minutes']),
                            'raw_gap_minutes' => $inc['raw_gap_minutes'],
                            'is_ongoing' => $inc['is_ongoing'],
                            'status' => $inc['is_ongoing'] ? 'ACTIVE_NON_WORKING' : 'PAST_UNATTENDED_GAP',
                            'severity' => ($inc['duration_minutes'] >= 60) ? 'CRITICAL' : 'WARNING',
                            'resumed_at_formatted' => isset($inc['resumed_at']) && $inc['resumed_at'] ? $inc['resumed_at']->format('h:i A') : null,
                            'resumed_at_ethiopian' => isset($inc['resumed_at']) && $inc['resumed_at'] ? $this->formatEthiopianTime($inc['resumed_at']) : null,
                            'total_shift_entries' => $totalCount,
                        ];
                    }
                } else {
                    $compliantShifts++;
                }
            }
        }

        $complianceRate = $totalMonitoredShifts > 0
            ? round(($compliantShifts / $totalMonitoredShifts) * 100, 1)
            : 100.0;

        return [
            'date_gregorian' => $targetDate,
            'date_gregorian_formatted' => $targetCarbon->format('l, F j, Y'),
            'date_ethiopian' => "{$eDay}/{$eMonth}/{$eYear} E.C.",
            'date_ethiopian_formatted' => $ethiopianDateFormatted['full'] ?? "{$eDay}/{$eMonth}/{$eYear}",
            'threshold_minutes' => self::INACTIVITY_THRESHOLD_MINUTES,
            'summary' => [
                'total_monitored_shifts' => $totalMonitoredShifts,
                'compliant_shifts' => $compliantShifts,
                'non_compliant_shifts' => $nonCompliantShifts,
                'compliance_rate' => $complianceRate,
                'total_incidents' => count($incidents),
                'ongoing_incidents_count' => count(array_filter($incidents, fn ($i) => $i['is_ongoing'])),
                'resolved_past_lapses_count' => count(array_filter($incidents, fn ($i) => !$i['is_ongoing'])),
                'total_inactive_minutes' => $totalInactiveMinutes,
                'total_inactive_hours' => round($totalInactiveMinutes / 60, 1),
                'available_shifts' => $shifts->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                    'name_am' => $s->name_am ?? $s->name,
                    'time_window' => Carbon::parse($s->start_time)->format('h:i A') . ' – ' . Carbon::parse($s->end_time)->format('h:i A'),
                    'time_window_ethiopian' => "{$s->ethiopian_start_time} – {$s->ethiopian_end_time}",
                ]),
                'available_branches' => $branches->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'name_am' => $b->name_am ?? $b->name,
                    'code' => $b->code,
                ]),
            ],
            'incidents' => $incidents,
        ];
    }

    /**
     * Helper to format time into Ethiopian 12-hour cycle (6:00 offset).
     */
    protected function formatEthiopianTime(Carbon $carbon): string
    {
        $h = (int) $carbon->format('G');
        $m = $carbon->format('i');
        $etH = $h >= 6 ? $h - 6 : $h + 6;
        if ($etH > 12) {
            $etH -= 12;
        }
        if ($etH === 0) {
            $etH = 12;
        }

        $period = '';
        if ($h >= 6 && $h < 12) {
            $period = 'ጠዋት';
        } elseif ($h >= 12 && $h < 18) {
            $period = 'ከሰዓት';
        } elseif ($h >= 18 && $h < 24) {
            $period = 'ማታ';
        } else {
            $period = 'ሌሊት';
        }

        return sprintf('%s %02d:%s', $period, $etH, $m);
    }

    /**
     * Helper for human-readable duration.
     */
    protected function formatDurationHuman(int $minutes): string
    {
        if ($minutes < 60) {
            return "{$minutes}m";
        }
        $hrs = intdiv($minutes, 60);
        $remMin = $minutes % 60;
        return $remMin > 0 ? "{$hrs}h {$remMin}m" : "{$hrs}h";
    }

    /**
     * Helper for localized gap type labels.
     */
    protected function getGapTypeLabel(string $gapType, bool $isAmharic = false): string
    {
        return match ($gapType) {
            'SHIFT_START_DELAY' => $isAmharic ? 'የፈረቃ መጀመሪያ መዘግየት' : 'Shift Start Delay',
            'INTERMITTENT_INACTIVITY' => $isAmharic ? 'በፈረቃ መካከል የተፈጠረ ክፍተት' : 'Intermittent Lapse',
            'TRAILING_INACTIVITY' => $isAmharic ? 'የቅርብ ጊዜ የሥራ ማቆም ክፍተት' : 'Trailing Inactivity',
            'COMPLETE_SHIFT_INACTIVITY' => $isAmharic ? 'በሙሉ ፈረቃው ምንም ቆጠራ አልተካሄደም' : 'Complete Shift Absence',
            default => $gapType,
        };
    }

    /**
     * Parse entry_at timestamp safely without erroneous UTC offset double-addition.
     */
    protected function parseEntryDateTime($entryAt): Carbon
    {
        if ($entryAt instanceof Carbon) {
            return (clone $entryAt)->setTimezone('Africa/Addis_Ababa');
        }

        $str = (string) $entryAt;
        if (preg_match('/[+-]\d{2}:?\d{2}$|Z$/', $str)) {
            return Carbon::parse($str)->setTimezone('Africa/Addis_Ababa');
        }

        return Carbon::parse($str, 'Africa/Addis_Ababa');
    }
}
