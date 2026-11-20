<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = [
            // Dashboard
            'dashboard.view',

            // User Management CRUD
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Role Management CRUD
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',

            // Library Branch Management CRUD + Branch Management
            'libraries.view',
            'libraries.create',
            'libraries.update',
            'libraries.delete',
            'libraries.manage',

            // Academic Year Calendar Management
            'academic_years.view',
            'academic_years.create',
            'academic_years.update',
            'academic_years.delete',

            // Duty Schedules & Shift Matrix
            'schedules.view',
            'schedules.manage',

            // Physical Attendance Management
            'attendance.view',
            'attendance.record',
            'attendance.update',
            'attendance.delete',
            'attendance.analytics',
        ];

        foreach ($allPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // Ensure roles exist
        $superAdmin = Role::firstOrCreate(['name' => 'Super Admin']);
        $admin = Role::firstOrCreate(['name' => 'Admin']);
        $manager = Role::firstOrCreate(['name' => 'Manager']);
        $librarian = Role::firstOrCreate(['name' => 'Librarian']);
        $attendant = Role::firstOrCreate(['name' => 'Attendant']);

        // Super Admin & Admin have all permissions
        $superAdmin->syncPermissions(Permission::all());
        $admin->syncPermissions(Permission::all());

        // Manager permissions: Dashboard view, users view, roles view, libraries manage, academic years, schedules, attendance analytics
        $manager->syncPermissions([
            'dashboard.view',
            'users.view',
            'roles.view',
            'libraries.view',
            'libraries.manage',
            'academic_years.view',
            'academic_years.create',
            'academic_years.update',
            'schedules.view',
            'schedules.manage',
            'attendance.view',
            'attendance.analytics',
        ]);

        // Librarian: View library, view academic years, view schedules, attendance view and record
        $librarian->syncPermissions([
            'libraries.view',
            'academic_years.view',
            'schedules.view',
            'attendance.view',
            'attendance.record',
        ]);

        // Attendant: View schedules, attendance view, record, update, delete
        $attendant->syncPermissions([
            'schedules.view',
            'attendance.view',
            'attendance.record',
            'attendance.update',
            'attendance.delete',
        ]);

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse necessary for permission sync
    }
};
