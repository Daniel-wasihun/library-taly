<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AttendanceController;
use App\Http\Controllers\Api\AttendanceSyncController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DutyScheduleController;
use App\Http\Controllers\Api\GroupShiftScheduleController;
use App\Http\Controllers\Api\LibraryController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\ShiftController;
use App\Http\Controllers\Api\ShiftGroupController;
use App\Http\Controllers\Api\ShiftInactivityController;
use App\Http\Controllers\Api\TranslationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
// Localization Endpoints
Route::prefix('translations')->group(function () {
    Route::get('/languages', [TranslationController::class, 'getLanguages']);
    Route::get('/front/{lang?}', [TranslationController::class, 'getFront']);
});

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

// Printable and downloadable official reports (supports token query parameter or bearer header)
Route::get('/physical/attendance/report/print', [AttendanceController::class, 'printReport']);
Route::get('/physical/attendance/report/download', [AttendanceController::class, 'downloadReport']);

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum Authenticated)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    // Auth profile
    Route::prefix('auth')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);

    // Roles and Available Tasks
    Route::get('/roles', [RolePermissionController::class, 'roles']);
    Route::post('/roles', [RolePermissionController::class, 'storeRole']);
    Route::put('/roles/{role}', [RolePermissionController::class, 'updateRole']);
    Route::delete('/roles/{role}', [RolePermissionController::class, 'destroyRole']);
    Route::get('/tasks', [RolePermissionController::class, 'tasks']);

    // Library Branches (Full CRUD)
    Route::apiResource('libraries', LibraryController::class);

    // Academic Years Calendar CRUD & Active Year Switcher
    Route::post('/academic-years/{academicYear}/set-current', [AcademicYearController::class, 'setCurrent']);
    Route::apiResource('academic-years', AcademicYearController::class);

    // User Management (Full CRUD + Direct Permission Overrides)
    Route::post('/users/{user}/permissions', [UserController::class, 'overridePermissions']);
    Route::apiResource('users', UserController::class);

    // Duty Schedules & Shift Management
    Route::get('/schedules/my-turns', [DutyScheduleController::class, 'myTurns']);
    Route::get('/schedules/check-duty', [DutyScheduleController::class, 'checkDuty']);
    Route::get('/schedules', [DutyScheduleController::class, 'index']);
    Route::post('/schedules', [DutyScheduleController::class, 'store']);
    Route::delete('/schedules/{schedule}', [DutyScheduleController::class, 'destroy']);

    // Attendant Shift Inactivity Monitoring & Compliance Audit Reporting (> 30 Mins Inactive)
    Route::get('/shifts/inactivity-alerts', [ShiftInactivityController::class, 'alerts']);
    Route::get('/shifts/inactivity-report', [ShiftInactivityController::class, 'report']);
    Route::get('/shifts/inactivity-report/export', [ShiftInactivityController::class, 'export']);

    // Shift Definitions CRUD (Day Shift, Night Shift with Ethiopian Local Time)
    Route::apiResource('shifts', ShiftController::class);

    // Shift Groups CRUD (Group A, Group B with Branch and Attendants)
    Route::get('/shift-groups/eligible-staff', [ShiftGroupController::class, 'eligibleStaff']);
    Route::apiResource('shift-groups', ShiftGroupController::class);

    // Ethiopian Calendar Monthly Shift Scheduling Matrix & Assignment
    Route::get('/shift-schedules/matrix', [GroupShiftScheduleController::class, 'matrix']);
    Route::post('/shift-schedules/assign', [GroupShiftScheduleController::class, 'assign']);
    Route::post('/shift-schedules/auto-generate', [GroupShiftScheduleController::class, 'autoGenerate']);
    Route::get('/shift-schedules/holidays', [GroupShiftScheduleController::class, 'holidays']);
    Route::post('/shift-schedules/holidays', [GroupShiftScheduleController::class, 'storeHoliday']);
    Route::delete('/shift-schedules/holidays/{holiday}', [GroupShiftScheduleController::class, 'destroyHoliday']);
    Route::get('/shift-schedules/check-overlap', [GroupShiftScheduleController::class, 'checkOverlap']);
    Route::delete('/shift-schedules/{groupShiftSchedule}', [GroupShiftScheduleController::class, 'destroy']);

    // Physical Library Attendance / Usage Counter (LMS Pattern)
    Route::prefix('physical/attendance')->group(function () {
        Route::get('/', [AttendanceController::class, 'index']);
        Route::post('/log', [AttendanceController::class, 'logEntry']);
        Route::post('/sync', [AttendanceSyncController::class, 'sync']);
        Route::get('/stats', [AttendanceController::class, 'stats']);
        Route::get('/export', [AttendanceController::class, 'export']);
        Route::get('/report/download', [AttendanceController::class, 'downloadReport']);
        Route::patch('/{slug}/count', [AttendanceController::class, 'updateCount']);
        Route::delete('/{slug}', [AttendanceController::class, 'destroy']);
    });
});
