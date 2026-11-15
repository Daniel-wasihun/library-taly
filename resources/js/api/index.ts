import api from './client';
import type { User, DutySchedule, Book, BookLoan, DashboardStats, ApiRole } from '@/types';

// ── Auth ──────────────────────────────────────────────────────────────────────
export const authApi = {
    login: (credentials: { login: string; password: string }) =>
        api.post<{ success: boolean; token: string; user: User }>('/auth/login', credentials),

    me: () => api.get<{ success: boolean; user: User }>('/auth/me'),

    logout: () => api.post('/auth/logout'),
};

// ── Dashboard ─────────────────────────────────────────────────────────────────
export const dashboardApi = {
    stats: () =>
        api.get<{
            success: boolean;
            stats: DashboardStats;
            recent_loans: BookLoan[];
            current_user_duty: {
                is_admin: boolean;
                circulation_authorized: boolean;
                reason: string;
                next_turn: string | null;
                task_name: string;
            };
        }>('/dashboard/stats'),
};

// ── Users ─────────────────────────────────────────────────────────────────────
export const usersApi = {
    list: (params?: { search?: string; role?: string; status?: string; page?: number }) =>
        api.get('/users', { params }),

    show: (id: number) => api.get(`/users/${id}`),

    create: (data: {
        name: string;
        username: string;
        email: string;
        password: string;
        role: string;
        status?: string;
        phone?: string;
    }) => api.post('/users', data),

    update: (
        id: number,
        data: {
            name: string;
            username: string;
            email: string;
            password?: string;
            role: string;
            status: string;
            phone?: string;
        }
    ) => api.put(`/users/${id}`, data),

    destroy: (id: number) => api.delete(`/users/${id}`),

    overridePermissions: (userId: number, permissions: string[]) =>
        api.post<{ success: boolean; message: string; direct_permissions: string[]; all_permissions: string[] }>(
            `/users/${userId}/permissions`,
            { permissions }
        ),
};

// ── Roles ─────────────────────────────────────────────────────────────────────
export const rolesApi = {
    list: () => api.get<{ success: boolean; roles: ApiRole[]; permissions: { id: number; name: string }[] }>('/roles'),

    create: (data: { name: string; permissions?: string[] }) =>
        api.post('/roles', data),

    update: (id: number, data: { name: string; permissions?: string[] }) =>
        api.put(`/roles/${id}`, data),

    destroy: (id: number) =>
        api.delete(`/roles/${id}`),

    tasks: () => api.get<{ success: boolean; tasks: { key: string; name: string }[] }>('/tasks'),
};

// ── Duty Schedules ────────────────────────────────────────────────────────────
export const schedulesApi = {
    list: (params?: { user_id?: number; date?: string; task_key?: string; page?: number }) =>
        api.get('/schedules', { params }),

    myTurns: () =>
        api.get<{
            success: boolean;
            is_admin: boolean;
            today_turns: DutySchedule[];
            upcoming_turns: DutySchedule[];
            available_tasks: Record<string, string>;
        }>('/schedules/my-turns'),

    checkDuty: (taskKey: string, date?: string) =>
        api.get('/schedules/check-duty', { params: { task_key: taskKey, date } }),

    create: (data: {
        user_id: number;
        task_key: string;
        shift_date: string;
        start_time?: string;
        end_time?: string;
        notes?: string;
    }) => api.post('/schedules', data),

    destroy: (id: number) => api.delete(`/schedules/${id}`),
};

// ── Books ─────────────────────────────────────────────────────────────────────
export const booksApi = {
    list: (params?: { search?: string; category?: string; available?: boolean; page?: number }) =>
        api.get('/books', { params }),

    create: (data: Omit<Book, 'id' | 'created_at' | 'available_copies'>) =>
        api.post('/books', data),

    update: (id: number, data: Partial<Book>) => api.put(`/books/${id}`, data),

    destroy: (id: number) => api.delete(`/books/${id}`),
};

// ── Circulation ───────────────────────────────────────────────────────────────
export const circulationApi = {
    list: (params?: { status?: string; search?: string; page?: number }) =>
        api.get('/circulation/loans', { params }),

    issue: (data: { book_id: number; member_id: number; due_days?: number; notes?: string }) =>
        api.post('/circulation/issue', data),

    return: (loanId: number) => api.post(`/circulation/return/${loanId}`),
};
