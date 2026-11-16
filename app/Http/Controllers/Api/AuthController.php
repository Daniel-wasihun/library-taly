<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DutySchedule;
use App\Models\User;
use App\Services\DutyScheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected DutyScheduleService $dutyService
    ) {}

    /**
     * Authenticate user by email or unique username.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $loginField = filter_var($validated['login'], FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($loginField, $validated['login'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Invalid credentials provided.'],
            ]);
        }

        if ($user->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Your account is deactivated. Please contact the administrator.',
            ], 403);
        }

        // Revoke previous tokens or generate a clean new one
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => $this->formatUserData($user),
        ]);
    }

    /**
     * Get current authenticated user profile and active duty shifts.
     */
    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'user' => $this->formatUserData($request->user()),
        ]);
    }

    /**
     * Revoke authenticated user token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
        ]);
    }

    /**
     * Format user payload with roles, permissions, and duty status for today.
     */
    protected function formatUserData(User $user): array
    {
        $today = Carbon::today()->toDateString();
        
        // Load roles and permissions
        $roles = $user->getRoleNames();
        $permissions = $user->getAllPermissions()->pluck('name');

        // Check user's duty shifts for today
        $todayShifts = DutySchedule::where('user_id', $user->id)
            ->where('shift_date', $today)
            ->whereIn('status', ['scheduled', 'active'])
            ->get();

        // Check if user is on duty for primary library attendance desk task
        $deskDutyCheck = $this->dutyService->canPerformTask($user, 'attendance_desk', $today, $user->library_id);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status,
            'library_id' => $user->library_id,
            'library' => $user->library ? [
                'id' => $user->library->id,
                'name' => $user->library->name,
                'name_am' => $user->library->name_am,
                'code' => $user->library->code,
            ] : null,
            'can_manage_all_libraries' => $user->canManageAllLibraries(),
            'roles' => $roles,
            'permissions' => $permissions,
            'is_admin' => $user->hasAdminPrivilege(),
            'duty_status' => [
                'has_active_shift_today' => $user->hasAdminPrivilege() || $todayShifts->isNotEmpty(),
                'circulation_authorized_today' => $deskDutyCheck['allowed'],
                'circulation_reason' => $deskDutyCheck['reason'] ?? '',
                'next_turn' => $deskDutyCheck['next_turn'] ?? null,
                'today_shifts' => $todayShifts->map(fn ($shift) => [
                    'id' => $shift->id,
                    'task_key' => $shift->task_key,
                    'task_name' => DutyScheduleService::TASKS[$shift->task_key] ?? $shift->task_key,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                ]),
            ],
        ];
    }
}
