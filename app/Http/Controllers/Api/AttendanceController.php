<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibraryAttendance;
use App\Models\Library;
use App\Models\User;
use App\Services\AttendanceReportService;
use App\Services\EthiopianDateService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(
        protected AttendanceReportService $reportService
    ) {}
    /**
     * List recent attendance entries with filtering and branch isolation.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || (!$user->hasPermissionTo('attendance.view') && !$user->hasPermissionTo('attendance.record'))) {
            return response()->json(['message' => 'Unauthorized: Permission required to view attendance.'], 403);
        }

        $query = LibraryAttendance::query()->with(['library', 'staff']);

        // Enforce branch-level isolation for users who cannot manage all branches
        if (!$user->canManageAllLibraries()) {
            $userBranchId = $user->library_id;
            if (!$userBranchId) {
                return response()->json([
                    'data' => [],
                    'total' => 0,
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $request->integer('per_page', 25),
                ]);
            }
            $query->where('library_id', $userBranchId);
        } elseif ($request->filled('library_id')) {
            $query->where('library_id', $request->library_id);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_at', '<=', $request->date_to);
        }

        $perPage = $request->integer('per_page', 25);
        $attendances = $query->orderBy('entry_at', 'desc')->paginate($perPage);

        return response()->json($attendances);
    }

    /**
     * Log a physical library attendance count (+1 or batch).
     */
    public function logEntry(Request $request): JsonResponse
    {
        $user = $request->user();

        // Security check: Only attendants and authorized staff can record live attendance
        $isPrivilegedStaff = $user->hasAdminPrivilege() || $user->hasRole('Manager') || $user->can('libraries.manage');
        if (!$user->hasPermissionTo('attendance.record') && !$isPrivilegedStaff) {
            return response()->json([
                'message' => 'Unauthorized: Only attendants and authorized staff can record live attendance.',
            ], 403);
        }

        $validated = $request->validate([
            'library_id' => ['required', 'exists:libraries,id'],
            'user_type' => ['required', 'in:student,teacher,staff'],
            'gender' => ['required', 'in:male,female'],
            'count' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $libraryId = (int) $validated['library_id'];

        // Security check: Ensure staff logs into their own branch unless global admin or management
        if (!$user->canManageAllLibraries() && !$isPrivilegedStaff) {
            if (!$user->library_id || (int) $user->library_id !== $libraryId) {
                return response()->json([
                    'message' => 'Unauthorized: You can only record attendance for your assigned library branch.',
                ], 403);
            }
        }

        // Security check: Verify active shift date and time window
        $dutyService = app(\App\Services\DutyScheduleService::class);
        $dutyCheck = $dutyService->canPerformTask($user, 'attendance_desk', null, $libraryId, true);
        if (!$dutyCheck['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $dutyCheck['reason'],
                'code' => $dutyCheck['code'] ?? 'OUTSIDE_DUTY_TURN',
                'duty_gate' => $dutyCheck,
            ], 403);
        }

        $count = (int) ($validated['count'] ?? 1);

        $attendance = DB::transaction(function () use ($libraryId, $validated, $count, $user) {
            return LibraryAttendance::create([
                'library_id' => $libraryId,
                'user_type' => $validated['user_type'],
                'gender' => $validated['gender'],
                'visitor_count' => $count,
                'staff_id' => $user->id,
                'entry_at' => now(),
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => "Logged {$count} {$validated['gender']} {$validated['user_type']}(s) successfully.",
            'data' => $attendance->load(['library', 'staff']),
            'count' => $count,
        ], 201);
    }

    /**
     * Attendance statistics and distributions (Total, Male/Female, User Types, Trends).
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        // Allow users with reporting permission, or desk attendants/librarians with attendance permissions
        if (!$user->canManageReportingAndAnalytics() && !$user->hasPermissionTo('attendance.view') && !$user->hasPermissionTo('attendance.record')) {
            return response()->json([
                'message' => 'Unauthorized: Attendance viewing or reporting permission required to view statistics.',
            ], 403);
        }

        $restrictedLibraryId = null;
        if (!$user->canManageAllLibraries()) {
            $restrictedLibraryId = $user->library_id;
            if (!$restrictedLibraryId) {
                return response()->json([
                    'total_users' => 0,
                    'total_male' => 0,
                    'total_female' => 0,
                    'gender_distribution' => [],
                    'type_distribution' => [],
                    'library_distribution' => [],
                    'trends' => [],
                ]);
            }
        }

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->startOfDay();

        $dateTo = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        $baseQuery = LibraryAttendance::whereBetween('entry_at', [$dateFrom, $dateTo])
            ->when($restrictedLibraryId, fn($q) => $q->where('library_id', $restrictedLibraryId))
            ->when($request->filled('library_id') && $user->canManageAllLibraries(), fn($q) => $q->where('library_id', $request->library_id));

        $totalCount = (int) (clone $baseQuery)->sum('visitor_count');

        $genderBreakdown = (clone $baseQuery)
            ->select('gender', DB::raw('SUM(visitor_count) as count'))
            ->groupBy('gender')
            ->get();

        $totalMale = (int) $genderBreakdown->where('gender', 'male')->sum('count');
        $totalFemale = (int) $genderBreakdown->where('gender', 'female')->sum('count');

        $typeBreakdown = (clone $baseQuery)
            ->select('user_type', DB::raw('SUM(visitor_count) as count'))
            ->groupBy('user_type')
            ->get();

        $libraryDistribution = (clone $baseQuery)
            ->select('library_id', 'user_type', 'gender', DB::raw('SUM(visitor_count) as count'))
            ->groupBy('library_id', 'user_type', 'gender')
            ->with('library:id,name,name_am,code')
            ->get();

        $periodicTrends = (clone $baseQuery)
            ->select(DB::raw('DATE(entry_at) as date'), DB::raw('SUM(visitor_count) as count'))
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Compile multi-perspective analytical dimensions
        $reportData = $this->reportService->compileReportData($user, $request->all(), $request->query('lang', 'en'));

        return response()->json([
            'total_users' => $totalCount,
            'total_male' => $totalMale,
            'total_female' => $totalFemale,
            'gender_distribution' => $genderBreakdown,
            'type_distribution' => $typeBreakdown,
            'library_distribution' => $libraryDistribution,
            'trends' => $periodicTrends,
            'period' => [
                'from' => $dateFrom->toDateString(),
                'to' => $dateTo->toDateString(),
            ],
            // Multidimensional Analytical Dimensions
            'kpis' => [
                'total' => $reportData['totalCount'],
                'male' => $reportData['maleCount'],
                'female' => $reportData['femaleCount'],
                'student' => $reportData['demographics']['student']['total'] ?? 0,
                'teacher' => $reportData['demographics']['teacher']['total'] ?? 0,
                'staff' => $reportData['demographics']['staff']['total'] ?? 0,
                'male_percent' => $reportData['malePercent'],
                'female_percent' => $reportData['femalePercent'],
                'student_percent' => $reportData['demographics']['student']['percent'] ?? 0.0,
                'teacher_percent' => $reportData['demographics']['teacher']['percent'] ?? 0.0,
                'staff_percent' => $reportData['demographics']['staff']['percent'] ?? 0.0,
                'peak_hour' => $reportData['peakHourLabel'],
                'peak_hour_count' => $reportData['peakHourCount'],
                'busiest_branch' => $reportData['busiestBranchName'],
                'dominant_patron' => $reportData['dominantPatronLabel'],
            ],
            'demographics' => $reportData['demographics'],
            'branch_comparison' => $reportData['branchComparison'],
            'shift_windows' => $reportData['shiftWindows'],
            'staff_activity' => $reportData['staffActivity'],
            'hourly_distribution' => $reportData['hourlyDistribution'],
            'day_of_week_analytics' => $reportData['dayOfWeekAnalytics'],
            'intra_month_analytics' => $reportData['intraMonthDayAnalytics'],
            'monthly_analytics' => $reportData['monthlyAnalytics'],
            'yearly_analytics' => $reportData['yearlyAnalytics'],
            'shift_time_analytics' => $reportData['shiftTimeAnalytics'],
            'description' => $reportData['description'],
        ]);
    }

    /**
     * Export attendance data as CSV or specified formal document format.
     */
    public function export(Request $request): Response|StreamedResponse|JsonResponse
    {
        $user = $request->user();

        // Authorized for reporting managers, librarians, or attendants
        if (!$user->canManageReportingAndAnalytics() && !$user->hasPermissionTo('attendance.view') && !$user->hasPermissionTo('attendance.record')) {
            return response()->json([
                'message' => 'Unauthorized: Library attendance viewing or reporting permission is required to export reports.',
            ], 403);
        }

        $format = $request->query('format');
        if ($format && in_array(strtolower($format), ['xlsx', 'excel', 'doc', 'word', 'pdf'])) {
            return $this->downloadReport($request);
        }

        $isAmharic = $request->query('lang') === 'am';
        $query = LibraryAttendance::with(['library', 'staff']);

        if (!$user->canManageAllLibraries()) {
            $query->where('library_id', $user->library_id);
        } elseif ($request->filled('library_id')) {
            $query->where('library_id', $request->library_id);
        }

        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->gender);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('entry_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('entry_at', '<=', $request->date_to);
        }

        $attendances = $query->orderBy('entry_at', 'desc')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="attendance_report_' . now()->format('Y_m_d_His') . '.csv"',
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return new StreamedResponse(function () use ($attendances, $isAmharic) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

            if ($isAmharic) {
                fputcsv($file, ['የመዝገብ መለያ', 'የቤተ-መጻሕፍት ቅርንጫፍ', 'የተጠቃሚ ዓይነት', 'ጾታ', 'የተጠቃሚ ብዛት', 'የተመዘገበበት ቀንና ሰዓት', 'መዝጋቢ ሠራተኛ']);
            } else {
                fputcsv($file, ['Log ID', 'Library Branch', 'Patron Type', 'Gender', 'Visitor Count', 'Recorded At', 'Staff Member']);
            }

            foreach ($attendances as $log) {
                $dt = EthiopianDateService::formatDT($log->entry_at, $isAmharic);
                $branchName = $isAmharic ? ($log->library?->name_am ?? $log->library?->name ?? 'ጠቅላላ') : ($log->library?->name ?? 'General');
                
                $userType = $log->user_type;
                if ($isAmharic) {
                    $userType = match ($userType) {
                        'student' => 'ተማሪ',
                        'teacher' => 'መምህር',
                        'staff' => 'ሰራተኛ',
                        default => $userType,
                    };
                } else {
                    $userType = ucfirst($userType);
                }

                $gender = $log->gender;
                if ($isAmharic) {
                    $gender = match ($gender) {
                        'male' => 'ወንድ',
                        'female' => 'ሴት',
                        default => $gender,
                    };
                } else {
                    $gender = ucfirst($gender);
                }

                fputcsv($file, [
                    $log->slug,
                    $branchName,
                    $userType,
                    $gender,
                    $log->visitor_count,
                    $dt['full'],
                    $log->staff?->name ?? ($isAmharic ? 'የስርዓት መዝጋቢ' : 'System'),
                ]);
            }
            fclose($file);
        }, 200, $headers);
    }

    /**
     * Update visitor count for an attendance entry.
     */
    public function updateCount(Request $request, string $slug): JsonResponse
    {
        $attendance = LibraryAttendance::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        if (!$user || (!$user->isSuperAdmin() && !$user->hasAdminPrivilege() && !$user->hasPermissionTo('attendance.update') && !$user->hasPermissionTo('attendance.record'))) {
            return response()->json(['message' => 'Unauthorized: Permission to update attendance record required.'], 403);
        }

        if (!$user->canManageAllLibraries() && (int) $attendance->library_id !== (int) $user->library_id) {
            return response()->json(['message' => 'Unauthorized to update attendance for this branch.'], 403);
        }

        // Security check: Verify active shift date and time window
        if (!$user->hasAdminPrivilege()) {
            $dutyService = app(\App\Services\DutyScheduleService::class);
            $dutyCheck = $dutyService->canPerformTask($user, 'attendance_desk', null, $attendance->library_id, true);
            if (!$dutyCheck['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $dutyCheck['reason'],
                    'code' => $dutyCheck['code'] ?? 'OUTSIDE_DUTY_TURN',
                ], 403);
            }
        }

        // Strict 2-minute rule: NO ONE (including admin and superadmin) can change numbers after 2 minutes of logging
        $entryTime = Carbon::parse($attendance->entry_at)->setTimezone('Africa/Addis_Ababa');
        $now = Carbon::now('Africa/Addis_Ababa');
        $diffSeconds = $entryTime->diffInSeconds($now, false);
        if ($diffSeconds > 120 || $diffSeconds < -60) {
            return response()->json([
                'success' => false,
                'message' => 'Operational regulation: attendance counter entries cannot be adjusted or modified after 2 minutes of recording. This lock applies strictly to all users including administrators.',
                'code' => 'ATTENDANCE_EDIT_WINDOW_EXPIRED',
            ], 403);
        }

        $validated = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        DB::transaction(function () use ($attendance, $validated) {
            $attendance->update(['visitor_count' => $validated['count']]);
        });

        return response()->json([
            'success' => true,
            'data' => $attendance->load(['library', 'staff']),
        ]);
    }

    /**
     * Delete an entry within the 2-minute safety window.
     */
    public function destroy(Request $request, string $slug): JsonResponse
    {
        $attendance = LibraryAttendance::where('slug', $slug)->firstOrFail();
        $user = $request->user();

        if (!$user || (!$user->isSuperAdmin() && !$user->hasAdminPrivilege() && !$user->hasPermissionTo('attendance.delete') && !$user->hasPermissionTo('attendance.record'))) {
            return response()->json(['message' => 'Unauthorized: Permission to delete attendance record required.'], 403);
        }

        if (!$user->canManageAllLibraries() && (int) $attendance->library_id !== (int) $user->library_id) {
            return response()->json(['message' => 'Unauthorized to delete attendance for this branch.'], 403);
        }

        // Security check: Verify active shift date and time window
        if (!$user->hasAdminPrivilege()) {
            $dutyService = app(\App\Services\DutyScheduleService::class);
            $dutyCheck = $dutyService->canPerformTask($user, 'attendance_desk', null, $attendance->library_id, true);
            if (!$dutyCheck['allowed']) {
                return response()->json([
                    'success' => false,
                    'message' => $dutyCheck['reason'],
                    'code' => $dutyCheck['code'] ?? 'OUTSIDE_DUTY_TURN',
                ], 403);
            }
        }

        // Strict 2-minute rule: NO ONE (including admin and superadmin) can delete entries after 2 minutes of logging
        $entryTime = Carbon::parse($attendance->entry_at)->setTimezone('Africa/Addis_Ababa');
        $now = Carbon::now('Africa/Addis_Ababa');
        $diffSeconds = $entryTime->diffInSeconds($now, false);
        if ($diffSeconds > 120 || $diffSeconds < -60) {
            return response()->json([
                'success' => false,
                'message' => 'Operational regulation: attendance counter entries cannot be deleted after 2 minutes of recording. This lock applies strictly to all users including administrators.',
                'code' => 'ATTENDANCE_EDIT_WINDOW_EXPIRED',
            ], 403);
        }

        DB::transaction(function () use ($attendance) {
            $attendance->delete();
        });

        return response()->json(['success' => true, 'message' => 'Attendance log deleted.']);
    }

    /**
     * Generate an official, print-ready HTML attendance report from backend.
     */
    public function printReport(Request $request): Response
    {
        $user = $this->resolveReportUser($request);

        if (!$user->canManageReportingAndAnalytics() && !$user->hasPermissionTo('attendance.view') && !$user->hasPermissionTo('attendance.record')) {
            abort(403, 'Unauthorized: Library management or attendance viewing permission is required to print reports.');
        }

        $lang = $request->query('lang', 'en');
        $reportData = $this->reportService->compileReportData($user, $request->all(), $lang);

        $html = view('reports.attendance_print', $reportData)->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Download multi-format official reports (Excel .xlsx, Word .doc, CSV, PDF HTML).
     */
    public function downloadReport(Request $request): Response|StreamedResponse|JsonResponse
    {
        $user = $this->resolveReportUser($request);

        if (!$user->canManageReportingAndAnalytics() && !$user->hasPermissionTo('attendance.view') && !$user->hasPermissionTo('attendance.record')) {
            return response()->json([
                'message' => 'Unauthorized: Library attendance viewing or reporting permission is required to download reports.',
            ], 403);
        }

        $format = strtolower($request->query('format', 'xlsx'));
        $lang = $request->query('lang', 'en');
        $scope = strtolower($request->query('scope', 'full'));
        
        $params = $request->all();
        $params['scope'] = $scope;
        $reportData = $this->reportService->compileReportData($user, $params, $lang);

        if ($format === 'xlsx' || $format === 'excel') {
            $filename = $this->reportService->generateReportFilename($reportData, 'xlsx');
            $content = $this->reportService->generateExcelXml($reportData);
            return response($content, 200, [
                'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            ]);
        }

        if ($format === 'doc' || $format === 'docx' || $format === 'word') {
            $filename = $this->reportService->generateReportFilename($reportData, 'doc');
            $content = $this->reportService->generateWordDoc($reportData);
            return response($content, 200, [
                'Content-Type' => 'application/msword; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            ]);
        }

        if ($format === 'csv') {
            $filename = $this->reportService->generateReportFilename($reportData, 'csv');
            $content = $this->reportService->generateCsv($reportData);
            return response($content, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            ]);
        }

        if ($format === 'pdf') {
            $filename = $this->reportService->generateReportFilename($reportData, 'pdf');
            $reportData['isPdf'] = true;
            $html = view('reports.attendance_print', $reportData)->render();

            if (!file_exists(storage_path('fonts'))) {
                @mkdir(storage_path('fonts'), 0775, true);
            }

            $options = new \Dompdf\Options();
            $options->set('isFontSubsettingEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'NotoSansEthiopic');
            $options->set('fontDir', storage_path('fonts'));
            $options->set('fontCache', storage_path('fonts'));
            $options->set('chroot', [
                realpath(base_path()),
                public_path(),
                storage_path(),
                '/usr/share/fonts',
            ]);

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('a4', 'portrait');
            $dompdf->render();

            return response($dompdf->output(), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            ]);
        }

        // Default: HTML print view
        $html = view('reports.attendance_print', $reportData)->render();
        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }

    /**
     * Resolve authenticated user from Sanctum or query token.
     */
    protected function resolveReportUser(Request $request): User
    {
        $user = $request->user();
        if (!$user && $request->filled('token')) {
            $token = PersonalAccessToken::findToken($request->query('token'));
            if ($token) {
                $user = $token->tokenable;
            }
        }

        if (!$user) {
            abort(401, 'Unauthenticated');
        }

        return $user;
    }
}
