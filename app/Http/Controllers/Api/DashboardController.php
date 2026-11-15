<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookLoan;
use App\Models\DutySchedule;
use App\Models\User;
use App\Services\DutyScheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __construct(
        protected DutyScheduleService $dutyService
    ) {}

    /**
     * Get aggregate library statistics and user duty status.
     */
    public function stats(Request $request): JsonResponse
    {
        $today = Carbon::today()->toDateString();
        $user = $request->user();

        // Cached overall library figures
        $stats = Cache::remember('dashboard_stats', 60, function () use ($today) {
            $totalBooks = Book::count();
            $totalCopies = Book::sum('total_copies');
            $availableCopies = Book::sum('available_copies');
            $activeLoans = BookLoan::where('status', 'borrowed')->count();
            $overdueLoans = BookLoan::where('status', 'borrowed')
                ->where('due_date', '<', Carbon::now())
                ->count();
            $totalMembers = User::role('Student Member')->count();

            // Staff on duty today
            $onDutyStaff = DutySchedule::where('shift_date', $today)
                ->whereIn('status', ['scheduled', 'active'])
                ->with('user:id,name,username,email')
                ->get()
                ->map(fn ($shift) => [
                    'user_id' => $shift->user_id,
                    'user_name' => $shift->user->name,
                    'user_username' => $shift->user->username,
                    'task_key' => $shift->task_key,
                    'task_name' => DutyScheduleService::TASKS[$shift->task_key] ?? $shift->task_key,
                    'start_time' => $shift->start_time,
                    'end_time' => $shift->end_time,
                ]);

            return [
                'total_books' => $totalBooks,
                'total_copies' => (int) $totalCopies,
                'available_copies' => (int) $availableCopies,
                'active_loans' => $activeLoans,
                'overdue_loans' => $overdueLoans,
                'total_members' => $totalMembers,
                'on_duty_staff_today' => $onDutyStaff,
            ];
        });

        // User specific duty check (live, with user-level cache)
        $circulationDuty = $this->dutyService->canPerformTask($user, 'book_circulation', $today);

        $recentLoans = BookLoan::with(['book:id,title,author', 'member:id,name,username'])
            ->latest('issued_date')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'stats' => $stats,
            'recent_loans' => $recentLoans,
            'current_user_duty' => [
                'is_admin' => $user->hasAdminPrivilege(),
                'circulation_authorized' => $circulationDuty['allowed'],
                'reason' => $circulationDuty['reason'],
                'next_turn' => $circulationDuty['next_turn'] ?? null,
                'task_name' => $circulationDuty['task_name'] ?? 'Book Circulation & Issue',
            ],
        ]);
    }
}
