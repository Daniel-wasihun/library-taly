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

export interface Attendance {
    id: number;
    slug: string;
    library_id: number;
    user_type: 'student' | 'teacher' | 'staff';
    gender: 'male' | 'female';
    visitor_count: number;
    entry_at: string;
    created_at?: string;
    library?: {
        id: number;
        name: string;
        name_am: string | null;
        code: string;
    };
    staff?: {
        id: number;
        name: string;
        username: string;
    };
}

export interface AttendanceStats {
    total_users: number;
    total_male: number;
    total_female: number;
    gender_distribution: { gender: 'male' | 'female'; count: number }[];
    type_distribution: { user_type: string; count: number }[];
    library_distribution: any[];
    trends: { date: string; count: number }[];
    period?: { from: string; to: string };
}

export interface DashboardStats {
    today_total: number;
    today_male: number;
    today_female: number;
    total_branches: number;
    total_staff: number;
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
