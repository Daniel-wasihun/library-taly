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
            try {
                const response = await authApi.me();
                user.value = response.data.user;
            } catch {
                token.value = null;
                user.value = null;
                localStorage.removeItem('auth_token');
                localStorage.removeItem('univ-library-auth');
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
        }

        return {
            user,
            token,
            loading,
            isAuthenticated,
            isAdmin,
            isSuperAdmin,
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
