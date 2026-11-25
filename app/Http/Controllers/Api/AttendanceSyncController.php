<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibraryAttendance;
use App\Models\Library;
use App\Services\DutyScheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceSyncController extends Controller
{
    public function __construct(
        protected DutyScheduleService $dutyService
    ) {}

    /**
     * Batch sync offline attendance logs with strict shift schedule validation.
     * Records recorded outside the attendant's scheduled window are pruned/rejected.
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user || !$user->hasPermissionTo('attendance.record')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only attendants and authorized staff can record or sync attendance.',
            ], 403);
        }

        $validated = $request->validate([
            'records' => ['required', 'array', 'min:1', 'max:500'],
            'records.*.client_uuid' => ['required', 'string'],
            'records.*.library_id' => ['required', 'integer', 'exists:libraries,id'],
            'records.*.user_type' => ['required', 'in:student,teacher,staff'],
            'records.*.gender' => ['required', 'in:male,female'],
            'records.*.count' => ['required', 'integer', 'min:1', 'max:50'],
            'records.*.recorded_at' => ['required', 'string'],
        ]);

        $synced = [];
        $rejected = [];
        $duplicateCount = 0;

        DB::beginTransaction();
        try {
            foreach ($validated['records'] as $item) {
                $clientUuid = $item['client_uuid'];
                $libraryId = (int) $item['library_id'];
                $count = (int) $item['count'];

                // 1. Check for duplicates using client_uuid / metadata or exact fingerprint
                $existing = LibraryAttendance::where('staff_id', $user->id)
                    ->where('library_id', $libraryId)
                    ->where('user_type', $item['user_type'])
                    ->where('gender', $item['gender'])
                    ->where('visitor_count', $count)
                    ->where('entry_at', Carbon::parse($item['recorded_at']))
                    ->first();

                if ($existing) {
                    $duplicateCount++;
                    $synced[] = [
                        'client_uuid' => $clientUuid,
                        'id' => $existing->id,
                        'slug' => $existing->slug,
                        'status' => 'already_synced',
                    ];
                    continue;
                }

                // 2. Branch restriction check
                if (!$user->canManageAllLibraries()) {
                    if (!$user->library_id || (int) $user->library_id !== $libraryId) {
                        $rejected[] = [
                            'client_uuid' => $clientUuid,
                            'reason' => 'Branch mismatch: Not authorized for library ID ' . $libraryId,
                            'code' => 'BRANCH_MISMATCH',
                        ];
                        continue;
                    }
                }

                // 3. Strict Shift & Schedule Validation
                // Parse timestamp when attendant recorded the counter offline
                try {
                    $recordedAt = Carbon::parse($item['recorded_at'])->setTimezone('Africa/Addis_Ababa');
                } catch (\Throwable $e) {
                    $rejected[] = [
                        'client_uuid' => $clientUuid,
                        'reason' => 'Invalid timestamp format: ' . $item['recorded_at'],
                        'code' => 'INVALID_TIMESTAMP',
                    ];
                    continue;
                }

                $recordDate = $recordedAt->toDateString();

                // Validate against DutyScheduleService for that specific recorded time
                $dutyCheck = $this->dutyService->canPerformTask(
                    $user,
                    'attendance_desk',
                    $recordDate,
                    $libraryId,
                    true,
                    $recordedAt
                );

                if (!$dutyCheck['allowed']) {
                    $rejected[] = [
                        'client_uuid' => $clientUuid,
                        'recorded_at' => $item['recorded_at'],
                        'reason' => 'Pruned: Outside assigned shift/schedule window (' . ($dutyCheck['reason'] ?? 'Not scheduled') . ')',
                        'code' => $dutyCheck['code'] ?? 'OUTSIDE_DUTY_TURN',
                    ];
                    continue; // Skip inserting invalid/out-of-schedule offline count
                }

                // 4. Valid record: save to database
                $record = LibraryAttendance::create([
                    'library_id' => $libraryId,
                    'user_type' => $item['user_type'],
                    'gender' => $item['gender'],
                    'visitor_count' => $count,
                    'staff_id' => $user->id,
                    'entry_at' => $recordedAt,
                ]);

                $synced[] = [
                    'client_uuid' => $clientUuid,
                    'id' => $record->id,
                    'slug' => $record->slug,
                    'status' => 'created',
                ];
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sync processed successfully.',
                'synced_count' => count($synced),
                'rejected_count' => count($rejected),
                'duplicate_count' => $duplicateCount,
                'synced' => $synced,
                'rejected' => $rejected,
            ]);

        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync offline attendance records: ' . $e->getMessage(),
            ], 500);
        }
    }
}
