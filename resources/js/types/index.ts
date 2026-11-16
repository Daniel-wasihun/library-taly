// Core TypeScript types for UniLibrary System

export interface Library {
    id: number;
    name: string;
    name_am: string | null;
    code: string;
    slug: string;
    location: string | null;
    phone: string | null;
    email: string | null;
    description: string | null;
    is_active: boolean;
    users_count?: number;
    books_count?: number;
    active_loans_count?: number;
    created_at?: string;
}

export interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string | null;
    status: 'active' | 'inactive' | 'suspended';
    library_id: number | null;
    library?: {
        id: number;
        name: string;
        name_am: string | null;
        code: string;
    } | null;
    can_manage_all_libraries?: boolean;
    roles: (string | { id: number; name: string })[];
    permissions?: (string | { id: number; name: string })[];
    direct_permissions?: string[];
    all_permissions?: string[];
    is_admin: boolean;
    duty_status?: DutyStatus;
    created_at?: string;
}

export interface DutyStatus {
    has_active_shift_today: boolean;
    circulation_authorized_today: boolean;
    circulation_reason: string;
    next_turn: string | null;
    today_shifts: TodayShift[];
}

export interface TodayShift {
    id: number;
    task_key: string;
    task_name: string;
    start_time: string;
    end_time: string;
}

export interface DutySchedule {
    id: number;
    user_id: number;
    library_id?: number | null;
    task_key: string;
    task_name?: string;
    shift_date: string;
    start_time: string;
    end_time: string;
    status: 'scheduled' | 'active' | 'completed' | 'cancelled';
    notes: string | null;
    user?: Partial<User>;
    library?: Partial<Library>;
}

export interface Book {
    id: number;
    library_id?: number | null;
    title: string;
    isbn: string;
    author: string;
    category: string;
    shelf_location: string | null;
    total_copies: number;
    available_copies: number;
    library?: Partial<Library>;
    created_at: string;
}

export interface BookLoan {
    id: number;
    library_id?: number | null;
    book_id: number;
    member_id: number;
    issued_by_user_id: number | null;
    issued_date: string;
    due_date: string;
    returned_date: string | null;
    status: 'borrowed' | 'returned' | 'overdue';
    notes: string | null;
    book?: Partial<Book>;
    member?: Partial<User>;
    issued_by?: Partial<User>;
    library?: Partial<Library>;
}

export interface DashboardStats {
    total_books: number;
    total_copies: number;
    available_copies: number;
    active_loans: number;
    overdue_loans: number;
    total_members: number;
    on_duty_staff_today: OnDutyEntry[];
}

export interface OnDutyEntry {
    user_id: number;
    user_name: string;
    user_username: string;
    task_key: string;
    task_name: string;
    start_time: string;
    end_time: string;
}

export interface ApiRole {
    id: number;
    name: string;
    permissions: Permission[];
}

export interface Permission {
    id: number;
    name: string;
}

export interface Pagination {
    current_page: number;
    last_page: number;
    total: number;
    per_page?: number;
}

export interface ApiResponse<T> {
    success: boolean;
    message?: string;
    data?: T;
    pagination?: Pagination;
}

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface Toast {
    id: string;
    type: ToastType;
    message: string;
}
