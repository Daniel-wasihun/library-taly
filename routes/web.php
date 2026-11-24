<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/run-seeder', function () {
    try {
        Artisan::call('migrate', ['--force' => true]);
        $migrateOutput = Artisan::output();

        Artisan::call('db:seed', ['--force' => true]);
        $seedOutput = Artisan::output();

        return response()->json([
            'status' => 'success',
            'message' => 'Database migrated and seeded successfully!',
            'counts' => [
                'users' => \App\Models\User::count(),
                'libraries' => \App\Models\Library::count(),
                'shifts' => \App\Models\Shift::count(),
                'shift_groups' => \App\Models\ShiftGroup::count(),
                'group_schedules' => \App\Models\GroupShiftSchedule::count(),
                'academic_years' => \App\Models\AcademicYear::count(),
                'attendance_records' => \App\Models\LibraryAttendance::count(),
                'ethiopian_holidays' => \App\Models\EthiopianHoliday::count(),
            ],
            'output' => [
                'migrate' => trim($migrateOutput),
                'seed' => trim($seedOutput),
            ],
        ], 200);
    } catch (\Throwable $e) {
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'class' => get_class($e),
        ], 500);
    }
});

Route::get('/health-check-db', function () {
    $response = [
        'php_version' => PHP_VERSION,
        'app_key_set' => !empty(config('app.key')),
        'vite_manifest_exists' => file_exists(public_path('build/manifest.json')),
        'storage_writable' => is_writable(storage_path('framework/sessions')),
    ];

    try {
        DB::connection()->getPdo();
        $response['db_status'] = 'connected';
        $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        $tableNames = array_map(fn($t) => $t->table_name, $tables);
        $response['tables_count'] = count($tableNames);
        $response['has_sessions'] = in_array('sessions', $tableNames);
        $response['has_users'] = in_array('users', $tableNames);
        return response()->json($response, 200);
    } catch (\Throwable $e) {
        $response['db_status'] = 'failed';
        $response['error_message'] = $e->getMessage();
        $response['error_class'] = get_class($e);
        $response['db_config'] = [
            'host' => config('database.connections.pgsql.host'),
            'port' => config('database.connections.pgsql.port'),
            'database' => config('database.connections.pgsql.database'),
            'username' => config('database.connections.pgsql.username'),
            'sslmode' => config('database.connections.pgsql.sslmode'),
        ];
        return response()->json($response, 500);
    }
});

Route::get('/login', function () {
    return view('app');
})->name('login');

Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');

