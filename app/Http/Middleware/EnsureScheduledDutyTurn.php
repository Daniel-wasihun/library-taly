<?php

namespace App\Http\Middleware;

use App\Services\DutyScheduleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureScheduledDutyTurn
{
    public function __construct(
        protected DutyScheduleService $dutyService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $taskKey
     */
    public function handle(Request $request, Closure $next, string $taskKey = 'book_circulation'): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $check = $this->dutyService->canPerformTask($user, $taskKey);

        if (!$check['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $check['reason'],
                'code' => $check['code'] ?? 'OUTSIDE_DUTY_TURN',
                'duty_gate' => [
                    'task' => $check['task'],
                    'task_name' => $check['task_name'],
                    'is_on_duty_today' => false,
                    'next_turn' => $check['next_turn'] ?? 'No upcoming shifts scheduled',
                ],
            ], 403);
        }

        return $next($request);
    }
}
