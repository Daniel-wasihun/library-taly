// Core TypeScript types for UniLibrary System

export interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    phone: string | null;
    status: 'active' | 'inactive' | 'suspended';
    roles: string[];
    permissions: string[];
    is_admin: boolean;
    duty_status: DutyStatus;
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
    task_key: string;
    task_name?: string;
    shift_date: string;
    start_time: string;
    end_time: string;
    status: 'scheduled' | 'active' | 'completed' | 'cancelled';
    notes: string | null;
    user?: Partial<User>;
}

export interface Book {
    id: number;
    title: string;
    isbn: string;
    author: string;
    category: string;
    shelf_location: string | null;
    total_copies: number;
    available_copies: number;
    created_at: string;
}

export interface BookLoan {
    id: number;
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
