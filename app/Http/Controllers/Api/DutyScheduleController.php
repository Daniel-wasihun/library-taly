<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DutySchedule;
use App\Models\User;
use App\Services\DutyScheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DutyScheduleController extends Controller
{
    public function __construct(
        protected DutyScheduleService $dutyService
    ) {}

    /**
     * List all duty schedules.
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->query('user_id');
        $date = $request->query('date');
        $task = $request->query('task_key');

        $query = DutySchedule::with('user:id,name,username,email')->orderBy('shift_date', 'desc');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        if ($date) {
            $query->where('shift_date', $date);
        }

        if ($task) {
            $query->where('task_key', $task);
        }

        $schedules = $query->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $schedules->items(),
            'pagination' => [
                'current_page' => $schedules->currentPage(),
                'last_page' => $schedules->lastPage(),
                'total' => $schedules->total(),
            ],
            'available_tasks' => $this->dutyService->getAvailableTasks(),
        ]);
    }

    /**
     * Get the logged-in user's upcoming shifts and duty turns.
     */
    public function myTurns(Request $request): JsonResponse
    {
        $user = $request->user();
        $today = Carbon::today()->toDateString();

        $shifts = DutySchedule::where('user_id', $user->id)
            ->where('shift_date', '>=', $today)
            ->orderBy('shift_date', 'asc')
            ->get();

        $todayTurns = DutySchedule::where('user_id', $user->id)
            ->where('shift_date', $today)
            ->get();

        return response()->json([
            'success' => true,
            'is_admin' => $user->hasAdminPrivilege(),
            'today_turns' => $todayTurns,
            'upcoming_turns' => $shifts,
            'available_tasks' => $this->dutyService->getAvailableTasks(),
        ]);
    }

    /**
     * Assign a new duty shift to a librarian / staff member.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'task_key' => ['required', 'string', 'in:' . implode(',', array_keys(DutyScheduleService::TASKS))],
            'shift_date' => ['required', 'date'],
            'start_time' => ['nullable', 'date_format:H:i', 'date_format:H:i:s'],
            'end_time' => ['nullable', 'date_format:H:i', 'date_format:H:i:s'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $schedule = DutySchedule::create([
            'user_id' => $validated['user_id'],
            'task_key' => $validated['task_key'],
            'shift_date' => $validated['shift_date'],
            'start_time' => $validated['start_time'] ?? '08:00:00',
            'end_time' => $validated['end_time'] ?? '18:00:00',
            'status' => 'scheduled',
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->dutyService->clearUserScheduleCache($schedule->user_id);

        return response()->json([
            'success' => true,
            'message' => 'Duty shift successfully scheduled.',
            'schedule' => $schedule->load('user:id,name,username,email'),
        ], 201);
    }

    /**
     * Delete/Cancel a duty shift.
     */
    public function destroy(DutySchedule $schedule): JsonResponse
    {
        $userId = $schedule->user_id;
        $schedule->delete();

        $this->dutyService->clearUserScheduleCache($userId);

        return response()->json([
            'success' => true,
            'message' => 'Duty shift cancelled.',
        ]);
    }

    /**
     * Test / verify duty access check for a specific task.
     */
    public function checkDuty(Request $request): JsonResponse
    {
        $task = $request->query('task_key', 'attendance_desk');
        $date = $request->query('date', Carbon::today()->toDateString());
        $user = $request->user();
        $libraryId = $request->filled('library_id')
            ? (int) $request->query('library_id')
            : ($user->library_id ? (int) $user->library_id : null);

        $result = $this->dutyService->canPerformTask($user, $task, $date, $libraryId, true);

        return response()->json([
            'success' => true,
            'duty_check' => $result,
        ]);
    }
}
