<?php

namespace App\Services;

use App\Models\DutySchedule;
use App\Models\GroupShiftSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DutyScheduleService
{
    /**
     * Map internal task keys to readable titles.
     */
    public const TASKS = [
        'attendance_desk' => 'Physical Attendance & Desk Logging',
        'facility_audit' => 'Facility Inventory & Stack Audit',
        'archive_access' => 'Restricted Rare Archive Access',
        'roster_management' => 'Duty Shift Roster Management',
    ];

    /**
     * Validate whether a user is authorized and scheduled to perform a specific task.
     *
     * @param User $user
     * @param string $taskKey
     * @param string|null $date
     * @param int|null $libraryId
     * @param bool $checkTimeWindow
     * @param Carbon|null $currentTime
     * @return array{allowed: bool, reason: string, schedule?: DutySchedule|null, next_turn?: string|null}
     */
    public function canPerformTask(
        User $user,
        string $taskKey,
        ?string $date = null,
        ?int $libraryId = null,
        bool $checkTimeWindow = true,
        ?Carbon $currentTime = null
    ): array {
        $now = ($currentTime ?? Carbon::now())->setTimezone('Africa/Addis_Ababa');
        $targetDate = $date ?? $now->toDateString();
        $taskName = self::TASKS[$taskKey] ?? $taskKey;

        // 1. Super Admin and Head Librarian possess administrative master override
        if ($user->hasAdminPrivilege()) {
            return [
                'allowed' => true,
                'reason' => 'Administrative override granted.',
                'task' => $taskKey,
                'task_name' => $taskName,
                'schedule' => null,
            ];
        }

        // 2. Branch scope check: if target branch specified and user is scoped to a different branch
        if ($libraryId && !$user->canManageAllLibraries() && (int) $user->library_id !== (int) $libraryId) {
            $userBranchName = $user->library?->name ?? 'another branch';
            return [
                'allowed' => false,
                'reason' => "Branch restricted: You are assigned to {$userBranchName} and cannot perform '{$taskName}' for another branch.",
                'code' => 'BRANCH_RESTRICTED',
                'task' => $taskKey,
                'task_name' => $taskName,
                'schedule' => null,
            ];
        }

        $userBranchName = $user->library?->name ?? 'your assigned branch';
        $isLiveDate = ($targetDate === $now->toDateString());
        $evalTime = $isLiveDate && $checkTimeWindow;
        $timeStr = $now->format('H:i');
        $timeBucket = $evalTime ? $now->format('Ymd_H_') . intdiv($now->minute, 2) : 'date';

        // 3. Cache query for high-performance duty turn verification (30s window when live)
        $cacheKey = "duty_turn_{$user->id}_{$taskKey}_{$targetDate}_{$timeBucket}_" . ($libraryId ?? 'any');

        return Cache::remember($cacheKey, 30, function () use ($user, $taskKey, $targetDate, $taskName, $libraryId, $evalTime, $now, $timeStr, $userBranchName) {
            $query = DutySchedule::where('user_id', $user->id)
                ->where('task_key', $taskKey)
                ->where('shift_date', $targetDate)
                ->whereIn('status', ['scheduled', 'active']);

            if ($libraryId && !$user->canManageAllLibraries()) {
                $query->where(function ($q) use ($libraryId) {
                    $q->whereNull('library_id')->orWhere('library_id', $libraryId);
                });
            }

            $schedule = $query->first();

            if ($schedule) {
                if ($evalTime && $schedule->start_time && $schedule->end_time) {
                    $inWindow = $this->isWithinTimeWindow($schedule->start_time, $schedule->end_time, $timeStr);
                    if (!$inWindow) {
                        return [
                            'allowed' => false,
                            'reason' => "You have a scheduled duty turn today ({$schedule->start_time} - {$schedule->end_time}), but the current time ({$timeStr}) is outside your shift window.",
                            'code' => 'OUTSIDE_SHIFT_HOURS',
                            'task' => $taskKey,
                            'task_name' => $taskName,
                            'schedule' => $schedule,
                        ];
                    }
                }

                return [
                    'allowed' => true,
                    'reason' => "Active duty turn verified for {$taskName} on {$targetDate}.",
                    'task' => $taskKey,
                    'task_name' => $taskName,
                    'schedule' => $schedule,
                ];
            }

            // 4. Check Ethiopian Calendar Monthly Group Shift Schedule
            $targetCarbon = Carbon::parse($targetDate);
            [$eyear, $emonth, $eday] = EthiopianCalendarService::gregorianToEthiopian(
                (int)$targetCarbon->year,
                (int)$targetCarbon->month,
                (int)$targetCarbon->day
            );

            $userGroupIds = $user->shiftGroups()->pluck('shift_groups.id');
            if ($userGroupIds->isNotEmpty()) {
                $groupSchedQuery = GroupShiftSchedule::with(['shift', 'group'])
                    ->whereIn('shift_group_id', $userGroupIds)
                    ->where('ethiopian_year', $eyear)
                    ->where('ethiopian_month', $emonth)
                    ->where('status', 'active');

                if ($libraryId && !$user->canManageAllLibraries()) {
                    $groupSchedQuery->where(function ($q) use ($libraryId) {
                        $q->whereNull('library_id')->orWhere('library_id', $libraryId);
                    });
                }

                $schedulesThisMonth = $groupSchedQuery->get();

                // Find schedule that contains today's day
                $activeGroupSched = $schedulesThisMonth->first(function ($sched) use ($eday) {
                    $days = is_array($sched->days) ? $sched->days : [];
                    return in_array((int)$eday, array_map('intval', $days), true);
                });

                // Check overnight shift extending from yesterday into early dawn (< 05:00)
                if (!$activeGroupSched && $evalTime && $timeStr <= '05:00') {
                    $yesterday = (clone $now)->subDay();
                    [$yYear, $yMonth, $yDay] = EthiopianCalendarService::gregorianToEthiopian(
                        (int)$yesterday->year,
                        (int)$yesterday->month,
                        (int)$yesterday->day
                    );

                    $yGroupSchedQuery = GroupShiftSchedule::with(['shift', 'group'])
                        ->whereIn('shift_group_id', $userGroupIds)
                        ->where('ethiopian_year', $yYear)
                        ->where('ethiopian_month', $yMonth)
                        ->where('status', 'active');

                    if ($libraryId && !$user->canManageAllLibraries()) {
                        $yGroupSchedQuery->where(function ($q) use ($libraryId) {
                            $q->whereNull('library_id')->orWhere('library_id', $libraryId);
                        });
                    }

                    $ySched = $yGroupSchedQuery->get()->first(function ($sched) use ($yDay) {
                        $days = is_array($sched->days) ? $sched->days : [];
                        return in_array((int)$yDay, array_map('intval', $days), true)
                            && $sched->shift
                            && $sched->shift->start_time > $sched->shift->end_time;
                    });

                    if ($ySched && $timeStr <= ($ySched->shift->end_time ?? '05:00')) {
                        $shiftName = $ySched->shift->name;
                        $groupName = $ySched->group?->name ?? 'Duty Group';
                        $timeInfo = $ySched->shift->ethiopian_start_time . ' - ' . $ySched->shift->ethiopian_end_time;

                        return [
                            'allowed' => true,
                            'reason' => "Active overnight duty turn verified ({$groupName} – {$shiftName} [{$timeInfo}]) extending into dawn on Ethiopian date {$eday}/{$emonth}/{$eyear} E.C.",
                            'task' => $taskKey,
                            'task_name' => $taskName,
                            'group_schedule' => $ySched,
                            'shift' => $ySched->shift,
                        ];
                    }
                }

                if ($activeGroupSched) {
                    $shift = $activeGroupSched->shift;
                    $shiftName = $shift ? $shift->name : 'Scheduled Shift';
                    $groupName = $activeGroupSched->group ? $activeGroupSched->group->name : 'Duty Group';
                    $timeInfo = $shift ? ($shift->ethiopian_start_time . ' - ' . $shift->ethiopian_end_time) : '';

                    if ($evalTime && $shift) {
                        $inWindow = $this->isWithinShiftWindow($shift, $timeStr);
                        if (!$inWindow) {
                            return [
                                'allowed' => false,
                                'reason' => "You have a scheduled {$shiftName} today for {$userBranchName} ({$timeInfo} / {$shift->start_time} - {$shift->end_time}), but your shift is inactive right now at {$timeStr}. The usage counter is only accessible during your active shift hours.",
                                'code' => 'OUTSIDE_SHIFT_HOURS',
                                'task' => $taskKey,
                                'task_name' => $taskName,
                                'shift' => $shift,
                                'group_schedule' => $activeGroupSched,
                            ];
                        }
                    }

                    return [
                        'allowed' => true,
                        'reason' => "Active group duty turn verified ({$groupName} – {$shiftName} [{$timeInfo}]) on Ethiopian date {$eday}/{$emonth}/{$eyear} E.C.",
                        'task' => $taskKey,
                        'task_name' => $taskName,
                        'group_schedule' => $activeGroupSched,
                        'shift' => $shift,
                    ];
                }
            }

            // User does not have a duty shift today: find upcoming turn
            $nextTurn = DutySchedule::where('user_id', $user->id)
                ->where('task_key', $taskKey)
                ->where('shift_date', '>', $targetDate)
                ->whereIn('status', ['scheduled', 'active'])
                ->orderBy('shift_date', 'asc')
                ->first();

            return [
                'allowed' => false,
                'reason' => "You are logged in, but you can only perform '{$taskName}' during your assigned duty schedule. You have no active shift scheduled for today ({$targetDate}) at {$userBranchName}.",
                'code' => 'OUTSIDE_DUTY_TURN',
                'task' => $taskKey,
                'task_name' => $taskName,
                'next_turn' => $nextTurn ? $nextTurn->shift_date->format('Y-m-d') : null,
                'schedule' => null,
            ];
        });
    }

    /**
     * Check whether current HH:mm is inside shift start_time and end_time.
     */
    public function isWithinShiftWindow(\App\Models\Shift $shift, string $currentTime): bool
    {
        $startTime = $shift->start_time; // e.g. "08:00" or "17:00"
        $endTime = $shift->end_time;     // e.g. "17:00" or "05:00"

        return $this->isWithinTimeWindow($startTime, $endTime, $currentTime);
    }

    /**
     * Compare time string against start and end window.
     */
    public function isWithinTimeWindow(string $startTime, string $endTime, string $currentTime): bool
    {
        // Daytime window (e.g. 08:00 to 17:00)
        if ($startTime <= $endTime) {
            return $currentTime >= $startTime && $currentTime <= $endTime;
        }

        // Overnight window (e.g. 17:00 to 05:00 next day)
        return $currentTime >= $startTime || $currentTime <= $endTime;
    }

    /**
     * Invalidate schedule cache for a specific user.
     */
    public function clearUserScheduleCache(int $userId): void
    {
        $today = Carbon::today()->toDateString();
        foreach (array_keys(self::TASKS) as $taskKey) {
            Cache::forget("duty_turn_{$userId}_{$taskKey}_{$today}");
        }
        Cache::forget("dashboard_stats");
        Cache::forget("on_duty_librarians_today");
    }

    /**
     * Evaluate duty status summary for a user.
     *
     * @param User $user
     * @param string $taskKey
     * @return array
     */
    public function evaluateUserDutyStatus(User $user, string $taskKey = 'book_circulation'): array
    {
        $evaluation = $this->canPerformTask($user, $taskKey);

        $today = Carbon::today()->toDateString();
        $todayShifts = DutySchedule::where('user_id', $user->id)
            ->where('shift_date', $today)
            ->whereIn('status', ['scheduled', 'active'])
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'task_key' => $s->task_key,
                'task_name' => self::TASKS[$s->task_key] ?? $s->task_key,
                'start_time' => $s->start_time,
                'end_time' => $s->end_time,
            ]);

        return [
            'has_active_shift_today' => $evaluation['allowed'] && !$user->hasAdminPrivilege(),
            'circulation_authorized_today' => $evaluation['allowed'],
            'circulation_reason' => $evaluation['reason'] ?? '',
            'next_turn' => $evaluation['next_turn'] ?? null,
            'today_shifts' => $todayShifts,
        ];
    }

    /**
     * Get list of supported tasks.
     */
    public function getAvailableTasks(): array
    {
        return self::TASKS;
    }
}
