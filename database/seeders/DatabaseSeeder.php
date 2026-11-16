<?php

namespace Database\Seeders;

use App\Models\DutySchedule;
use App\Models\Library;
use App\Models\LibraryAttendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Create Granular Spatie Permissions
        $permissions = [
            // Library Branches Management
            'libraries.view',
            'libraries.create',
            'libraries.edit',
            'libraries.delete',
            'libraries.manage',
            'libraries.manage_all',

            // User management
            'users.view',
            'users.create',
            'users.edit',
            'users.delete',
            'users.override_permissions',

            // Roles & RBAC Matrix
            'roles.view',
            'roles.create',
            'roles.edit',
            'roles.delete',

            // Physical Attendance & Usage Counter (LMS Pattern)
            'attendance.view',
            'attendance.create',
            'attendance.edit',
            'attendance.delete',
            'attendance.report',

            // Duty Schedules
            'schedules.view',
            'schedules.create',
            'schedules.delete',

            // Reports & Analytics
            'reports.view',
            'reports.export',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // 2. Create Spatie Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $headLibrarianRole = Role::firstOrCreate(['name' => 'Head Librarian']);
        $deskLibrarianRole = Role::firstOrCreate(['name' => 'Desk Librarian']);
        $catalogerRole = Role::firstOrCreate(['name' => 'Cataloging Specialist']);
        $archiveAssistantRole = Role::firstOrCreate(['name' => 'Archive Assistant']);
        $facultyRole = Role::firstOrCreate(['name' => 'Faculty Researcher']);
        $studentRole = Role::firstOrCreate(['name' => 'Student Member']);

        // Assign Permissions to Roles
        $superAdminRole->syncPermissions(Permission::all());

        $headLibrarianRole->syncPermissions([
            'libraries.view',
            'libraries.create',
            'libraries.edit',
            'libraries.manage_all',
            'users.view',
            'users.create',
            'users.edit',
            'roles.view',
            'attendance.view',
            'attendance.create',
            'attendance.edit',
            'attendance.delete',
            'attendance.report',
            'schedules.view',
            'schedules.create',
            'schedules.delete',
            'reports.view',
            'reports.export',
        ]);

        $deskLibrarianRole->syncPermissions([
            'libraries.view',
            'attendance.view',
            'attendance.create',
            'attendance.edit',
            'attendance.delete',
            'attendance.report',
            'schedules.view',
        ]);

        $catalogerRole->syncPermissions([
            'libraries.view',
            'attendance.view',
            'reports.view',
        ]);

        $archiveAssistantRole->syncPermissions([
            'libraries.view',
            'attendance.view',
            'reports.view',
        ]);

        $facultyRole->syncPermissions([
            'libraries.view',
            'attendance.view',
        ]);

        $studentRole->syncPermissions([
            'libraries.view',
        ]);

        // 3. Seed Realistic University Library Branches
        $mainLibrary = Library::firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Central Library',
                'name_am' => 'ዋና ማዕከላዊ ቤተ-መጻሕፍት',
                'slug' => 'main-central-library',
                'location' => 'Main Campus, Administration Block, 2nd Floor',
                'phone' => '+251 111 234501',
                'email' => 'main.library@unilib.edu',
                'description' => 'University central academic repository, directorate office, and general multidisciplinary reference stacks.',
                'is_active' => true,
            ]
        );

        $sciTechLibrary = Library::firstOrCreate(
            ['code' => 'SCITECH'],
            [
                'name' => 'Science & Technology Library',
                'name_am' => 'የሳይንስና ቴክኖሎጂ ቤተ-መጻሕፍት',
                'slug' => 'science-technology-library',
                'location' => 'Technology Campus, Engineering Complex, Block C',
                'phone' => '+251 111 234502',
                'email' => 'scitech.library@unilib.edu',
                'description' => 'Specialized collections for computer science, mechanical, electrical, and civil engineering.',
                'is_active' => true,
            ]
        );

        $healthLibrary = Library::firstOrCreate(
            ['code' => 'HEALTH'],
            [
                'name' => 'Health Sciences Library',
                'name_am' => 'የጤና ሳይንስ ቤተ-መጻሕፍት',
                'slug' => 'health-sciences-library',
                'location' => 'Medical Faculty, Hospital Wing 3rd Floor',
                'phone' => '+251 111 234503',
                'email' => 'health.library@unilib.edu',
                'description' => 'Clinical medicine, pharmacology, human anatomy, and public health research archives.',
                'is_active' => true,
            ]
        );

        $lawLibrary = Library::firstOrCreate(
            ['code' => 'LAW'],
            [
                'name' => 'Law & Humanities Library',
                'name_am' => 'የሕግና ማኅበራዊ ሳይንስ ቤተ-መጻሕፍት',
                'slug' => 'law-humanities-library',
                'location' => 'Siddist Kilo Campus, Law School Building, 1st Floor',
                'phone' => '+251 111 234504',
                'email' => 'law.library@unilib.edu',
                'description' => 'Federal legal gazettes, international treaties, Ethiopian jurisprudence, and political science stacks.',
                'is_active' => true,
            ]
        );

        // 4. Seed Realistic Users with Branch Assignment (or General / All Libraries)
        $defaultPassword = Hash::make('password123');

        // Super Admin: General / All Libraries (library_id = null)
        $admin = User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Dr. Abebe Bekele',
                'email' => 'admin@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 911 234567',
                'library_id' => null, // Global / General
            ]
        );
        $admin->syncRoles(['Super Admin']);

        // Head Librarian: General / All Libraries (library_id = null)
        $headLibrarian = User::updateOrCreate(
            ['username' => 'selam_head'],
            [
                'name' => 'Selamawit Tadesse',
                'email' => 'selamawit@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 912 345678',
                'library_id' => null, // Global / General
            ]
        );
        $headLibrarian->syncRoles(['Head Librarian']);

        // On-Duty Desk Librarian: Main Central Library
        $onDutyLibrarian = User::updateOrCreate(
            ['username' => 'tigist_duty'],
            [
                'name' => 'Tigist Alemu',
                'email' => 'tigist@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 922 345678',
                'library_id' => $mainLibrary->id,
            ]
        );
        $onDutyLibrarian->syncRoles(['Desk Librarian']);
        $onDutyLibrarian->syncPermissions(['reports.view']);

        // Off-Duty Desk Librarian: Main Central Library
        $offDutyLibrarian = User::updateOrCreate(
            ['username' => 'yohannes_offduty'],
            [
                'name' => 'Yohannes Girma',
                'email' => 'yohannes@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 933 456789',
                'library_id' => $mainLibrary->id,
            ]
        );
        $offDutyLibrarian->syncRoles(['Desk Librarian']);

        // Cataloging Specialist: Science & Technology Library
        $cataloger = User::updateOrCreate(
            ['username' => 'kassahun_cat'],
            [
                'name' => 'Kassahun Desta',
                'email' => 'kassahun@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 914 567890',
                'library_id' => $sciTechLibrary->id,
            ]
        );
        $cataloger->syncRoles(['Cataloging Specialist']);

        // Archive Assistant: Law & Humanities Library
        $archiveAssistant = User::updateOrCreate(
            ['username' => 'bethlehem_arch'],
            [
                'name' => 'Bethlehem Wolde',
                'email' => 'bethlehem@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 915 678901',
                'library_id' => $lawLibrary->id,
            ]
        );
        $archiveAssistant->syncRoles(['Archive Assistant']);

        // Faculty Researchers:
        $faculty1 = User::updateOrCreate(
            ['username' => 'dr_tariku'],
            [
                'name' => 'Dr. Tariku Mengesha (Associate Prof.)',
                'email' => 'tariku.m@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 916 789012',
                'library_id' => $lawLibrary->id,
            ]
        );
        $faculty1->syncRoles(['Faculty Researcher']);

        $faculty2 = User::updateOrCreate(
            ['username' => 'prof_almaz'],
            [
                'name' => 'Prof. Almaz Haile (Dept of Medicine)',
                'email' => 'almaz.h@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 917 890123',
                'library_id' => $healthLibrary->id,
            ]
        );
        $faculty2->syncRoles(['Faculty Researcher']);

        // Student Members:
        $student1 = User::updateOrCreate(
            ['username' => 'sara_student'],
            [
                'name' => 'Sara Tadesse (CS Senior)',
                'email' => 'sara@student.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 944 567890',
                'library_id' => $sciTechLibrary->id,
            ]
        );
        $student1->syncRoles(['Student Member']);

        $student2 = User::updateOrCreate(
            ['username' => 'dawit_student'],
            [
                'name' => 'Dawit Haile (Med 4th Year)',
                'email' => 'dawit@student.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 955 678901',
                'library_id' => $healthLibrary->id,
            ]
        );
        $student2->syncRoles(['Student Member']);

        $student3 = User::updateOrCreate(
            ['username' => 'marta_student'],
            [
                'name' => 'Marta Kebede (Law 3rd Year)',
                'email' => 'marta@student.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 966 789012',
                'library_id' => $lawLibrary->id,
            ]
        );
        $student3->syncRoles(['Student Member']);

        $student4 = User::updateOrCreate(
            ['username' => 'robel_student'],
            [
                'name' => 'Robel Tesfaye (Civil Eng 2nd Year)',
                'email' => 'robel@student.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 977 890123',
                'library_id' => $sciTechLibrary->id,
            ]
        );
        $student4->syncRoles(['Student Member']);

        // Inactive and Suspended Users
        $inactiveUser = User::updateOrCreate(
            ['username' => 'bereket_inactive'],
            [
                'name' => 'Bereket Assefa (Graduated)',
                'email' => 'bereket@alumni.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'inactive',
                'phone' => '+251 988 901234',
                'library_id' => $mainLibrary->id,
            ]
        );
        $inactiveUser->syncRoles(['Student Member']);

        $suspendedUser = User::updateOrCreate(
            ['username' => 'natnael_suspended'],
            [
                'name' => 'Natnael Solomon (Suspended Borrower)',
                'email' => 'natnael@student.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'suspended',
                'phone' => '+251 999 012345',
                'library_id' => $mainLibrary->id,
            ]
        );
        $suspendedUser->syncRoles(['Student Member']);

        // 5. Create Duty Schedules (Turns) with Library Branch Scoping
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();
        $twoDaysLater = Carbon::today()->addDays(2)->toDateString();
        $threeDaysLater = Carbon::today()->addDays(3)->toDateString();
        $nextWeek = Carbon::today()->addDays(7)->toDateString();

        // Tigist is on duty TODAY for book circulation at Main Library
        DutySchedule::firstOrCreate(
            [
                'user_id' => $onDutyLibrarian->id,
                'task_key' => 'book_circulation',
                'shift_date' => $today,
            ],
            [
                'library_id' => $mainLibrary->id,
                'start_time' => '08:00:00',
                'end_time' => '17:30:00',
                'status' => 'active',
                'notes' => 'Main Circulation Desk - Morning & Afternoon Shift',
            ]
        );

        // Selamawit (Head Librarian) general shift
        DutySchedule::firstOrCreate(
            [
                'user_id' => $headLibrarian->id,
                'task_key' => 'schedule_management',
                'shift_date' => $today,
            ],
            [
                'library_id' => null, // All Branches / System-wide
                'start_time' => '08:30:00',
                'end_time' => '17:00:00',
                'status' => 'active',
                'notes' => 'Head Office Operations & System-Wide Roster Control',
            ]
        );

        // Cataloger at SciTech
        DutySchedule::firstOrCreate(
            [
                'user_id' => $cataloger->id,
                'task_key' => 'catalog_management',
                'shift_date' => $tomorrow,
            ],
            [
                'library_id' => $sciTechLibrary->id,
                'start_time' => '09:00:00',
                'end_time' => '15:00:00',
                'status' => 'scheduled',
                'notes' => 'SciTech Library New Acquisitions Cataloging',
            ]
        );

        // Yohannes off duty today, turn in 3 days at Main Library
        DutySchedule::firstOrCreate(
            [
                'user_id' => $offDutyLibrarian->id,
                'task_key' => 'book_circulation',
                'shift_date' => $threeDaysLater,
            ],
            [
                'library_id' => $mainLibrary->id,
                'start_time' => '08:30:00',
                'end_time' => '17:00:00',
                'status' => 'scheduled',
                'notes' => 'Upcoming Main Desk Turn',
            ]
        );

        // 6. Seed Realistic Physical Attendance Logs (LMS Pattern)
        if (LibraryAttendance::count() === 0) {
            $branches = [$mainLibrary, $sciTechLibrary, $healthLibrary, $lawLibrary];
            $userTypes = ['student', 'teacher', 'staff'];
            $genders = ['male', 'female'];
            $staffUsers = [$onDutyLibrarian, $cataloger, $archiveAssistant, $admin];

            // Today's entries
            foreach ($branches as $branch) {
                for ($i = 0; $i < 6; $i++) {
                    $uType = $userTypes[$i % 3];
                    $gen = $genders[$i % 2];
                    $count = ($i % 4 === 0) ? 3 : 1;
                    $staff = $staffUsers[$i % 4];

                    LibraryAttendance::create([
                        'library_id' => $branch->id,
                        'user_type' => $uType,
                        'gender' => $gen,
                        'visitor_count' => $count,
                        'staff_id' => $staff->id,
                        'entry_at' => Carbon::now()->subMinutes(rand(5, 300)),
                    ]);
                }
            }

            // Past week entries for trends & analytics
            for ($d = 1; $d <= 6; $d++) {
                $date = Carbon::now()->subDays($d);
                foreach ($branches as $branch) {
                    LibraryAttendance::create([
                        'library_id' => $branch->id,
                        'user_type' => 'student',
                        'gender' => 'male',
                        'visitor_count' => rand(15, 45),
                        'staff_id' => $onDutyLibrarian->id,
                        'entry_at' => $date->copy()->setTime(10, 15),
                    ]);

                    LibraryAttendance::create([
                        'library_id' => $branch->id,
                        'user_type' => 'student',
                        'gender' => 'female',
                        'visitor_count' => rand(12, 40),
                        'staff_id' => $onDutyLibrarian->id,
                        'entry_at' => $date->copy()->setTime(11, 45),
                    ]);

                    LibraryAttendance::create([
                        'library_id' => $branch->id,
                        'user_type' => 'teacher',
                        'gender' => ($d % 2 === 0) ? 'female' : 'male',
                        'visitor_count' => rand(2, 8),
                        'staff_id' => $cataloger->id,
                        'entry_at' => $date->copy()->setTime(14, 20),
                    ]);
                }
            }
        }
    }
}
