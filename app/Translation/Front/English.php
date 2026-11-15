<?php

namespace App\Translation\Front;

class English
{
    public static function get(): array
    {
        return [
            // App & General
            'app.name' => 'UniLibrary Portal',
            'app.subtitle' => 'University Library Management System',
            'app.tagline' => 'Academic Excellence & Automated Duty Enforcement',
            'app.welcome' => 'Welcome to UniLibrary Portal',
            'common.actions' => 'Actions',
            'common.save' => 'Save Changes',
            'common.cancel' => 'Cancel',
            'common.delete' => 'Delete',
            'common.edit' => 'Edit',
            'common.view' => 'View',
            'common.search' => 'Search records...',
            'common.filter' => 'Filter',
            'common.status' => 'Status',
            'common.all' => 'All',
            'common.active' => 'Active',
            'common.inactive' => 'Inactive',
            'common.suspended' => 'Suspended',
            'common.loading' => 'Loading...',
            'common.refresh' => 'Refresh',
            'common.confirm_delete' => 'Are you sure you want to delete this record? This action cannot be undone.',
            'common.no_records' => 'No records found.',
            'common.success' => 'Success',
            'common.error' => 'Error',
            'common.close' => 'Close',
            'common.create' => 'Create',
            'common.update' => 'Update',

            // Navigation
            'nav.dashboard' => 'Overview Dashboard',
            'nav.group_general' => 'General',
            'nav.group_library' => 'Library Operations',
            'nav.group_admin' => 'Administration & Security',
            'nav.users' => 'User Management',
            'nav.roles' => 'Roles & RBAC Matrix',
            'nav.schedules' => 'Duty Shift Schedules',
            'nav.my_turns' => 'My Duty Turns',
            'nav.books' => 'Book Catalog',
            'nav.circulation' => 'Circulation & Loans',

            // Duty Status
            'duty.on_duty' => 'On Duty Today',
            'duty.off_duty' => 'Off Duty',
            'duty.admin_override' => 'Admin Override Active',
            'duty.restricted_notice' => 'Task Gated by Duty Turn',
            'duty.next_turn' => 'Next Scheduled Turn',
            'duty.active_shift' => 'Active Duty Shift',
            'duty.shifts_today' => 'Today\'s Duty Shifts',
            'duty.task_circulation' => 'Book Circulation & Issue',
            'duty.task_audit' => 'Inventory Audit & Barcode Scan',
            'duty.task_fine' => 'Overdue Fine Waiver',
            'duty.task_archive' => 'Rare Archive Access',

            // Users
            'users.title' => 'User Accounts & Access',
            'users.subtitle' => 'Manage user authentication, Spatie role assignments, and status',
            'users.add' => 'Add New User',
            'users.edit' => 'Edit User',
            'users.name' => 'Full Name',
            'users.username' => 'Unique Username',
            'users.email' => 'Email Address',
            'users.role' => 'Assigned Role',
            'users.password' => 'Password',
            'users.password_hint' => 'Leave blank to retain current password',
            'users.phone' => 'Phone Number',
            'users.created' => 'User created successfully.',
            'users.updated' => 'User updated successfully.',
            'users.deleted' => 'User deleted successfully.',

            // Roles
            'roles.title' => 'Roles & RBAC Matrix',
            'roles.subtitle' => 'Hierarchical authorization powered by Spatie Laravel Permission',
            'roles.create' => 'Create Role',
            'roles.edit' => 'Edit Role',
            'roles.name' => 'Role Name',
            'roles.permissions' => 'Permissions',
            'roles.catalog' => 'Roles Catalog',
            'roles.matrix' => 'Permission Matrix',
            'roles.created' => 'Role created successfully.',
            'roles.updated' => 'Role updated successfully.',

            // Books & Circulation
            'books.title' => 'University Library Catalog',
            'books.add' => 'Add Book',
            'books.isbn' => 'ISBN',
            'books.book_title' => 'Book Title',
            'books.author' => 'Author',
            'books.category' => 'Category',
            'books.location' => 'Shelf Location',
            'books.copies' => 'Copies Available',
            'circulation.title' => 'Circulation & Book Loans',
            'circulation.issue' => 'Issue Book',
            'circulation.return' => 'Return Book',
            'circulation.borrower' => 'Borrower (Student)',
            'circulation.due_date' => 'Due Date',
            'circulation.issued_date' => 'Issued Date',
            'circulation.status_borrowed' => 'Borrowed',
            'circulation.status_returned' => 'Returned',
            'circulation.status_overdue' => 'Overdue',
        ];
    }
}
