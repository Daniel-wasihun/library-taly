import api, { getStoredToken } from './client';
import type { User, DutySchedule, DashboardStats, ApiRole, Library, Attendance, AttendanceStats, AcademicYear } from '@/types';

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
            recent_entries: Attendance[];
            current_user_duty: {
                is_admin: boolean;
                authorized: boolean;
                reason: string;
                next_turn: string | null;
                task_name: string;
            };
        }>('/dashboard/stats'),
};

// ── Libraries (Branches) ──────────────────────────────────────────────────────
export const librariesApi = {
    list: (params?: { search?: string; active_only?: boolean }) =>
        api.get<{ success: boolean; data: Library[] }>('/libraries', { params }),

    show: (id: number) =>
        api.get<{ success: boolean; data: Library }>(`/libraries/${id}`),

    create: (data: {
        name: string;
        name_am?: string;
        code: string;
        location?: string;
        phone?: string;
        email?: string;
        description?: string;
        is_active?: boolean;
    }) => api.post<{ success: boolean; message: string; data: Library }>('/libraries', data),

    update: (
        id: number,
        data: {
            name: string;
            name_am?: string;
            code: string;
            location?: string;
            phone?: string;
            email?: string;
            description?: string;
            is_active?: boolean;
        }
    ) => api.put<{ success: boolean; message: string; data: Library }>(`/libraries/${id}`, data),

    destroy: (id: number) =>
        api.delete<{ success: boolean; message: string }>(`/libraries/${id}`),
};

// ── Academic Years (Academic Calendar CRUD) ──────────────────────────────────
export const academicYearsApi = {
    list: () =>
        api.get<{ data: AcademicYear[]; current: AcademicYear | null }>('/academic-years'),

    show: (id: number) =>
        api.get<{ data: AcademicYear }>(`/academic-years/${id}`),

    create: (data: {
        name: string;
        code: string;
        start_date: string;
        end_date: string;
        is_current?: boolean;
        description?: string;
    }) => api.post<{ message: string; data: AcademicYear }>('/academic-years', data),

    update: (
        id: number,
        data: {
            name?: string;
            code?: string;
            start_date?: string;
            end_date?: string;
            is_current?: boolean;
            description?: string;
        }
    ) => api.put<{ message: string; data: AcademicYear }>(`/academic-years/${id}`, data),

    destroy: (id: number) =>
        api.delete<{ message: string }>(`/academic-years/${id}`),

    setCurrent: (id: number) =>
        api.post<{ message: string; data: AcademicYear }>(`/academic-years/${id}/set-current`),
};

// ── Users ─────────────────────────────────────────────────────────────────────
export const usersApi = {
    list: (params?: { search?: string; role?: string; status?: string; library_id?: number | string; page?: number }) =>
        api.get('/users', { params }),

    show: (id: number) => api.get(`/users/${id}`),

    create: (data: {
        name: string;
        username: string;
        email: string;
        password: string;
        role: string;
        library_id?: number | null;
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
            library_id?: number | null;
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

// ── Physical Attendance / Usage Counter (LMS Pattern) ─────────────────────────
export const attendanceApi = {
    list: (params?: {
        library_id?: string | number;
        user_type?: string;
        gender?: string;
        date_from?: string;
        date_to?: string;
        page?: number;
        per_page?: number;
    }) => api.get('/physical/attendance', { params }),

    log: (data: {
        library_id: number;
        user_type: string;
        gender: string;
        count?: number;
    }) => api.post('/physical/attendance/log', data),

    batchSync: (data: {
        records: Array<{
            client_uuid: string;
            library_id: number;
            user_type: string;
            gender: string;
            count: number;
            recorded_at: string;
        }>;
    }) => api.post('/physical/attendance/sync', data),

    stats: (params?: {
        library_id?: string | number;
        date_from?: string;
        date_to?: string;
        lang?: string;
    }) => api.get<AttendanceStats>('/physical/attendance/stats', { params }),

    export: (params?: any) =>
        api.get('/physical/attendance/export', {
            params,
            responseType: 'blob',
        }),

    downloadReport: (params?: any) =>
        api.get('/physical/attendance/report/download', {
            params,
            responseType: 'blob',
        }),

    updateCount: (slug: string, count: number) =>
        api.patch(`/physical/attendance/${slug}/count`, { count }),

    destroy: (slug: string) =>
        api.delete(`/physical/attendance/${slug}`),
};

// ── Shift Inactivity & Duty Compliance Monitoring ────────────────────────────
export const shiftInactivityApi = {
    alerts: (params?: { library_id?: number; date?: string; shift_id?: number }) =>
        api.get<{ success: boolean; data: any }>('/shifts/inactivity-alerts', { params }),

    report: (params?: { date?: string; library_id?: number; shift_id?: number }) =>
        api.get<{ success: boolean; data: any }>('/shifts/inactivity-report', { params }),

    exportUrl: (params?: { date?: string; library_id?: number; shift_id?: number; format?: string; lang?: string }) => {
        const query = new URLSearchParams();
        if (params?.date) query.set('date', params.date);
        if (params?.library_id) query.set('library_id', String(params.library_id));
        if (params?.shift_id) query.set('shift_id', String(params.shift_id));
        if (params?.format) query.set('format', params.format);
        if (params?.lang) query.set('lang', params.lang);
        const token = getStoredToken();
        if (token) query.set('token', token);
        return `/api/shifts/inactivity-report/export?${query.toString()}`;
    },

    exportBlob: (params?: { date?: string; library_id?: number; shift_id?: number; format?: string; lang?: string }) => {
        return api.get('/shifts/inactivity-report/export', {
            params,
            responseType: 'blob',
        });
    },
};
