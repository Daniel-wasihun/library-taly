<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EthiopianDateService;
use App\Services\ShiftInactivityMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ShiftInactivityController extends Controller
{
    public function __construct(
        protected ShiftInactivityMonitorService $monitorService
    ) {}

    protected function resolveUser(Request $request)
    {
        $user = $request->user();
        if (!$user && ($request->filled('token') || $request->filled('api_token'))) {
            $rawToken = $request->query('token') ?: $request->query('api_token');
            $personalToken = \Laravel\Sanctum\PersonalAccessToken::findToken($rawToken);
            if ($personalToken) {
                $user = $personalToken->tokenable;
            }
        }
        return $user;
    }

    /**
     * Get real-time shift inactivity alerts for currently active shift.
     */
    public function alerts(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $branchId = null;
        if (!$user->canManageAllLibraries()) {
            $branchId = $user->library_id;
        } elseif ($request->filled('library_id')) {
            $branchId = (int) $request->library_id;
        }

        $alerts = $this->monitorService->getCurrentShiftInactivityAlerts($branchId);

        return response()->json([
            'success' => true,
            'data' => $alerts,
        ]);
    }

    /**
     * Get full inactivity compliance audit report for a specific date or shift.
     */
    public function report(Request $request): JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $branchId = null;
        if (!$user->canManageAllLibraries()) {
            $branchId = $user->library_id;
        } elseif ($request->filled('library_id')) {
            $branchId = (int) $request->library_id;
        }

        $date = $request->query('date');
        $shiftId = $request->filled('shift_id') ? (int) $request->shift_id : null;

        $report = $this->monitorService->getShiftInactivityReport($date, $branchId, $shiftId);

        return response()->json([
            'success' => true,
            'data' => $report,
        ]);
    }

    /**
     * Export or print inactivity compliance audit report.
     */
    public function export(Request $request): Response|StreamedResponse|JsonResponse
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $branchId = null;
        if (!$user->canManageAllLibraries()) {
            $branchId = $user->library_id;
        } elseif ($request->filled('library_id')) {
            $branchId = (int) $request->library_id;
        }

        $date = $request->query('date');
        $shiftId = $request->filled('shift_id') ? (int) $request->shift_id : null;
        $format = strtolower($request->query('format', 'print'));
        $isAmharic = $request->query('lang') === 'am';

        $report = $this->monitorService->getShiftInactivityReport($date, $branchId, $shiftId);

        if ($format === 'csv') {
            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="shift_inactivity_audit_' . ($date ?: now()->toDateString()) . '.csv"',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
                'Expires' => '0',
            ];

            return new StreamedResponse(function () use ($report, $isAmharic) {
                $file = fopen('php://output', 'w');
                fprintf($file, chr(0xEF) . chr(0xBB) . chr(0xBF)); // UTF-8 BOM

                if ($isAmharic) {
                    fputcsv($file, [
                        'የክስተት መለያ',
                        'የቤተ-መጻሕፍት ቅርንጫፍ',
                        'የፈረቃ ስም',
                        'የፈረቃ ቡድን',
                        'በፈረቃው የተመደቡ ሠራተኞች',
                        'የሥራ ማቆም ክፍተት (የፈረንጅ ሰዓት)',
                        'የሥራ ማቆም ክፍተት (የኢትዮጵያ ሰዓት)',
                        'የቆይታ ጊዜ (ደቂቃ)',
                        'ሁኔታ',
                        'ክብደት',
                        'የተመዘገበ ተጠቃሚ ብዛት',
                    ]);
                } else {
                    fputcsv($file, [
                        'Incident ID',
                        'Library Branch',
                        'Shift Name',
                        'Duty Group',
                        'Scheduled Attendants',
                        'Inactive Interval (EAT)',
                        'Inactive Interval (Ethiopian)',
                        'Duration (Minutes)',
                        'Status',
                        'Severity',
                        'Visitor Counts Logged',
                    ]);
                }

                foreach ($report['incidents'] as $inc) {
                    $attendantNames = implode(', ', array_map(function ($a) {
                        return "{$a['name']} (@{$a['username']})";
                    }, $inc['attendants']));

                    $branchName = $isAmharic ? $inc['branch_name_am'] : $inc['branch_name'];
                    $shiftName = $isAmharic ? $inc['shift_name_am'] : $inc['shift_name'];

                    fputcsv($file, [
                        $inc['incident_id'],
                        $branchName,
                        $shiftName,
                        $inc['shift_group_name'],
                        $attendantNames,
                        $inc['interval_formatted'],
                        $inc['interval_ethiopian'],
                        $inc['duration_minutes'],
                        $inc['status'],
                        $inc['severity'],
                        $inc['total_shift_entries'],
                    ]);
                }

                fclose($file);
            }, 200, $headers);
        }

        // Return clean, printable institutional HTML view
        return response()->view('reports.inactivity_audit_print', [
            'report' => $report,
            'isAmharic' => $isAmharic,
            'generatedAt' => now('Africa/Addis_Ababa'),
            'generatedBy' => $user->name . ' (' . ($user->roles->first()?->name ?? 'Staff') . ')',
        ]);
    }
}
