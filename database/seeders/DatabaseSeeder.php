<?php

namespace Database\Seeders;

use App\Models\EthiopianHoliday;
use App\Models\GroupShiftSchedule;
use App\Models\Library;
use App\Models\LibraryAttendance;
use App\Models\Shift;
use App\Models\ShiftGroup;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
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
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // 1. Clean out existing permissions and roles
        DB::statement('SET CONSTRAINTS ALL DEFERRED');
        DB::table('model_has_permissions')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('role_has_permissions')->truncate();
        DB::table('permissions')->delete();
        DB::table('roles')->delete();

        // 2. Exact Permission List (CRUD on user, library, and physical attendance)
        $permissions = [
            // Dashboard
            'dashboard.view',

            // User Management CRUD
            'users.view',
            'users.create',
            'users.update',
            'users.delete',

            // Library Branch Management CRUD + Branch Management
            'libraries.view',
            'libraries.create',
            'libraries.update',
            'libraries.delete',
            'libraries.manage',

            // Physical Attendance Management
            'attendance.view',
            'attendance.record',
            'attendance.update',
            'attendance.delete',
            'attendance.analytics',
        ];

        foreach ($permissions as $perm) {
            Permission::create(['name' => $perm]);
        }

        // 3. Exact 5 Roles
        $superAdminRole = Role::create(['name' => 'Super Admin']);
        $adminRole = Role::create(['name' => 'Admin']);
        $managerRole = Role::create(['name' => 'Manager']);
        $librarianRole = Role::create(['name' => 'Librarian']);
        $attendantRole = Role::create(['name' => 'Attendant']);

        // 4. Assign Permissions to Roles
        // Super Admin & Admin: Full CRUD on users, libraries, and attendance
        $superAdminRole->syncPermissions(Permission::all());
        $adminRole->syncPermissions(Permission::all());

        // Manager: Library management, scoped reporting & analytics, and viewing users
        $managerRole->syncPermissions([
            'dashboard.view',
            'users.view',
            'libraries.view',
            'libraries.manage',
            'attendance.view',
            'attendance.analytics',
        ]);

        // Librarian: View library and record attendance
        $librarianRole->syncPermissions([
            'libraries.view',
            'attendance.view',
            'attendance.record',
        ]);

        // Attendant: Record attendance, and 2-minute update/delete
        $attendantRole->syncPermissions([
            'attendance.view',
            'attendance.record',
            'attendance.update',
            'attendance.delete',
        ]);

        // 5. Seed Exactly Three University Library Branches
        $technoLibrary = Library::updateOrCreate(
            ['code' => 'TECHNO'],
            [
                'name' => 'Techno Library',
                'name_am' => 'የቴክኖሎጂ ቤተ-መጻሕፍት',
                'slug' => 'techno-library',
                'location' => 'Technology Campus, Engineering Complex, Block C',
                'phone' => '+251 111 234502',
                'email' => 'techno.library@unilib.edu',
                'description' => 'Specialized collections for technology, software engineering, and digital innovation stacks.',
                'is_active' => true,
            ]
        );

        $socialLibrary = Library::updateOrCreate(
            ['code' => 'SOCIAL'],
            [
                'name' => 'Social Library',
                'name_am' => 'የማኅበራዊ ሳይንስ ቤተ-መጻሕፍት',
                'slug' => 'social-library',
                'location' => 'Social Sciences Faculty, Academic Wing 3rd Floor',
                'phone' => '+251 111 234503',
                'email' => 'social.library@unilib.edu',
                'description' => 'Social sciences, economics, law, sociology, and humanities literature.',
                'is_active' => true,
            ]
        );

        $naturalLibrary = Library::updateOrCreate(
            ['code' => 'NATURAL'],
            [
                'name' => 'Natural Library',
                'name_am' => 'የተፈጥሮ ሳይንስ ቤተ-መጻሕፍት',
                'slug' => 'natural-library',
                'location' => 'Natural & Computational Sciences Faculty, Block 1',
                'phone' => '+251 111 234504',
                'email' => 'natural.library@unilib.edu',
                'description' => 'Natural sciences, physics, chemistry, biology, mathematics, and environmental archives.',
                'is_active' => true,
            ]
        );

        // Delete any legacy/other libraries so ONLY TECHNO, SOCIAL, NATURAL exist
        $otherLibraries = Library::whereNotIn('code', ['TECHNO', 'SOCIAL', 'NATURAL'])->get();
        foreach ($otherLibraries as $oldLib) {
            User::where('library_id', $oldLib->id)->update(['library_id' => $technoLibrary->id]);
            LibraryAttendance::where('library_id', $oldLib->id)->update(['library_id' => $technoLibrary->id]);
            $oldLib->delete();
        }

        // 6. Seed Realistic Users with Roles & Branch Assignments
        $defaultPassword = Hash::make('password123');

        // Super Admin (Hidden, Global / All Libraries)
        $superAdminUser = User::updateOrCreate(
            ['email' => 'admin@unilib.edu'],
            [
                'username' => 'admin',
                'name' => 'Dr. Abebe Bekele',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 911 234567',
                'library_id' => null, // Global
            ]
        );
        $superAdminUser->syncRoles(['Super Admin']);

        // System Admin (Visible in user list, Global / All Libraries)
        $adminUser = User::updateOrCreate(
            ['email' => 'dawit.admin@unilib.edu'],
            [
                'username' => 'admin_dawit',
                'name' => 'Dawit Haile',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 911 345678',
                'library_id' => null, // Global
            ]
        );
        $adminUser->syncRoles(['Admin']);

        // General Manager (Manages all branch reporting & analytics)
        $generalManager = User::updateOrCreate(
            ['email' => 'selamawit@unilib.edu'],
            [
                'username' => 'selam_manager',
                'name' => 'Selamawit Tadesse',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 912 345678',
                'library_id' => null, // Global / General
            ]
        );
        $generalManager->syncRoles(['Manager']);

        // Branch Manager (Assigned to Techno Library: manages reporting for Techno Library)
        $branchManagerMain = User::updateOrCreate(
            ['email' => 'kassahun@unilib.edu'],
            [
                'username' => 'kassahun_manager',
                'name' => 'Kassahun Desta',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 914 567890',
                'library_id' => $technoLibrary->id,
            ]
        );
        $branchManagerMain->syncRoles(['Manager']);

        // Librarian (Assigned to Techno Library)
        $librarianUser = User::updateOrCreate(
            ['email' => 'tigist@unilib.edu'],
            [
                'username' => 'tigist_lib',
                'name' => 'Tigist Alemu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 922 345678',
                'library_id' => $technoLibrary->id,
            ]
        );
        $librarianUser->syncRoles(['Librarian']);

        // =========================================================================
        // DISTINCT ATTENDANTS SEEDING (16 per library: Techno, Social, Natural = 48)
        // Grouped evenly into 4 groups (4 from each branch in each group)
        // =========================================================================

        $ethiopianNames = [
            ['Yohannes', 'Girma'],
            ['Almaz', 'Kebede'],
            ['Dawit', 'Mengistu'],
            ['Bethlehem', 'Tadesse'],
            ['Henok', 'Berhanu'],
            ['Senait', 'Tefera'],
            ['Ermias', 'Assefa'],
            ['Hiwot', 'Kasahun'],
            ['Samuel', 'Alemu'],
            ['Rahel', 'Bekele'],
            ['Daniel', 'Fikre'],
            ['Selamawit', 'Gashaw'],
            ['Natnael', 'Solomon'],
            ['Kalkidan', 'Abebe'],
            ['Biniyam', 'Tesfaye'],
            ['Meron', 'Haile'],
        ];

        // 1. Techno Library Attendants (16 attendants)
        $technoAttendants = [];
        for ($i = 0; $i < 16; $i++) {
            $num = $i + 1;
            [$first, $last] = $ethiopianNames[$i];
            $username = ($i === 0) ? 'yohannes_attendant' : "techno_attendant_{$num}";
            $email = ($i === 0) ? 'yohannes@unilib.edu' : "techno.attendant{$num}@unilib.edu";

            $u = User::updateOrCreate(
                ['email' => $email],
                [
                    'username' => $username,
                    'name' => "{$first} {$last}",
                    'password' => $defaultPassword,
                    'status' => 'active',
                    'phone' => '+251 911 ' . sprintf('%06d', 100000 + $num),
                    'library_id' => $technoLibrary->id,
                ]
            );
            $u->syncRoles(['Attendant']);
            $technoAttendants[] = $u;
        }

        // 2. Social Library Attendants (16 attendants)
        $socialAttendants = [];
        for ($i = 0; $i < 16; $i++) {
            $num = $i + 1;
            [$first, $last] = $ethiopianNames[($i + 4) % 16];
            $username = "social_attendant_{$num}";
            $email = "social.attendant{$num}@unilib.edu";

            $u = User::updateOrCreate(
                ['email' => $email],
                [
                    'username' => $username,
                    'name' => "{$first} {$last}",
                    'password' => $defaultPassword,
                    'status' => 'active',
                    'phone' => '+251 922 ' . sprintf('%06d', 200000 + $num),
                    'library_id' => $socialLibrary->id,
                ]
            );
            $u->syncRoles(['Attendant']);
            $socialAttendants[] = $u;
        }

        // 3. Natural Library Attendants (16 attendants)
        $naturalAttendants = [];
        for ($i = 0; $i < 16; $i++) {
            $num = $i + 1;
            [$first, $last] = $ethiopianNames[($i + 8) % 16];
            $username = "natural_attendant_{$num}";
            $email = "natural.attendant{$num}@unilib.edu";

            $u = User::updateOrCreate(
                ['email' => $email],
                [
                    'username' => $username,
                    'name' => "{$first} {$last}",
                    'password' => $defaultPassword,
                    'status' => 'active',
                    'phone' => '+251 933 ' . sprintf('%06d', 300000 + $num),
                    'library_id' => $naturalLibrary->id,
                ]
            );
            $u->syncRoles(['Attendant']);
            $naturalAttendants[] = $u;
        }

        // Floating General Attendant
        $attendantGeneral = User::updateOrCreate(
            ['email' => 'sara@unilib.edu'],
            [
                'username' => 'sara_attendant',
                'name' => 'Sara Tadesse',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 944 567890',
                'library_id' => null,
            ]
        );
        $attendantGeneral->syncRoles(['Attendant']);

        // 7. Seed Attendance Records for Today and Past Historical Days
        LibraryAttendance::query()->forceDelete();

        $staffMembers = array_merge(
            array_slice($technoAttendants, 0, 4),
            array_slice($socialAttendants, 0, 4),
            array_slice($naturalAttendants, 0, 4),
            [$attendantGeneral, $superAdminUser]
        );
        $libraries = [$technoLibrary, $socialLibrary, $naturalLibrary];
        $today = Carbon::today();

        $todayRows = [];
        // Distribution weights: students ~68%, teachers ~20%, staff ~12%
        $patronPool = [
            'student', 'student', 'student', 'student', 'student', 'student', 'student',
            'teacher', 'teacher',
            'staff',
        ];
        $genderPool = ['male', 'female', 'male', 'female'];

        // Morning Shift (08:00 - 12:00)
        $morningTimes = [
            [8, 15], [8, 30], [8, 45], [9, 10], [9, 30], [9, 50], [10, 15], [10, 40], [11, 0], [11, 25], [11, 50]
        ];
        foreach ($morningTimes as $idx => [$h, $m]) {
            foreach ($libraries as $lIdx => $lib) {
                $uType = $patronPool[($idx + $lIdx * 3) % count($patronPool)];
                $count = ($uType === 'student') ? rand(2, 6) : rand(1, 2);
                $entryDt = (clone $today)->setHour($h)->setMinute($m)->setSecond(rand(0, 59));
                $todayRows[] = [
                    'slug' => (string) \Illuminate\Support\Str::uuid(),
                    'library_id' => $lib->id,
                    'user_type' => $uType,
                    'gender' => $genderPool[($idx + $lIdx) % count($genderPool)],
                    'visitor_count' => $count,
                    'staff_id' => $staffMembers[($idx + $lIdx) % count($staffMembers)]->id,
                    'entry_at' => $entryDt,
                    'created_at' => $entryDt,
                    'updated_at' => $entryDt,
                ];
            }
        }

        // Afternoon Shift (12:00 - 18:00) - Peak volume
        $afternoonTimes = [
            [12, 15], [12, 45], [13, 10], [13, 35], [14, 0], [14, 20], [14, 45], [15, 10], [15, 30], [15, 55], [16, 20], [16, 50], [17, 15], [17, 45]
        ];
        foreach ($afternoonTimes as $idx => [$h, $m]) {
            foreach ($libraries as $lIdx => $lib) {
                $uType = $patronPool[($idx * 2 + $lIdx) % count($patronPool)];
                $count = ($uType === 'student') ? rand(3, 8) : rand(1, 3);
                $entryDt = (clone $today)->setHour($h)->setMinute($m)->setSecond(rand(0, 59));
                $todayRows[] = [
                    'slug' => (string) \Illuminate\Support\Str::uuid(),
                    'library_id' => $lib->id,
                    'user_type' => $uType,
                    'gender' => $genderPool[($idx + $lIdx + 1) % count($genderPool)],
                    'visitor_count' => $count,
                    'staff_id' => $staffMembers[($idx * 2 + $lIdx) % count($staffMembers)]->id,
                    'entry_at' => $entryDt,
                    'created_at' => $entryDt,
                    'updated_at' => $entryDt,
                ];
            }
        }

        // Night Shift (18:00 - 23:00)
        $nightTimes = [
            [18, 15], [18, 45], [19, 15], [19, 45], [20, 15], [20, 45], [21, 15], [21, 45], [22, 10], [22, 40]
        ];
        foreach ($nightTimes as $idx => [$h, $m]) {
            foreach ($libraries as $lIdx => $lib) {
                $uType = $patronPool[($idx + $lIdx) % count($patronPool)];
                $count = ($uType === 'student') ? rand(2, 5) : 1;
                $entryDt = (clone $today)->setHour($h)->setMinute($m)->setSecond(rand(0, 59));
                $todayRows[] = [
                    'slug' => (string) \Illuminate\Support\Str::uuid(),
                    'library_id' => $lib->id,
                    'user_type' => $uType,
                    'gender' => $genderPool[($idx + $lIdx) % count($genderPool)],
                    'visitor_count' => $count,
                    'staff_id' => $staffMembers[($idx + $lIdx + 2) % count($staffMembers)]->id,
                    'entry_at' => $entryDt,
                    'created_at' => $entryDt,
                    'updated_at' => $entryDt,
                ];
            }
        }

        foreach (array_chunk($todayRows, 100) as $chunk) {
            LibraryAttendance::insert($chunk);
        }

        // 7.5. Seed Realistic Longitudinal Historical Attendance Data for Multi-Month, Multi-Year, and Day Analytics
        $historicalRows = [];
        $sampleMonths = [
            ['year' => 2025, 'month' => 9],
            ['year' => 2025, 'month' => 10],
            ['year' => 2025, 'month' => 11],
            ['year' => 2025, 'month' => 12],
            ['year' => 2026, 'month' => 1],
            ['year' => 2026, 'month' => 2],
            ['year' => 2026, 'month' => 3],
            ['year' => 2026, 'month' => 4],
            ['year' => 2026, 'month' => 5],
            ['year' => 2026, 'month' => 6],
            ['year' => 2026, 'month' => 9],
            ['year' => 2026, 'month' => 10],
            ['year' => 2026, 'month' => 11],
        ];

        foreach ($sampleMonths as $mInfo) {
            $y = $mInfo['year'];
            $m = $mInfo['month'];
            $maxDay = ($y === 2026 && $m === 11) ? 18 : Carbon::create($y, $m, 1)->daysInMonth;

            for ($d = 1; $d <= min(28, $maxDay); $d += 2) {
                $cDate = Carbon::create($y, $m, $d);
                $dayOfWeek = $cDate->dayOfWeekIso; // 1 (Mon) to 7 (Sun)
                $weekOccurrence = (int) ceil($d / 7); // 1st, 2nd, 3rd, 4th

                // Operational foot-traffic modeling: 2nd Monday & 3rd Tuesday surge
                $multiplier = 1.0;
                if ($dayOfWeek === 1 || $dayOfWeek === 2) $multiplier += 0.40;
                if ($dayOfWeek === 1 && $weekOccurrence === 2) $multiplier += 0.35; // 2nd Monday surge
                if ($dayOfWeek === 2 && $weekOccurrence === 3) $multiplier += 0.30; // 3rd Tuesday surge
                if ($dayOfWeek >= 6) $multiplier -= 0.35; // Weekend lower volume

                $mCount = (int) max(1, round(rand(2, 6) * $multiplier));
                $aCount = (int) max(2, round(rand(4, 9) * $multiplier));
                $nCount = (int) max(1, round(rand(1, 4) * $multiplier));

                $hourEntries = [
                    [9, 15, $mCount, 'morning'],
                    [10, 45, $mCount + 1, 'morning'],
                    [14, 20, $aCount + 2, 'afternoon'],
                    [16, 10, $aCount, 'afternoon'],
                    [19, 30, $nCount, 'night'],
                ];

                foreach ($hourEntries as $hEntry) {
                    $lib = $libraries[($d + $hEntry[0]) % count($libraries)];
                    $staff = $staffMembers[($d + $hEntry[0]) % count($staffMembers)];
                    $uType = $patronPool[($d + $hEntry[0] + $hEntry[1]) % count($patronPool)];
                    $g = $genderPool[($d + $hEntry[1]) % count($genderPool)];
                    $entryDt = Carbon::create($y, $m, $d, $hEntry[0], $hEntry[1], 0);

                    $historicalRows[] = [
                        'slug' => (string) \Illuminate\Support\Str::uuid(),
                        'library_id' => $lib->id,
                        'user_type' => $uType,
                        'gender' => $g,
                        'visitor_count' => max(1, $hEntry[2]),
                        'staff_id' => $staff->id,
                        'entry_at' => $entryDt,
                        'created_at' => $entryDt,
                        'updated_at' => $entryDt,
                    ];
                }
            }
        }

        foreach (array_chunk($historicalRows, 150) as $chunk) {
            LibraryAttendance::insert($chunk);
        }

        // 8. Seed Default Shifts (Day & Night) with Ethiopian Local Time
        $dayShift = Shift::updateOrCreate(
            ['code' => 'day'],
            [
                'name' => 'Day Shift',
                'name_am' => 'የቀን ፈረቃ',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'ethiopian_start_time' => '02:00 ጠዋት',
                'ethiopian_end_time' => '11:00 ከቀኑ',
                'description' => 'Regular daytime library shift from 02:00 morning to 11:00 afternoon Ethiopian time.',
                'is_active' => true,
            ]
        );

        $nightShift = Shift::updateOrCreate(
            ['code' => 'night'],
            [
                'name' => 'Night Shift',
                'name_am' => 'የማታ ፈረቃ',
                'start_time' => '17:00',
                'end_time' => '05:00',
                'ethiopian_start_time' => '11:00 ከቀኑ',
                'ethiopian_end_time' => '11:00 ከሌሊቱ',
                'description' => 'Overnight library duty shift from 11:00 afternoon to 11:00 night/dawn Ethiopian time (05:00 AM standard).',
                'is_active' => true,
            ]
        );

        // 8.5. Seed initial user-inserted Ethiopian Holiday (Day 12 Hidar Zion for 2019)
        EthiopianHoliday::updateOrCreate(
            [
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
                'ethiopian_day' => 12,
                'library_id' => null, // applies across all branches
            ],
            [
                'name' => 'Hidar Zion',
                'name_am' => 'ኅዳር ጽዮን',
                'description' => 'Major historical and cultural commemoration.',
                'created_by' => $superAdminUser->id,
            ]
        );

        // 9. Seed Unified Shift Groups (4 Groups: each has at least 4 from each library branch)
        $groupA = ShiftGroup::updateOrCreate(
            ['name' => 'Group A'],
            [
                'library_id' => null, // Global: belongs to all library branches
                'description' => 'Primary university library duty group across all branches (Techno, Social, Natural).',
                'is_active' => true,
            ]
        );
        $groupA->users()->sync([
            $technoAttendants[0]->id, $technoAttendants[1]->id, $technoAttendants[2]->id, $technoAttendants[3]->id,
            $socialAttendants[0]->id, $socialAttendants[1]->id, $socialAttendants[2]->id, $socialAttendants[3]->id,
            $naturalAttendants[0]->id, $naturalAttendants[1]->id, $naturalAttendants[2]->id, $naturalAttendants[3]->id,
        ]);

        $groupB = ShiftGroup::updateOrCreate(
            ['name' => 'Group B'],
            [
                'library_id' => null, // Global
                'description' => 'Secondary duty group across all branches (Techno, Social, Natural).',
                'is_active' => true,
            ]
        );
        $groupB->users()->sync([
            $technoAttendants[4]->id, $technoAttendants[5]->id, $technoAttendants[6]->id, $technoAttendants[7]->id,
            $socialAttendants[4]->id, $socialAttendants[5]->id, $socialAttendants[6]->id, $socialAttendants[7]->id,
            $naturalAttendants[4]->id, $naturalAttendants[5]->id, $naturalAttendants[6]->id, $naturalAttendants[7]->id,
        ]);

        $groupC = ShiftGroup::updateOrCreate(
            ['name' => 'Group C'],
            [
                'library_id' => null, // Global
                'description' => 'Rotation duty group across all branches (Techno, Social, Natural).',
                'is_active' => true,
            ]
        );
        $groupC->users()->sync([
            $technoAttendants[8]->id, $technoAttendants[9]->id, $technoAttendants[10]->id, $technoAttendants[11]->id,
            $socialAttendants[8]->id, $socialAttendants[9]->id, $socialAttendants[10]->id, $socialAttendants[11]->id,
            $naturalAttendants[8]->id, $naturalAttendants[9]->id, $naturalAttendants[10]->id, $naturalAttendants[11]->id,
        ]);

        $groupD = ShiftGroup::updateOrCreate(
            ['name' => 'Group D'],
            [
                'library_id' => null, // Global
                'description' => 'Archive and weekend duty group across all branches (Techno, Social, Natural).',
                'is_active' => true,
            ]
        );
        $groupD->users()->sync([
            $technoAttendants[12]->id, $technoAttendants[13]->id, $technoAttendants[14]->id, $technoAttendants[15]->id,
            $socialAttendants[12]->id, $socialAttendants[13]->id, $socialAttendants[14]->id, $socialAttendants[15]->id,
            $naturalAttendants[12]->id, $naturalAttendants[13]->id, $naturalAttendants[14]->id, $naturalAttendants[15]->id,
        ]);

        // 10. Seed Sample Ethiopian Calendar Schedules across Groups (same across all library branches)
        // Group A: Day duty on 1, 5, 9, 13, 17, 21, 25, 29; Night duty on 3, 7, 11, 15, 19, 23, 27
        GroupShiftSchedule::updateOrCreate(
            [
                'shift_group_id' => $groupA->id,
                'shift_id' => $dayShift->id,
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
            ],
            [
                'library_id' => null,
                'days' => [1, 5, 9, 13, 17, 21, 25, 29],
                'status' => 'active',
                'notes' => 'Group A daytime duty (08:00 - 17:00) for Hidar 2019 across all branches',
            ]
        );

        GroupShiftSchedule::updateOrCreate(
            [
                'shift_group_id' => $groupA->id,
                'shift_id' => $nightShift->id,
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
            ],
            [
                'library_id' => null,
                'days' => [3, 7, 11, 15, 19, 23, 27],
                'status' => 'active',
                'notes' => 'Group A overnight duty (17:00 - 05:00) for Hidar 2019 across all branches',
            ]
        );

        // Group B: Night duty on 1, 5, 9, 13, 17, 21, 25, 29; Day duty on 3, 7, 11, 15, 19, 23, 27
        GroupShiftSchedule::updateOrCreate(
            [
                'shift_group_id' => $groupB->id,
                'shift_id' => $dayShift->id,
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
            ],
            [
                'library_id' => null,
                'days' => [3, 7, 11, 15, 19, 23, 27],
                'status' => 'active',
                'notes' => 'Group B daytime duty for Hidar 2019 across all branches',
            ]
        );

        GroupShiftSchedule::updateOrCreate(
            [
                'shift_group_id' => $groupB->id,
                'shift_id' => $nightShift->id,
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
            ],
            [
                'library_id' => null,
                'days' => [1, 5, 9, 13, 17, 21, 25, 29],
                'status' => 'active',
                'notes' => 'Group B overnight duty for Hidar 2019 across all branches',
            ]
        );

        // Group C: Day on 2, 6, 10, 14, 18, 22, 26, 30; Night on 4, 8, 16, 20, 24, 28
        GroupShiftSchedule::updateOrCreate(
            [
                'shift_group_id' => $groupC->id,
                'shift_id' => $dayShift->id,
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
            ],
            [
                'library_id' => null,
                'days' => [2, 6, 10, 14, 18, 22, 26, 30],
                'status' => 'active',
                'notes' => 'Group C daytime duty for Hidar 2019 across all branches',
            ]
        );

        GroupShiftSchedule::updateOrCreate(
            [
                'shift_group_id' => $groupC->id,
                'shift_id' => $nightShift->id,
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
            ],
            [
                'library_id' => null,
                'days' => [4, 8, 16, 20, 24, 28], // day 12 is holiday
                'status' => 'active',
                'notes' => 'Group C overnight duty for Hidar 2019 across all branches',
            ]
        );

        // Group D: Night on 2, 6, 10, 14, 18, 22, 26, 30; Day on 4, 8, 16, 20, 24, 28
        GroupShiftSchedule::updateOrCreate(
            [
                'shift_group_id' => $groupD->id,
                'shift_id' => $dayShift->id,
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
            ],
            [
                'library_id' => null,
                'days' => [4, 8, 16, 20, 24, 28], // day 12 is holiday
                'status' => 'active',
                'notes' => 'Group D daytime duty for Hidar 2019 across all branches',
            ]
        );

        GroupShiftSchedule::updateOrCreate(
            [
                'shift_group_id' => $groupD->id,
                'shift_id' => $nightShift->id,
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
            ],
            [
                'library_id' => null,
                'days' => [2, 6, 10, 14, 18, 22, 26, 30],
                'status' => 'active',
                'notes' => 'Group D overnight duty for Hidar 2019 across all branches',
            ]
        );
    }
}
