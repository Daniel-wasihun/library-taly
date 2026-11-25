import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import { authApi } from '@/api';
import { useToastStore } from './toastStore';
import type { User } from '@/types';

export const useAuthStore = defineStore(
    'auth',
    () => {
        const user = ref<User | null>(null);
        const token = ref<string | null>(null);
        const loading = ref(false);

        const isAuthenticated = computed(() => !!token.value && !!user.value);
        const isSuperAdmin = computed(() => {
            const u = user.value as any;
            if (!u) return false;
            return Boolean(u.is_super_admin) ||
                   u.roles?.includes('Super Admin') ||
                   (Array.isArray(u.roles) && u.roles.some((r: any) => (typeof r === 'string' ? r : r.name) === 'Super Admin'));
        });
        const isAdmin = computed(() => isSuperAdmin.value || (user.value?.is_admin ?? false));
        const canManageAllLibraries = computed(() => isSuperAdmin.value || (user.value?.can_manage_all_libraries ?? false));
        const canManageLibrary = computed(() => isSuperAdmin.value || (user.value as any)?.can_manage_library || hasRole('Manager') || hasPermission('libraries.manage'));
        const canManageReporting = computed(() => isSuperAdmin.value || (user.value as any)?.can_manage_reporting || hasRole('Manager') || hasPermission('attendance.analytics') || hasPermission('libraries.manage'));
        const canViewDashboard = computed(() => {
            return isSuperAdmin.value || hasPermission('dashboard.view');
        });
        const canViewLibraries = computed(() => {
            return isSuperAdmin.value || hasPermission('libraries.view') || hasPermission('libraries.manage');
        });
        const canCreateLibrary = computed(() => isSuperAdmin.value || hasPermission('libraries.create') || hasPermission('libraries.manage'));
        const canUpdateLibrary = computed(() => isSuperAdmin.value || hasPermission('libraries.update') || hasPermission('libraries.manage'));
        const canDeleteLibrary = computed(() => isSuperAdmin.value || hasPermission('libraries.delete') || hasPermission('libraries.manage'));
        const canViewAcademicYears = computed(() => {
            return isSuperAdmin.value || hasPermission('academic_years.view') || hasPermission('libraries.view');
        });
        const canManageAcademicYears = computed(() => {
            return isSuperAdmin.value ||
                   hasPermission('academic_years.create') ||
                   hasPermission('academic_years.update') ||
                   hasPermission('academic_years.delete') ||
                   hasPermission('libraries.manage');
        });
        const canViewUsers = computed(() => {
            return isSuperAdmin.value || hasPermission('users.view');
        });
        const canManageUsers = computed(() => {
            return isSuperAdmin.value || hasPermission('users.create') || hasPermission('users.update') || hasPermission('users.delete');
        });
        const canViewRoles = computed(() => {
            return isSuperAdmin.value || hasPermission('roles.view');
        });
        const canManageRoles = computed(() => {
            return isSuperAdmin.value || hasPermission('roles.create') || hasPermission('roles.update') || hasPermission('roles.delete');
        });
        const canViewSchedules = computed(() => {
            return isSuperAdmin.value || hasPermission('schedules.view');
        });
        const canManageSchedules = computed(() => {
            return isSuperAdmin.value || hasPermission('schedules.manage');
        });
        const canViewAttendance = computed(() => {
            return isSuperAdmin.value || hasPermission('attendance.view') || hasPermission('attendance.record');
        });
        const canRecordAttendance = computed(() => {
            return isSuperAdmin.value || hasPermission('attendance.record');
        });
        const canManageAttendanceAnalytics = computed(() => {
            return isSuperAdmin.value || (user.value as any)?.can_manage_reporting || hasPermission('attendance.analytics') || hasPermission('libraries.manage');
        });
        const userRoles = computed(() => user.value?.roles ?? []);
        const isOnDutyToday = computed(() => isSuperAdmin.value || (user.value?.duty_status?.has_active_shift_today ?? false));
        const canCirculateBooks = computed(() => isSuperAdmin.value || (user.value?.duty_status?.circulation_authorized_today ?? false));
        const canLogAttendance = computed(() => isSuperAdmin.value || isAdmin.value || (user.value?.duty_status?.circulation_authorized_today ?? false));

        function hasRole(role: string | string[]): boolean {
            if (isSuperAdmin.value) return true;
            const roles = Array.isArray(role) ? role : [role];
            return roles.some((r) => userRoles.value.includes(r));
        }

        function hasPermission(permission: string): boolean {
            if (isSuperAdmin.value) return true;
            return user.value?.permissions?.includes(permission) ?? false;
        }

        async function login(credentials: { login: string; password: string }): Promise<boolean> {
            const toastStore = useToastStore();
            loading.value = true;
            try {
                const response = await authApi.login(credentials);
                const { token: authToken, user: userData } = response.data;
                token.value = authToken;
                user.value = userData;
                localStorage.setItem('auth_token', authToken);
                toastStore.success(`Welcome back, ${userData.name.split(' ')[0]}!`);
                return true;
            } catch (error: any) {
                const message =
                    error.response?.data?.errors?.login?.[0] ||
                    error.response?.data?.message ||
                    'Login failed. Please check your credentials.';
                toastStore.error(message);
                return false;
            } finally {
                loading.value = false;
            }
        }

        async function fetchMe(): Promise<void> {
            if (!token.value) return;
            // Never wipe credentials if device is currently offline
            if (typeof navigator !== 'undefined' && !navigator.onLine) {
                return;
            }
            try {
                const response = await authApi.me();
                user.value = response.data.user;
            } catch (err: any) {
                // If it's a network disconnection, timeout, or unreachable server, preserve cached auth
                const isNetworkError = !err.response || err.code === 'ECONNABORTED' || err.message?.includes('Network');
                if (isNetworkError) {
                    return;
                }
                // Only clear credentials if backend explicitly responded with 401 Unauthorized
                if (err.response?.status === 401) {
                    token.value = null;
                    user.value = null;
                    localStorage.removeItem('auth_token');
                    localStorage.removeItem('univ-library-auth');
                }
            }
        }

        async function logout(): Promise<void> {
            try {
                if (token.value) {
                    await authApi.logout();
                }
            } catch {}
            token.value = null;
            user.value = null;
            localStorage.removeItem('auth_token');
            localStorage.removeItem('univ-library-auth');
            localStorage.removeItem('unilib_pwa_last_route');
        }

        return {
            user,
            token,
            loading,
            isAuthenticated,
            isAdmin,
            isSuperAdmin,
            canManageAllLibraries,
            canManageLibrary,
            canManageReporting,
            canViewDashboard,
            canViewLibraries,
            canCreateLibrary,
            canUpdateLibrary,
            canDeleteLibrary,
            canViewAcademicYears,
            canManageAcademicYears,
            canViewUsers,
            canManageUsers,
            canViewRoles,
            canManageRoles,
            canViewSchedules,
            canManageSchedules,
            canViewAttendance,
            canRecordAttendance,
            canManageAttendanceAnalytics,
            userRoles,
            isOnDutyToday,
            canCirculateBooks,
            canLogAttendance,
            hasRole,
            hasPermission,
            login,
            fetchMe,
            logout,
        };
    },
    {
        persist: {
            key: 'univ-library-auth',
            pick: ['token', 'user'],
        },
    }
);
