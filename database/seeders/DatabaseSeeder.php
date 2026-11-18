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

        // Attendant: View library, record attendance, and 2-minute update/delete
        $attendantRole->syncPermissions([
            'libraries.view',
            'attendance.view',
            'attendance.record',
            'attendance.update',
            'attendance.delete',
        ]);

        // 5. Seed Realistic University Library Branches
        $mainLibrary = Library::updateOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Main Central Library',
                'name_am' => 'ዋና ማዕከላዊ ቤተ-መጻሕፍት',
                'slug' => 'main-central-library',
                'location' => 'Main Campus, Administration Block, 2nd Floor',
                'phone' => '+251 111 234501',
                'email' => 'main.library@unilib.edu',
                'description' => 'University central academic repository, directorate office, and general multidisciplinary stacks.',
                'is_active' => true,
            ]
        );

        $sciTechLibrary = Library::updateOrCreate(
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

        $healthLibrary = Library::updateOrCreate(
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

        $lawLibrary = Library::updateOrCreate(
            ['code' => 'LAW'],
            [
                'name' => 'Law & Humanities Library',
                'name_am' => 'የሕግና ማኅበራዊ ሳይንስ ቤተ-መጻሕፍት',
                'slug' => 'law-humanities-library',
                'location' => 'Siddist Kilo Campus, Law School Building, 1st Floor',
                'phone' => '+251 111 234504',
                'email' => 'law.library@unilib.edu',
                'description' => 'Federal law gazettes, international treaties, and human rights journals.',
                'is_active' => true,
            ]
        );

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

        // Branch Manager (Assigned to Main Library: manages reporting for Main Library ONLY)
        $branchManagerMain = User::updateOrCreate(
            ['email' => 'kassahun@unilib.edu'],
            [
                'username' => 'kassahun_manager',
                'name' => 'Kassahun Desta',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 914 567890',
                'library_id' => $mainLibrary->id,
            ]
        );
        $branchManagerMain->syncRoles(['Manager']);

        // Librarian (Assigned to Science & Tech Library)
        $librarianUser = User::updateOrCreate(
            ['email' => 'tigist@unilib.edu'],
            [
                'username' => 'tigist_lib',
                'name' => 'Tigist Alemu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 922 345678',
                'library_id' => $sciTechLibrary->id,
            ]
        );
        $librarianUser->syncRoles(['Librarian']);

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

        // Branch Manager (Assigned to Main Library: manages reporting for Main Library ONLY)
        $branchManagerMain = User::updateOrCreate(
            ['email' => 'kassahun@unilib.edu'],
            [
                'username' => 'kassahun_manager',
                'name' => 'Kassahun Desta',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 914 567890',
                'library_id' => $mainLibrary->id,
            ]
        );
        $branchManagerMain->syncRoles(['Manager']);

        // Librarian (Assigned to Science & Tech Library)
        $librarianUser = User::updateOrCreate(
            ['email' => 'tigist@unilib.edu'],
            [
                'username' => 'tigist_lib',
                'name' => 'Tigist Alemu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 922 345678',
                'library_id' => $sciTechLibrary->id,
            ]
        );
        $librarianUser->syncRoles(['Librarian']);

        // =========================================================================
        // DISTINCT ATTENDANTS SEEDING (Strict 1-group-per-branch assignment, 0 overlap)
        // =========================================================================

        // --- Main Central Library Attendants ---
        // Group A Attendants
        $attendantMain = User::updateOrCreate(
            ['email' => 'yohannes@unilib.edu'],
            [
                'username' => 'yohannes_attendant',
                'name' => 'Yohannes Girma',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 933 456789',
                'library_id' => $mainLibrary->id,
            ]
        );
        $attendantMain->syncRoles(['Attendant']);

        $attendantAlmaz = User::updateOrCreate(
            ['email' => 'almaz@unilib.edu'],
            [
                'username' => 'almaz_attendant',
                'name' => 'Almaz Kebede',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 933 112233',
                'library_id' => $mainLibrary->id,
            ]
        );
        $attendantAlmaz->syncRoles(['Attendant']);

        $attendantDawitM = User::updateOrCreate(
            ['email' => 'dawit.m@unilib.edu'],
            [
                'username' => 'dawit_m_attendant',
                'name' => 'Dawit Mengistu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 933 223344',
                'library_id' => $mainLibrary->id,
            ]
        );
        $attendantDawitM->syncRoles(['Attendant']);

        // Group B Attendants
        $attendantBethlehemT = User::updateOrCreate(
            ['email' => 'bethlehem.t@unilib.edu'],
            [
                'username' => 'bethlehem_t_attendant',
                'name' => 'Bethlehem Tadesse',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 933 334455',
                'library_id' => $mainLibrary->id,
            ]
        );
        $attendantBethlehemT->syncRoles(['Attendant']);

        $attendantHenok = User::updateOrCreate(
            ['email' => 'henok@unilib.edu'],
            [
                'username' => 'henok_attendant',
                'name' => 'Henok Berhanu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 933 445566',
                'library_id' => $mainLibrary->id,
            ]
        );
        $attendantHenok->syncRoles(['Attendant']);

        $attendantSenait = User::updateOrCreate(
            ['email' => 'senait@unilib.edu'],
            [
                'username' => 'senait_attendant',
                'name' => 'Senait Tefera',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 933 556677',
                'library_id' => $mainLibrary->id,
            ]
        );
        $attendantSenait->syncRoles(['Attendant']);

        // Group C Attendants
        $attendantErmias = User::updateOrCreate(
            ['email' => 'ermias@unilib.edu'],
            [
                'username' => 'ermias_attendant',
                'name' => 'Ermias Assefa',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 933 667788',
                'library_id' => $mainLibrary->id,
            ]
        );
        $attendantErmias->syncRoles(['Attendant']);

        $attendantMahlet = User::updateOrCreate(
            ['email' => 'mahlet@unilib.edu'],
            [
                'username' => 'mahlet_attendant',
                'name' => 'Mahlet Desta',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 933 778899',
                'library_id' => $mainLibrary->id,
            ]
        );
        $attendantMahlet->syncRoles(['Attendant']);

        $attendantYared = User::updateOrCreate(
            ['email' => 'yared@unilib.edu'],
            [
                'username' => 'yared_attendant',
                'name' => 'Yared Getachew',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 933 889900',
                'library_id' => $mainLibrary->id,
            ]
        );
        $attendantYared->syncRoles(['Attendant']);

        // --- Science & Technology Library Attendants ---
        // Group A Attendants
        $attendantAbel = User::updateOrCreate(
            ['email' => 'abel@unilib.edu'],
            [
                'username' => 'abel_attendant',
                'name' => 'Abel Hailu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 922 112233',
                'library_id' => $sciTechLibrary->id,
            ]
        );
        $attendantAbel->syncRoles(['Attendant']);

        $attendantHiwot = User::updateOrCreate(
            ['email' => 'hiwot@unilib.edu'],
            [
                'username' => 'hiwot_attendant',
                'name' => 'Hiwot Lemma',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 922 223344',
                'library_id' => $sciTechLibrary->id,
            ]
        );
        $attendantHiwot->syncRoles(['Attendant']);

        // Group B Attendants
        $attendantSamuel = User::updateOrCreate(
            ['email' => 'samuel@unilib.edu'],
            [
                'username' => 'samuel_attendant',
                'name' => 'Samuel Worku',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 922 334455',
                'library_id' => $sciTechLibrary->id,
            ]
        );
        $attendantSamuel->syncRoles(['Attendant']);

        $attendantRahel = User::updateOrCreate(
            ['email' => 'rahel@unilib.edu'],
            [
                'username' => 'rahel_attendant',
                'name' => 'Rahel Bekele',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 922 445566',
                'library_id' => $sciTechLibrary->id,
            ]
        );
        $attendantRahel->syncRoles(['Attendant']);

        // --- Health Sciences Library Attendants ---
        // Group A Attendants
        $attendantHealth = User::updateOrCreate(
            ['email' => 'bethlehem@unilib.edu'],
            [
                'username' => 'bethlehem_attendant',
                'name' => 'Bethlehem Wolde',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 915 678901',
                'library_id' => $healthLibrary->id,
            ]
        );
        $attendantHealth->syncRoles(['Attendant']);

        $attendantDaniel = User::updateOrCreate(
            ['email' => 'daniel@unilib.edu'],
            [
                'username' => 'daniel_attendant',
                'name' => 'Daniel Fikre',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 915 112233',
                'library_id' => $healthLibrary->id,
            ]
        );
        $attendantDaniel->syncRoles(['Attendant']);

        // Group B Attendants
        $attendantYonas = User::updateOrCreate(
            ['email' => 'yonas@unilib.edu'],
            [
                'username' => 'yonas_attendant',
                'name' => 'Yonas Kassa',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 915 223344',
                'library_id' => $healthLibrary->id,
            ]
        );
        $attendantYonas->syncRoles(['Attendant']);

        $attendantSelamG = User::updateOrCreate(
            ['email' => 'selam.g@unilib.edu'],
            [
                'username' => 'selam_g_attendant',
                'name' => 'Selamawit Gashaw',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 915 334455',
                'library_id' => $healthLibrary->id,
            ]
        );
        $attendantSelamG->syncRoles(['Attendant']);

        // --- Law & Humanities Library Attendants ---
        // Group A Attendants
        $attendantLaw = User::updateOrCreate(
            ['email' => 'natnael@unilib.edu'],
            [
                'username' => 'natnael_attendant',
                'name' => 'Natnael Solomon',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 999 012345',
                'library_id' => $lawLibrary->id,
            ]
        );
        $attendantLaw->syncRoles(['Attendant']);

        $attendantKalkidan = User::updateOrCreate(
            ['email' => 'kalkidan@unilib.edu'],
            [
                'username' => 'kalkidan_attendant',
                'name' => 'Kalkidan Abebe',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 999 112233',
                'library_id' => $lawLibrary->id,
            ]
        );
        $attendantKalkidan->syncRoles(['Attendant']);

        // Group B Attendants
        $attendantBiniyam = User::updateOrCreate(
            ['email' => 'biniyam@unilib.edu'],
            [
                'username' => 'biniyam_attendant',
                'name' => 'Biniyam Tesfaye',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 999 223344',
                'library_id' => $lawLibrary->id,
            ]
        );
        $attendantBiniyam->syncRoles(['Attendant']);

        $attendantMeron = User::updateOrCreate(
            ['email' => 'meron@unilib.edu'],
            [
                'username' => 'meron_attendant',
                'name' => 'Meron Haile',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 999 334455',
                'library_id' => $lawLibrary->id,
            ]
        );
        $attendantMeron->syncRoles(['Attendant']);

        // Floating General Attendant (across branches)
        $attendantGeneral = User::updateOrCreate(
            ['email' => 'sara@unilib.edu'],
            [
                'username' => 'sara_attendant',
                'name' => 'Sara Tadesse',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 944 567890',
                'library_id' => null, // Floating General Attendant
            ]
        );
        $attendantGeneral->syncRoles(['Attendant']);

        // 7. Seed Attendance Records for Today and Past Days
        $staffMembers = [
            $attendantMain, $attendantAlmaz, $attendantDawitM,
            $attendantBethlehemT, $attendantHenok, $attendantSenait,
            $attendantAbel, $attendantSamuel, $attendantHealth,
            $attendantDaniel, $attendantLaw, $attendantKalkidan,
            $attendantGeneral, $librarianUser, $superAdminUser,
        ];
        $libraries = [$mainLibrary, $sciTechLibrary, $healthLibrary, $lawLibrary];
        $userTypes = ['student', 'teacher', 'staff'];
        $genders = ['male', 'female'];

        $today = Carbon::today();

        // Seed 18 attendance records across branches
        for ($i = 0; $i < 24; $i++) {
            $lib = $libraries[$i % count($libraries)];
            $staff = $staffMembers[$i % count($staffMembers)];
            $type = $userTypes[$i % count($userTypes)];
            $gender = $genders[$i % 2];
            $count = ($i % 5 === 0) ? ($i % 4 + 2) : 1;

            // Minutes ago: mix of recent (< 2 min) and older entries
            $minutesAgo = ($i === 0) ? 1 : ($i * 12 + 5);
            $entryTime = (clone $today)->addHours(8)->addMinutes($minutesAgo);

            LibraryAttendance::create([
                'library_id' => $lib->id,
                'user_type' => $type,
                'gender' => $gender,
                'visitor_count' => $count,
                'staff_id' => $staff->id,
                'entry_at' => $entryTime,
            ]);
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

        // 9. Seed Unified Shift Groups (Belongs to ALL library branches by default)
        // Staff belong to their respective library branch, and one staff member cannot be in multiple groups.
        $groupA = ShiftGroup::updateOrCreate(
            ['name' => 'Group A'],
            [
                'library_id' => null, // Global: belongs to all library branches
                'description' => 'Primary university library duty group across all branches.',
                'is_active' => true,
            ]
        );
        $groupA->users()->sync([
            $attendantMain->id,     // Main Central Library
            $attendantAbel->id,     // Science & Technology Library
            $attendantHealth->id,   // Health Sciences Library
            $attendantLaw->id,      // Law & Humanities Library
            $attendantAlmaz->id,    // Main Central Library
        ]);

        $groupB = ShiftGroup::updateOrCreate(
            ['name' => 'Group B'],
            [
                'library_id' => null, // Global
                'description' => 'Evening and secondary duty group across all branches.',
                'is_active' => true,
            ]
        );
        $groupB->users()->sync([
            $attendantBethlehemT->id, // Main Central Library
            $attendantSamuel->id,     // Science & Technology Library
            $attendantYonas->id,      // Health Sciences Library
            $attendantKalkidan->id,   // Law & Humanities Library
            $attendantDawitM->id,     // Main Central Library
        ]);

        $groupC = ShiftGroup::updateOrCreate(
            ['name' => 'Group C'],
            [
                'library_id' => null, // Global
                'description' => 'Weekend and rotation duty group across all branches.',
                'is_active' => true,
            ]
        );
        $groupC->users()->sync([
            $attendantErmias->id,  // Main Central Library
            $attendantHiwot->id,   // Science & Technology Library
            $attendantDaniel->id,  // Health Sciences Library
            $attendantBiniyam->id, // Law & Humanities Library
            $attendantHenok->id,   // Main Central Library
        ]);

        $groupD = ShiftGroup::updateOrCreate(
            ['name' => 'Group D'],
            [
                'library_id' => null, // Global
                'description' => 'Archive, support & reserve duty group across all branches.',
                'is_active' => true,
            ]
        );
        $groupD->users()->sync([
            $attendantSenait->id,  // Main Central Library
            $attendantRahel->id,   // Science & Technology Library
            $attendantSelamG->id,  // Health Sciences Library
            $attendantMeron->id,   // Law & Humanities Library
            $attendantMahlet->id,  // Main Central Library
            $attendantYared->id,   // Main Central Library
        ]);

        // 10. Seed Sample Ethiopian Calendar Schedules across Groups (same across all library branches)
        // Group A: Day duty on 1, 6, 7, 13, 19, 24, 25, 30; Night duty on 3, 4, 9, 10, 15, 16, 21, 22, 27, 28
        // Non-consecutive: Day -> Off -> Night -> Off
        GroupShiftSchedule::updateOrCreate(
            [
                'shift_group_id' => $groupA->id,
                'shift_id' => $dayShift->id,
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
            ],
            [
                'library_id' => null, // Applies to all branches
                'days' => [1, 6, 7, 13, 18, 19, 24, 25, 30], // Day 12 excluded (Hidar Zion Holiday)
                'status' => 'active',
                'notes' => 'Group A daytime duty for Hidar 2019 across all branches',
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
                'days' => [3, 4, 9, 10, 15, 16, 21, 22, 27, 28],
                'status' => 'active',
                'notes' => 'Group A overnight duty (up to 11:00 Ethiopian time) for Hidar 2019 across all branches',
            ]
        );

        // Group B: Day on 4, 5, 10, 11, 16, 17, 22, 23, 28, 29; Night on 1, 2, 7, 8, 13, 14, 19, 20, 25, 26
        GroupShiftSchedule::updateOrCreate(
            [
                'shift_group_id' => $groupB->id,
                'shift_id' => $dayShift->id,
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
            ],
            [
                'library_id' => null,
                'days' => [4, 5, 10, 11, 16, 17, 22, 23, 28, 29],
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
                'days' => [1, 2, 7, 8, 13, 14, 19, 20, 25, 26],
                'status' => 'active',
                'notes' => 'Group B overnight duty for Hidar 2019 across all branches',
            ]
        );

        // Group C: Day on 2, 3, 8, 9, 14, 15, 20, 21, 26, 27; Night on 5, 6, 11, 17, 23, 24, 29
        GroupShiftSchedule::updateOrCreate(
            [
                'shift_group_id' => $groupC->id,
                'shift_id' => $dayShift->id,
                'ethiopian_year' => 2019,
                'ethiopian_month' => 3,
            ],
            [
                'library_id' => null,
                'days' => [2, 3, 8, 9, 14, 15, 20, 21, 26, 27],
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
                'days' => [5, 6, 11, 17, 23, 24, 29],
                'status' => 'active',
                'notes' => 'Group C overnight duty for Hidar 2019 across all branches',
            ]
        );
    }
}
