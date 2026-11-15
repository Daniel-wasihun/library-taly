<?php

namespace Database\Seeders;

use App\Models\Book;
use App\Models\BookLoan;
use App\Models\DutySchedule;
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

            // Book Catalog
            'books.view',
            'books.create',
            'books.edit',
            'books.delete',

            // Circulation & Loans
            'circulation.view',
            'circulation.issue',
            'circulation.return',

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
            'users.view',
            'users.create',
            'users.edit',
            'roles.view',
            'books.view',
            'books.create',
            'books.edit',
            'books.delete',
            'circulation.view',
            'circulation.issue',
            'circulation.return',
            'schedules.view',
            'schedules.create',
            'schedules.delete',
            'reports.view',
            'reports.export',
        ]);

        $deskLibrarianRole->syncPermissions([
            'books.view',
            'circulation.view',
            'circulation.issue',
            'circulation.return',
            'schedules.view',
        ]);

        $catalogerRole->syncPermissions([
            'books.view',
            'books.create',
            'books.edit',
            'reports.view',
        ]);

        $archiveAssistantRole->syncPermissions([
            'books.view',
            'circulation.view',
            'reports.view',
        ]);

        $facultyRole->syncPermissions([
            'books.view',
            'circulation.view',
        ]);

        $studentRole->syncPermissions([
            'books.view',
        ]);

        // 3. Seed Realistic Users
        $defaultPassword = Hash::make('password123');

        // Super Admin / Library Director
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Dr. Abebe Bekele',
                'email' => 'admin@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 911 234567',
            ]
        );
        $admin->syncRoles(['Super Admin']);

        // Head Librarian
        $headLibrarian = User::firstOrCreate(
            ['username' => 'selam_head'],
            [
                'name' => 'Selamawit Tadesse',
                'email' => 'selamawit@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 912 345678',
            ]
        );
        $headLibrarian->syncRoles(['Head Librarian']);

        // On-Duty Desk Librarian (Scheduled for today)
        $onDutyLibrarian = User::firstOrCreate(
            ['username' => 'tigist_duty'],
            [
                'name' => 'Tigist Alemu',
                'email' => 'tigist@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 922 345678',
            ]
        );
        $onDutyLibrarian->syncRoles(['Desk Librarian']);
        // Direct override example: give Tigist direct report viewing privilege
        $onDutyLibrarian->syncPermissions(['reports.view']);

        // Off-Duty Desk Librarian (Scheduled 3 days later)
        $offDutyLibrarian = User::firstOrCreate(
            ['username' => 'yohannes_offduty'],
            [
                'name' => 'Yohannes Girma',
                'email' => 'yohannes@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 933 456789',
            ]
        );
        $offDutyLibrarian->syncRoles(['Desk Librarian']);

        // Cataloging Specialist
        $cataloger = User::firstOrCreate(
            ['username' => 'kassahun_cat'],
            [
                'name' => 'Kassahun Desta',
                'email' => 'kassahun@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 914 567890',
            ]
        );
        $cataloger->syncRoles(['Cataloging Specialist']);

        // Archive Assistant
        $archiveAssistant = User::firstOrCreate(
            ['username' => 'bethlehem_arch'],
            [
                'name' => 'Bethlehem Wolde',
                'email' => 'bethlehem@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 915 678901',
            ]
        );
        $archiveAssistant->syncRoles(['Archive Assistant']);

        // Faculty Researchers
        $faculty1 = User::firstOrCreate(
            ['username' => 'dr_tariku'],
            [
                'name' => 'Dr. Tariku Mengesha (Associate Prof.)',
                'email' => 'tariku.m@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 916 789012',
            ]
        );
        $faculty1->syncRoles(['Faculty Researcher']);

        $faculty2 = User::firstOrCreate(
            ['username' => 'prof_almaz'],
            [
                'name' => 'Prof. Almaz Haile (Dept of Medicine)',
                'email' => 'almaz.h@unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 917 890123',
            ]
        );
        $faculty2->syncRoles(['Faculty Researcher']);

        // Student Members
        $student1 = User::firstOrCreate(
            ['username' => 'sara_student'],
            [
                'name' => 'Sara Tadesse (CS Senior)',
                'email' => 'sara@student.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 944 567890',
            ]
        );
        $student1->syncRoles(['Student Member']);

        $student2 = User::firstOrCreate(
            ['username' => 'dawit_student'],
            [
                'name' => 'Dawit Haile (Med 4th Year)',
                'email' => 'dawit@student.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 955 678901',
            ]
        );
        $student2->syncRoles(['Student Member']);

        $student3 = User::firstOrCreate(
            ['username' => 'marta_student'],
            [
                'name' => 'Marta Kebede (Law 3rd Year)',
                'email' => 'marta@student.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 966 789012',
            ]
        );
        $student3->syncRoles(['Student Member']);

        $student4 = User::firstOrCreate(
            ['username' => 'robel_student'],
            [
                'name' => 'Robel Tesfaye (Civil Eng 2nd Year)',
                'email' => 'robel@student.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'active',
                'phone' => '+251 977 890123',
            ]
        );
        $student4->syncRoles(['Student Member']);

        // Inactive User
        $inactiveUser = User::firstOrCreate(
            ['username' => 'bereket_inactive'],
            [
                'name' => 'Bereket Assefa (Graduated)',
                'email' => 'bereket@alumni.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'inactive',
                'phone' => '+251 988 901234',
            ]
        );
        $inactiveUser->syncRoles(['Student Member']);

        // Suspended User (Overdue violation)
        $suspendedUser = User::firstOrCreate(
            ['username' => 'natnael_suspended'],
            [
                'name' => 'Natnael Solomon (Suspended Borrower)',
                'email' => 'natnael@student.unilib.edu',
                'password' => $defaultPassword,
                'status' => 'suspended',
                'phone' => '+251 999 012345',
            ]
        );
        $suspendedUser->syncRoles(['Student Member']);

        // 4. Create Duty Schedules (Turns)
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();
        $twoDaysLater = Carbon::today()->addDays(2)->toDateString();
        $threeDaysLater = Carbon::today()->addDays(3)->toDateString();
        $nextWeek = Carbon::today()->addDays(7)->toDateString();

        // Tigist is on duty TODAY for book circulation & issue
        DutySchedule::firstOrCreate(
            [
                'user_id' => $onDutyLibrarian->id,
                'task_key' => 'book_circulation',
                'shift_date' => $today,
            ],
            [
                'start_time' => '08:00:00',
                'end_time' => '17:30:00',
                'status' => 'active',
                'notes' => 'Main Circulation Desk - Morning & Afternoon Shift',
            ]
        );

        // Selamawit (Head Librarian) is scheduled for today for schedule management
        DutySchedule::firstOrCreate(
            [
                'user_id' => $headLibrarian->id,
                'task_key' => 'schedule_management',
                'shift_date' => $today,
            ],
            [
                'start_time' => '08:30:00',
                'end_time' => '17:00:00',
                'status' => 'active',
                'notes' => 'Head Office Operations & Shift Roster Control',
            ]
        );

        // Cataloger is scheduled for cataloging audit tomorrow
        DutySchedule::firstOrCreate(
            [
                'user_id' => $cataloger->id,
                'task_key' => 'catalog_management',
                'shift_date' => $tomorrow,
            ],
            [
                'start_time' => '09:00:00',
                'end_time' => '15:00:00',
                'status' => 'scheduled',
                'notes' => 'New Acquisitions Cataloging & Barcoding',
            ]
        );

        // Tigist is scheduled for inventory audit in 2 days
        DutySchedule::firstOrCreate(
            [
                'user_id' => $onDutyLibrarian->id,
                'task_key' => 'inventory_audit',
                'shift_date' => $twoDaysLater,
            ],
            [
                'start_time' => '09:00:00',
                'end_time' => '13:00:00',
                'status' => 'scheduled',
                'notes' => 'Section B Book Stack Inventory Audit',
            ]
        );

        // Yohannes is NOT on duty today; his turn is 3 days from now!
        DutySchedule::firstOrCreate(
            [
                'user_id' => $offDutyLibrarian->id,
                'task_key' => 'book_circulation',
                'shift_date' => $threeDaysLater,
            ],
            [
                'start_time' => '08:30:00',
                'end_time' => '17:00:00',
                'status' => 'scheduled',
                'notes' => 'Upcoming Main Desk Turn',
            ]
        );

        // Archive assistant turn next week
        DutySchedule::firstOrCreate(
            [
                'user_id' => $archiveAssistant->id,
                'task_key' => 'archive_access',
                'shift_date' => $nextWeek,
            ],
            [
                'start_time' => '10:00:00',
                'end_time' => '16:00:00',
                'status' => 'scheduled',
                'notes' => 'Historical Manuscripts Repository Shift',
            ]
        );

        // 5. Seed University Library Books
        $books = [
            [
                'title' => 'Introduction to Algorithms (4th Edition)',
                'isbn' => '978-0262046305',
                'author' => 'Thomas H. Cormen, Charles E. Leiserson',
                'category' => 'Computer Science',
                'shelf_location' => 'Floor 2 - Stacks CS-101',
                'total_copies' => 6,
                'available_copies' => 5,
            ],
            [
                'title' => 'Operating System Concepts (10th Edition)',
                'isbn' => '978-1119800361',
                'author' => 'Abraham Silberschatz, Peter B. Galvin',
                'category' => 'Computer Science',
                'shelf_location' => 'Floor 2 - Stacks CS-104',
                'total_copies' => 5,
                'available_copies' => 4,
            ],
            [
                'title' => 'Harrison\'s Principles of Internal Medicine',
                'isbn' => '978-1264268504',
                'author' => 'J. Larry Jameson, Anthony S. Fauci',
                'category' => 'Medicine',
                'shelf_location' => 'Floor 3 - Medical Reference M-20',
                'total_copies' => 4,
                'available_copies' => 3,
            ],
            [
                'title' => 'Robbins & Cotran Pathologic Basis of Disease',
                'isbn' => '978-0323531139',
                'author' => 'Vinay Kumar, Abul K. Abbas',
                'category' => 'Medicine',
                'shelf_location' => 'Floor 3 - Stacks MED-08',
                'total_copies' => 3,
                'available_copies' => 3,
            ],
            [
                'title' => 'Black\'s Law Dictionary (11th Edition)',
                'isbn' => '978-1539229759',
                'author' => 'Bryan A. Garner',
                'category' => 'Law',
                'shelf_location' => 'Floor 1 - Law Archive L-02',
                'total_copies' => 3,
                'available_copies' => 3,
            ],
            [
                'title' => 'Engineering Mechanics: Statics & Dynamics',
                'isbn' => '978-0133915426',
                'author' => 'Russell C. Hibbeler',
                'category' => 'Engineering',
                'shelf_location' => 'Floor 2 - Engineering ENG-40',
                'total_copies' => 5,
                'available_copies' => 5,
            ],
            [
                'title' => 'Design Patterns: Elements of Reusable Object-Oriented Software',
                'isbn' => '978-0201633610',
                'author' => 'Erich Gamma, Richard Helm, Ralph Johnson, John Vlissides',
                'category' => 'Computer Science',
                'shelf_location' => 'Floor 2 - Stacks CS-205',
                'total_copies' => 4,
                'available_copies' => 4,
            ],
            [
                'title' => 'Principles of Neural Science (6th Edition)',
                'isbn' => '978-1259642234',
                'author' => 'Eric R. Kandel, John D. Koester',
                'category' => 'Medicine',
                'shelf_location' => 'Floor 3 - Neuro Science M-45',
                'total_copies' => 2,
                'available_copies' => 2,
            ],
            [
                'title' => 'Ethiopian Constitutional Law and Federalism',
                'isbn' => '978-9994400123',
                'author' => 'Dr. Fasil Nahum',
                'category' => 'Law',
                'shelf_location' => 'Floor 1 - Legal Archives L-14',
                'total_copies' => 4,
                'available_copies' => 3,
            ],
            [
                'title' => 'Artificial Intelligence: A Modern Approach (4th Edition)',
                'isbn' => '978-0134610992',
                'author' => 'Stuart Russell, Peter Norvig',
                'category' => 'Computer Science',
                'shelf_location' => 'Floor 2 - Stacks CS-301',
                'total_copies' => 5,
                'available_copies' => 4,
            ],
        ];

        $createdBooks = [];
        foreach ($books as $bookData) {
            $createdBooks[] = Book::firstOrCreate(['isbn' => $bookData['isbn']], $bookData);
        }

        // 6. Create Circulation Records
        if (BookLoan::count() === 0) {
            BookLoan::create([
                'book_id' => $createdBooks[0]->id,
                'member_id' => $student1->id,
                'issued_by_user_id' => $onDutyLibrarian->id,
                'issued_date' => Carbon::now()->subDays(3),
                'due_date' => Carbon::now()->addDays(11),
                'status' => 'borrowed',
                'notes' => 'CS final year project preparation',
            ]);

            BookLoan::create([
                'book_id' => $createdBooks[1]->id,
                'member_id' => $student2->id,
                'issued_by_user_id' => $onDutyLibrarian->id,
                'issued_date' => Carbon::now()->subDays(5),
                'due_date' => Carbon::now()->addDays(9),
                'status' => 'borrowed',
                'notes' => 'OS laboratory study',
            ]);

            BookLoan::create([
                'book_id' => $createdBooks[2]->id,
                'member_id' => $student3->id,
                'issued_by_user_id' => $onDutyLibrarian->id,
                'issued_date' => Carbon::now()->subDays(18),
                'due_date' => Carbon::now()->subDays(4),
                'returned_date' => Carbon::now()->subDays(2),
                'status' => 'returned',
                'notes' => 'Returned in pristine condition',
            ]);

            BookLoan::create([
                'book_id' => $createdBooks[8]->id,
                'member_id' => $faculty1->id,
                'issued_by_user_id' => $onDutyLibrarian->id,
                'issued_date' => Carbon::now()->subDays(7),
                'due_date' => Carbon::now()->addDays(23),
                'status' => 'borrowed',
                'notes' => 'Faculty research on federal jurisprudence',
            ]);
        }
    }
}
