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
    public function getCurrentShiftInactivityAlerts(?int $libraryId = null, ?Carbon $now = null): array
    {
        $evalTime = ($now ?? Carbon::now('Africa/Addis_Ababa'))->setTimezone('Africa/Addis_Ababa');
        $currentTimeStr = $evalTime->format('H:i');
        $todayDateStr = $evalTime->toDateString();

        // 1. Determine currently active shift(s) based on time window
        $allShifts = Shift::where('is_active', true)->get();
        $activeShift = null;
        $shiftStartDateTime = null;
        $shiftEndDateTime = null;
        $targetSchedDate = $todayDateStr;

        foreach ($allShifts as $shift) {
            $isOvernight = ($shift->start_time > $shift->end_time);

            if (!$isOvernight) {
                // Daytime shift: e.g. 08:00 to 17:00
                if ($currentTimeStr >= $shift->start_time && $currentTimeStr <= $shift->end_time) {
                    $activeShift = $shift;
                    $shiftStartDateTime = Carbon::parse("{$todayDateStr} {$shift->start_time}", 'Africa/Addis_Ababa');
                    $shiftEndDateTime = Carbon::parse("{$todayDateStr} {$shift->end_time}", 'Africa/Addis_Ababa');
                    $targetSchedDate = $todayDateStr;
                    break;
                }
            } else {
                // Overnight shift: e.g. 17:00 to 05:00
                if ($currentTimeStr >= $shift->start_time) {
                    // Evening portion: started today at 17:00, ends tomorrow at 05:00
                    $activeShift = $shift;
                    $shiftStartDateTime = Carbon::parse("{$todayDateStr} {$shift->start_time}", 'Africa/Addis_Ababa');
                    $shiftEndDateTime = Carbon::parse("{$todayDateStr} {$shift->end_time}", 'Africa/Addis_Ababa')->addDay();
                    $targetSchedDate = $todayDateStr;
                    break;
                } elseif ($currentTimeStr <= $shift->end_time) {
                    // Early dawn portion: started yesterday at 17:00, ends today at 05:00
                    $activeShift = $shift;
                    $yesterdayDate = (clone $evalTime)->subDay()->toDateString();
                    $shiftStartDateTime = Carbon::parse("{$yesterdayDate} {$shift->start_time}", 'Africa/Addis_Ababa');
                    $shiftEndDateTime = Carbon::parse("{$todayDateStr} {$shift->end_time}", 'Africa/Addis_Ababa');
                    $targetSchedDate = $yesterdayDate;
                    break;
                }
            }
        }

        if (!$activeShift || !$shiftStartDateTime) {
            return [
                'has_active_incidents' => false,
                'incident_count' => 0,
                'critical_count' => 0,
                'warning_count' => 0,
                'threshold_minutes' => self::INACTIVITY_THRESHOLD_MINUTES,
                'evaluated_at' => $evalTime->toIso8601String(),
                'evaluated_at_formatted' => $evalTime->format('M j, Y h:i A (T)'),
                'active_shift' => null,
                'alerts' => [],
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

            // Query attendance records logged in this branch during this active shift (using UTC for database storage)
            $shiftStartUtc = (clone $shiftStartDateTime)->utc();
            $evalTimeUtc = (clone $evalTime)->utc();

            $records = LibraryAttendance::where('library_id', $branch->id)
                ->where('entry_at', '>=', $shiftStartUtc)
                ->where('entry_at', '<=', $evalTimeUtc)
                ->orderBy('entry_at', 'asc')
                ->get(['id', 'entry_at', 'visitor_count', 'staff_id', 'created_at']);

            $totalShiftEntries = (int) $records->sum('visitor_count');

            // Evaluate inactivity:
            // Shift must have run for at least 30 minutes to trigger inactivity
            if ($shiftElapsedMinutes < self::INACTIVITY_THRESHOLD_MINUTES) {
                continue; // Grace period during shift startup (< 30 min)
            }

            $isInactive = false;
            $inactiveSince = null;
            $inactiveDurationMinutes = 0;
            $statusType = 'COMPLIANT';
            $lastEntryTimeStr = null;

            if ($records->isEmpty()) {
                // Zero records logged throughout the shift so far!
                $isInactive = true;
                $inactiveSince = $shiftStartDateTime;
                $inactiveDurationMinutes = (int) $shiftStartDateTime->diffInMinutes($evalTime);
                $statusType = 'CRITICAL_NO_COUNTS';
            } else {
                // Records exist: check gap since the last recorded entry
                $lastRecord = $records->last();
                $lastEntryAt = Carbon::parse($lastRecord->entry_at, 'UTC')->setTimezone('Africa/Addis_Ababa');
                $gapMinutes = (int) $lastEntryAt->diffInMinutes($evalTime);
                $lastEntryTimeStr = $lastEntryAt->format('H:i');

                if ($gapMinutes >= self::INACTIVITY_THRESHOLD_MINUTES) {
                    $isInactive = true;
                    $inactiveSince = $lastEntryAt;
                    $inactiveDurationMinutes = $gapMinutes;
                    $statusType = 'INACTIVE_GAP';
                }
            }

            if ($isInactive) {
                $severity = ($inactiveDurationMinutes >= 60) ? 'critical' : 'warning';
                if ($severity === 'critical') {
                    $criticalCount++;
                } else {
                    $warningCount++;
                }

                $sinceGregorian = $inactiveSince->format('H:i');
                $nowGregorian = $evalTime->format('H:i');
                $intervalFormatted = "{$sinceGregorian} – {$nowGregorian} (Addis Ababa Time)";

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

                $alerts[] = [
                    'branch_id' => $branch->id,
                    'branch_name' => $branch->name,
                    'branch_name_am' => $branch->name_am ?? $branch->name,
                    'branch_code' => $branch->code,
                    'shift_id' => $activeShift->id,
                    'shift_name' => $activeShift->name,
                    'shift_name_am' => $activeShift->name_am ?? $activeShift->name,
                    'shift_time_window' => "{$activeShift->start_time} – {$activeShift->end_time}",
                    'shift_time_ethiopian' => "{$activeShift->ethiopian_start_time} – {$activeShift->ethiopian_end_time}",
                    'shift_group_id' => $activeGroupSchedule?->shift_group_id,
                    'shift_group_name' => $activeGroupSchedule?->group?->name ?? 'Assigned Shift Group',
                    'scheduled_attendants_count' => count($attendantList),
                    'attendants' => $attendantList,
                    'inactive_since' => $inactiveSince->toIso8601String(),
                    'inactive_since_formatted' => $inactiveSince->format('h:i A'),
                    'inactive_duration_minutes' => $inactiveDurationMinutes,
                    'inactive_duration_human' => $this->formatDurationHuman($inactiveDurationMinutes),
                    'interval_formatted' => $intervalFormatted,
                    'interval_ethiopian' => $intervalEthiopian,
                    'last_entry_at' => $lastEntryTimeStr,
                    'total_entries_recorded' => $totalShiftEntries,
                    'status' => $statusType,
                    'severity' => $severity,
                ];
            }
        }

        // Sort alerts by longest inactive duration descending
        usort($alerts, fn ($a, $b) => $b['inactive_duration_minutes'] <=> $a['inactive_duration_minutes']);

        return [
            'has_active_incidents' => count($alerts) > 0,
            'incident_count' => count($alerts),
            'critical_count' => $criticalCount,
            'warning_count' => $warningCount,
            'threshold_minutes' => self::INACTIVITY_THRESHOLD_MINUTES,
            'evaluated_at' => $evalTime->toIso8601String(),
            'evaluated_at_formatted' => $evalTime->format('M j, Y h:i A (T)'),
            'active_shift' => [
                'id' => $activeShift->id,
                'name' => $activeShift->name,
                'name_am' => $activeShift->name_am ?? $activeShift->name,
                'time_window' => "{$activeShift->start_time} – {$activeShift->end_time}",
                'time_window_ethiopian' => "{$activeShift->ethiopian_start_time} – {$activeShift->ethiopian_end_time}",
                'group_name' => $activeGroupSchedule?->group?->name ?? 'Duty Group',
            ],
            'alerts' => $alerts,
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

                // Attendance logs during this shift window (using UTC for database storage)
                $shiftStartUtc = (clone $shiftStart)->utc();
                $evalEndUtc = (clone $evalEnd)->utc();

                $records = LibraryAttendance::where('library_id', $branch->id)
                    ->where('entry_at', '>=', $shiftStartUtc)
                    ->where('entry_at', '<=', $evalEndUtc)
                    ->orderBy('entry_at', 'asc')
                    ->get(['id', 'entry_at', 'visitor_count', 'staff_id']);

                $totalCount = (int) $records->sum('visitor_count');
                $branchShiftIncidents = [];

                if ($records->isEmpty()) {
                    // Entire monitored interval had 0 counts
                    $duration = (int) $shiftStart->diffInMinutes($evalEnd);
                    if ($duration >= self::INACTIVITY_THRESHOLD_MINUTES) {
                        $branchShiftIncidents[] = [
                            'gap_type' => 'COMPLETE_SHIFT_INACTIVITY',
                            'from' => $shiftStart,
                            'to' => $evalEnd,
                            'duration_minutes' => $duration,
                            'is_ongoing' => ($evalEnd == $now && $now->lt($shiftEnd)),
                        ];
                    }
                } else {
                    // Check gap from shift start to first log
                    $firstLogAt = Carbon::parse($records->first()->entry_at, 'UTC')->setTimezone('Africa/Addis_Ababa');
                    $startGap = (int) $shiftStart->diffInMinutes($firstLogAt);
                    if ($startGap >= self::INACTIVITY_THRESHOLD_MINUTES) {
                        $branchShiftIncidents[] = [
                            'gap_type' => 'SHIFT_START_DELAY',
                            'from' => $shiftStart,
                            'to' => $firstLogAt,
                            'duration_minutes' => $startGap,
                            'is_ongoing' => false,
                        ];
                    }

                    // Check consecutive record gaps
                    for ($i = 0; $i < count($records) - 1; $i++) {
                        $curr = Carbon::parse($records[$i]->entry_at, 'UTC')->setTimezone('Africa/Addis_Ababa');
                        $next = Carbon::parse($records[$i + 1]->entry_at, 'UTC')->setTimezone('Africa/Addis_Ababa');
                        $midGap = (int) $curr->diffInMinutes($next);

                        if ($midGap >= self::INACTIVITY_THRESHOLD_MINUTES) {
                            $branchShiftIncidents[] = [
                                'gap_type' => 'INTERMITTENT_INACTIVITY',
                                'from' => $curr,
                                'to' => $next,
                                'duration_minutes' => $midGap,
                                'is_ongoing' => false,
                            ];
                        }
                    }

                    // Check gap from last record to evalEnd
                    $lastLogAt = Carbon::parse($records->last()->entry_at, 'UTC')->setTimezone('Africa/Addis_Ababa');
                    $tailGap = (int) $lastLogAt->diffInMinutes($evalEnd);
                    if ($tailGap >= self::INACTIVITY_THRESHOLD_MINUTES) {
                        $branchShiftIncidents[] = [
                            'gap_type' => 'TRAILING_INACTIVITY',
                            'from' => $lastLogAt,
                            'to' => $evalEnd,
                            'duration_minutes' => $tailGap,
                            'is_ongoing' => ($evalEnd == $now && $now->lt($shiftEnd)),
                        ];
                    }
                }

                if (count($branchShiftIncidents) > 0) {
                    $nonCompliantShifts++;
                    foreach ($branchShiftIncidents as $inc) {
                        $totalInactiveMinutes += $inc['duration_minutes'];
                        $incidents[] = [
                            'incident_id' => 'INC-' . strtoupper(substr(md5($branch->id . $shift->id . $inc['from']->timestamp), 0, 8)),
                            'branch_id' => $branch->id,
                            'branch_name' => $branch->name,
                            'branch_name_am' => $branch->name_am ?? $branch->name,
                            'branch_code' => $branch->code,
                            'shift_id' => $shift->id,
                            'shift_name' => $shift->name,
                            'shift_name_am' => $shift->name_am ?? $shift->name,
                            'shift_window' => "{$shift->start_time} – {$shift->end_time}",
                            'shift_window_ethiopian' => "{$shift->ethiopian_start_time} – {$shift->ethiopian_end_time}",
                            'shift_group_name' => $activeGroupSched?->group?->name ?? 'Duty Group',
                            'attendants' => $attendantsList,
                            'attendants_count' => count($attendantsList),
                            'gap_type' => $inc['gap_type'],
                            'from_time' => $inc['from']->format('H:i'),
                            'to_time' => $inc['to']->format('H:i'),
                            'interval_formatted' => "{$inc['from']->format('H:i')} – {$inc['to']->format('H:i')}",
                            'interval_ethiopian' => "{$this->formatEthiopianTime($inc['from'])} – {$this->formatEthiopianTime($inc['to'])}",
                            'duration_minutes' => $inc['duration_minutes'],
                            'duration_human' => $this->formatDurationHuman($inc['duration_minutes']),
                            'is_ongoing' => $inc['is_ongoing'],
                            'status' => $inc['is_ongoing'] ? 'ACTIVE_NON_WORKING' : 'PAST_UNATTENDED_GAP',
                            'severity' => ($inc['duration_minutes'] >= 60) ? 'CRITICAL' : 'WARNING',
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
                'total_inactive_minutes' => $totalInactiveMinutes,
                'total_inactive_hours' => round($totalInactiveMinutes / 60, 1),
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
}
