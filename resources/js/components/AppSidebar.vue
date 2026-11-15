<template>
    <aside class="flex flex-col w-64 shrink-0 h-full overflow-hidden" style="background: var(--bg-sidebar);">
        <!-- Brand Header -->
        <div class="flex items-center gap-3 px-5 py-5 border-b border-slate-700">
            <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                style="background: var(--lib-primary-light);">
                <BookOpen :size="20" class="text-white" />
            </div>
            <div class="min-w-0">
                <p class="text-white font-semibold text-sm leading-tight truncate">UniLibrary</p>
                <p class="text-slate-400 text-xs truncate">University Library System</p>
            </div>
        </div>

        <!-- User Duty Status Badge -->
        <div class="mx-4 mt-4 mb-2 rounded-lg px-3 py-2.5 text-xs"
            :style="authStore.isAdmin
                ? 'background:#1d4ed8; color:#bfdbfe;'
                : authStore.isOnDutyToday
                    ? 'background:#065f46; color:#a7f3d0;'
                    : 'background:#7c2d12; color:#fca5a5;'">
            <div class="flex items-center gap-2">
                <div class="w-2 h-2 rounded-full"
                    :style="authStore.isAdmin
                        ? 'background:#60a5fa;'
                        : authStore.isOnDutyToday
                            ? 'background:#34d399; animation: pulse 2s infinite;'
                            : 'background:#f87171;'"></div>
                <span class="font-medium">
                    {{ authStore.isAdmin ? 'Admin Override Active' : authStore.isOnDutyToday ? 'On Duty Today' : 'Off Duty' }}
                </span>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-0.5">
            <template v-for="item in navItems" :key="item.to">
                <router-link
                    v-if="!item.roles || authStore.hasRole(item.roles)"
                    :to="item.to"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-all duration-150 group"
                    :class="isActive(item.to)
                        ? 'text-white font-medium'
                        : 'text-slate-400 hover:text-white hover:bg-slate-700'"
                    :style="isActive(item.to) ? 'background: var(--lib-primary-light);' : ''">
                    <component :is="item.icon" :size="17" class="shrink-0" />
                    <span class="truncate">{{ item.label }}</span>
                    <span v-if="item.to === '/schedules/my-turns' && !authStore.isAdmin && !authStore.isOnDutyToday"
                        class="ml-auto w-2 h-2 rounded-full bg-amber-400"></span>
                </router-link>
            </template>
        </nav>

        <!-- Bottom User Card -->
        <div class="border-t border-slate-700 px-4 py-3">
            <div class="flex items-center gap-3 mb-2">
                <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold text-white shrink-0"
                    style="background: var(--lib-primary-light);">
                    {{ initials }}
                </div>
                <div class="min-w-0 flex-1">
                    <p class="text-white text-xs font-medium truncate">{{ authStore.user?.name }}</p>
                    <p class="text-slate-400 text-xs truncate">{{ authStore.user?.roles?.[0] ?? 'User' }}</p>
                </div>
            </div>
            <button
                @click="handleLogout"
                class="w-full flex items-center gap-2 px-3 py-1.5 rounded-md text-xs text-slate-400 hover:text-white hover:bg-slate-700 transition-colors">
                <LogOut :size="14" />
                Sign Out
            </button>
        </div>
    </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import {
    LayoutDashboard, Users, CalendarDays, Clock,
    BookOpen, BookMarked, LogOut
} from '@lucide/vue';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const initials = computed(() => {
    const name = authStore.user?.name ?? '';
    return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
});

const navItems = [
    { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
    { to: '/users', label: 'User Management', icon: Users, roles: ['Super Admin', 'Head Librarian'] },
    { to: '/schedules', label: 'Duty Schedules', icon: CalendarDays, roles: ['Super Admin', 'Head Librarian'] },
    { to: '/my-turns', label: 'My Duty Turns', icon: Clock },
    { to: '/books', label: 'Book Catalog', icon: BookOpen },
    { to: '/circulation', label: 'Circulation & Loans', icon: BookMarked },
];

function isActive(path: string) {
    return route.path === path || route.path.startsWith(path + '/');
}

async function handleLogout() {
    await authStore.logout();
    router.push('/login');
}
</script>
