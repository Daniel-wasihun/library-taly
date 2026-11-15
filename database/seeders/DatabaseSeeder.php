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
        // 1. Create Spatie Permissions
        $permissions = [
            'users.view',
            'users.manage',
            'schedules.view',
            'schedules.manage',
            'books.view',
            'books.manage',
            'circulation.view',
            'circulation.issue',
            'circulation.return',
        ];

        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm]);
        }

        // 2. Create Spatie Roles
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $headLibrarianRole = Role::firstOrCreate(['name' => 'Head Librarian']);
        $deskLibrarianRole = Role::firstOrCreate(['name' => 'Desk Librarian']);
        $studentRole = Role::firstOrCreate(['name' => 'Student Member']);

        $superAdminRole->syncPermissions(Permission::all());
        $headLibrarianRole->syncPermissions(Permission::all());

        $deskLibrarianRole->syncPermissions([
            'books.view',
            'circulation.view',
            'circulation.issue',
            'circulation.return',
            'schedules.view',
        ]);

        $studentRole->syncPermissions([
            'books.view',
        ]);

        // 3. Create Key Demo Users
        // A) Super Admin / Library Director
        $admin = User::firstOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Dr. Abebe Bekele (Library Director)',
                'email' => 'admin@unilib.edu',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'phone' => '+251 911 234567',
            ]
        );
        $admin->syncRoles(['Super Admin']);

        // B) On-Duty Librarian (Scheduled for today!)
        $onDutyLibrarian = User::firstOrCreate(
            ['username' => 'tigist_duty'],
            [
                'name' => 'Tigist Alemu (On-Duty Librarian)',
                'email' => 'tigist@unilib.edu',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'phone' => '+251 922 345678',
            ]
        );
        $onDutyLibrarian->syncRoles(['Desk Librarian']);

        // C) Off-Duty Librarian (NOT scheduled for today)
        $offDutyLibrarian = User::firstOrCreate(
            ['username' => 'yohannes_offduty'],
            [
                'name' => 'Yohannes Girma (Off-Duty Librarian)',
                'email' => 'yohannes@unilib.edu',
                'password' => Hash::make('password123'),
                'status' => 'active',
                'phone' => '+251 933 456789',
            ]
        );
        $offDutyLibrarian->syncRoles(['Desk Librarian']);

        // D) Students / Library Members
        $student1 = User::firstOrCreate(
            ['username' => 'sara_student'],
            [
                'name' => 'Sara Tadesse (CS Senior)',
                'email' => 'sara@student.unilib.edu',
                'password' => Hash::make('password123'),
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
                'password' => Hash::make('password123'),
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
                'password' => Hash::make('password123'),
                'status' => 'active',
                'phone' => '+251 966 789012',
            ]
        );
        $student3->syncRoles(['Student Member']);

        // 4. Create Duty Schedules (Turns)
        $today = Carbon::today()->toDateString();
        $tomorrow = Carbon::tomorrow()->toDateString();
        $threeDaysLater = Carbon::today()->addDays(3)->toDateString();
        $nextWeek = Carbon::today()->addDays(7)->toDateString();

        // Tigist is on duty TODAY for book circulation & issue
        DutySchedule::create([
            'user_id' => $onDutyLibrarian->id,
            'task_key' => 'book_circulation',
            'shift_date' => $today,
            'start_time' => '08:00:00',
            'end_time' => '17:30:00',
            'status' => 'active',
            'notes' => 'Main Circulation Desk - Morning & Afternoon Shift',
        ]);

        // Tigist is also scheduled for inventory audit tomorrow
        DutySchedule::create([
            'user_id' => $onDutyLibrarian->id,
            'task_key' => 'inventory_audit',
            'shift_date' => $tomorrow,
            'start_time' => '09:00:00',
            'end_time' => '13:00:00',
            'status' => 'scheduled',
            'notes' => 'Section B Book Stack Audit',
        ]);

        // Yohannes is NOT on duty today; his turn is 3 days from now!
        DutySchedule::create([
            'user_id' => $offDutyLibrarian->id,
            'task_key' => 'book_circulation',
            'shift_date' => $threeDaysLater,
            'start_time' => '08:30:00',
            'end_time' => '17:00:00',
            'status' => 'scheduled',
            'notes' => 'Upcoming Main Desk Turn',
        ]);

        DutySchedule::create([
            'user_id' => $offDutyLibrarian->id,
            'task_key' => 'fine_waiver',
            'shift_date' => $nextWeek,
            'start_time' => '10:00:00',
            'end_time' => '16:00:00',
            'status' => 'scheduled',
            'notes' => 'Overdue Review Desk',
        ]);

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
        ];

        $createdBooks = [];
        foreach ($books as $bookData) {
            $createdBooks[] = Book::create($bookData);
        }

        // 6. Create Initial Circulation Records
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
    }
}
