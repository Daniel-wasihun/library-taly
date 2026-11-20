import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';

function getLandingRoute(authStore: any) {
    if (authStore.canViewDashboard) {
        return { name: 'Dashboard' };
    }
    if (authStore.hasPermission('attendance.record') || authStore.hasPermission('attendance.view')) {
        return { name: 'Attendance' };
    }
    if (authStore.hasPermission('libraries.view')) {
        return { name: 'Libraries' };
    }
    if (authStore.hasPermission('academic_years.view')) {
        return { name: 'AcademicYears' };
    }
    if (authStore.hasPermission('schedules.view')) {
        return { name: 'Schedules' };
    }
    if (authStore.hasPermission('users.view')) {
        return { name: 'Users' };
    }
    if (authStore.hasPermission('roles.view')) {
        return { name: 'Roles' };
    }
    return { name: 'MyTurns' };
}

const routes: RouteRecordRaw[] = [
    {
        path: '/login',
        name: 'Login',
        component: () => import('@/views/auth/LoginView.vue'),
        meta: { guest: true },
    },
    {
        path: '/',
        component: () => import('@/layouts/AppLayout.vue'),
        meta: { requiresAuth: true },
        children: [
            {
                path: '',
                redirect: () => {
                    const authStore = useAuthStore();
                    return getLandingRoute(authStore);
                },
            },
            {
                path: 'dashboard',
                name: 'Dashboard',
                component: () => import('@/views/dashboard/DashboardView.vue'),
                meta: { title: 'Dashboard', icon: 'LayoutDashboard', permission: 'dashboard.view' },
            },
            {
                path: 'libraries',
                name: 'Libraries',
                component: () => import('@/views/libraries/LibrariesView.vue'),
                meta: { title: 'Library Branches', icon: 'Landmark', permission: 'libraries.view' },
            },
            {
                path: 'academic-years',
                name: 'AcademicYears',
                component: () => import('@/views/academic-years/AcademicYearsView.vue'),
                meta: { title: 'Academic Years', icon: 'CalendarRange', permission: 'academic_years.view' },
            },
            {
                path: 'my-turns',
                name: 'MyTurns',
                component: () => import('@/views/schedules/MyTurnsView.vue'),
                meta: { title: 'My Duty Turns', icon: 'Clock', requiresAuth: true },
            },
            {
                path: 'attendance',
                name: 'Attendance',
                component: () => import('@/views/attendance/AttendanceView.vue'),
                meta: { title: 'Physical Attendance / Usage Counter', icon: 'Activity', permissions: ['attendance.view', 'attendance.record'] },
            },
            {
                path: 'schedules',
                name: 'Schedules',
                component: () => import('@/views/schedules/SchedulesView.vue'),
                meta: { title: 'Duty Schedules', icon: 'CalendarDays', permission: 'schedules.view' },
            },
            {
                path: 'users',
                name: 'Users',
                component: () => import('@/views/users/UsersView.vue'),
                meta: { title: 'User Management', icon: 'Users', permission: 'users.view' },
            },
            {
                path: 'roles',
                name: 'Roles',
                component: () => import('@/views/roles/RolesView.vue'),
                meta: { title: 'Roles & RBAC Matrix', icon: 'ShieldCheck', permission: 'roles.view' },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: () => {
            const authStore = useAuthStore();
            return getLandingRoute(authStore);
        },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});

// Navigation guard
router.beforeEach(async (to) => {
    const authStore = useAuthStore();

    // Restore token from localStorage if store is empty
    if (!authStore.token) {
        let storedToken = localStorage.getItem('auth_token');
        if (!storedToken) {
            try {
                const persisted = localStorage.getItem('univ-library-auth');
                if (persisted) {
                    storedToken = JSON.parse(persisted)?.token || null;
                }
            } catch {}
        }
        if (storedToken) {
            authStore.token = storedToken;
        }
    }

    // If token exists but user profile is missing, attempt to fetch user once safely
    if (authStore.token && !authStore.user) {
        try {
            await authStore.fetchMe();
        } catch {
            authStore.token = null;
            authStore.user = null;
            localStorage.removeItem('auth_token');
            localStorage.removeItem('univ-library-auth');
        }
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return { name: 'Login' };
    }

    if (to.meta.guest && authStore.isAuthenticated) {
        return getLandingRoute(authStore);
    }

    // 1. Strict Dashboard authorization guard: only users with dashboard.view permission can access
    if (to.name === 'Dashboard' && !authStore.canViewDashboard) {
        return getLandingRoute(authStore);
    }

    // 2. Strict single permission guard
    if (to.meta.permission && !authStore.hasPermission(to.meta.permission as string)) {
        return getLandingRoute(authStore);
    }

    // 3. Strict multi-permission guard (any permitted)
    if (to.meta.permissions && Array.isArray(to.meta.permissions)) {
        const hasAny = (to.meta.permissions as string[]).some((p) => authStore.hasPermission(p));
        if (!hasAny) {
            return getLandingRoute(authStore);
        }
    }

    // 4. Role guard fallback
    if (to.meta.roles && !authStore.hasRole(to.meta.roles as string[])) {
        return getLandingRoute(authStore);
    }

    return true;
});

export default router;
