<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\CirculationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DutyScheduleController;
use App\Http\Controllers\Api\RolePermissionController;
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

    // User Management (Full CRUD + Direct Permission Overrides)
    Route::post('/users/{user}/permissions', [UserController::class, 'overridePermissions']);
    Route::apiResource('users', UserController::class);

    // Duty Schedules & Shift Management
    Route::get('/schedules/my-turns', [DutyScheduleController::class, 'myTurns']);
    Route::get('/schedules/check-duty', [DutyScheduleController::class, 'checkDuty']);
    Route::get('/schedules', [DutyScheduleController::class, 'index']);
    Route::post('/schedules', [DutyScheduleController::class, 'store']);
    Route::delete('/schedules/{schedule}', [DutyScheduleController::class, 'destroy']);

    // Library Book Catalog
    Route::apiResource('books', BookController::class);

    // Book Circulation
    Route::get('/circulation/loans', [CirculationController::class, 'index']);
    Route::post('/circulation/return/{loan}', [CirculationController::class, 'returnBook']);

    // Issue Book: Enforces that the staff member is on duty for 'book_circulation' today!
    Route::post('/circulation/issue', [CirculationController::class, 'issue'])
        ->middleware('duty.turn:book_circulation');
});
