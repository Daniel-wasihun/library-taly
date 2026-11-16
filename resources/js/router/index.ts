import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';

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
                redirect: '/dashboard',
            },
            {
                path: 'dashboard',
                name: 'Dashboard',
                component: () => import('@/views/dashboard/DashboardView.vue'),
                meta: { title: 'Dashboard', icon: 'LayoutDashboard' },
            },
            {
                path: 'libraries',
                name: 'Libraries',
                component: () => import('@/views/libraries/LibrariesView.vue'),
                meta: { title: 'Library Branches', icon: 'Landmark', roles: ['Super Admin', 'Head Librarian'] },
            },
            {
                path: 'users',
                name: 'Users',
                component: () => import('@/views/users/UsersView.vue'),
                meta: { title: 'User Management', icon: 'Users', roles: ['Super Admin', 'Head Librarian'] },
            },
            {
                path: 'roles',
                name: 'Roles',
                component: () => import('@/views/roles/RolesView.vue'),
                meta: { title: 'Roles & RBAC Matrix', icon: 'ShieldCheck', roles: ['Super Admin', 'Head Librarian'] },
            },
            {
                path: 'schedules',
                name: 'Schedules',
                component: () => import('@/views/schedules/SchedulesView.vue'),
                meta: { title: 'Duty Schedules', icon: 'CalendarDays' },
            },
            {
                path: 'my-turns',
                name: 'MyTurns',
                component: () => import('@/views/schedules/MyTurnsView.vue'),
                meta: { title: 'My Duty Turns', icon: 'Clock' },
            },
            {
                path: 'books',
                name: 'Books',
                component: () => import('@/views/books/BooksView.vue'),
                meta: { title: 'Book Catalog', icon: 'BookOpen' },
            },
            {
                path: 'circulation',
                name: 'Circulation',
                component: () => import('@/views/circulation/CirculationView.vue'),
                meta: { title: 'Circulation & Loans', icon: 'BookMarked' },
            },
        ],
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard',
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

    // Restore token from localStorage if store is empty (after page reload)
    if (!authStore.token) {
        const storedToken = localStorage.getItem('auth_token');
        if (storedToken) {
            authStore.token = storedToken;
            await authStore.fetchMe();
        }
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return { name: 'Login' };
    }

    if (to.meta.guest && authStore.isAuthenticated) {
        return { name: 'Dashboard' };
    }

    // Role guard
    if (to.meta.roles && !authStore.hasRole(to.meta.roles as string[])) {
        return { name: 'Dashboard' };
    }

    return true;
});

export default router;
