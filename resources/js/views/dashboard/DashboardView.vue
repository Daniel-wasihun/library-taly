<template>
    <div class="space-y-6">
        <!-- Header -->
        <div>
            <h1 class="text-xl font-bold" style="color: var(--text-primary);">Dashboard</h1>
            <p class="text-sm mt-1" style="color: var(--text-secondary);">
                University Library Overview — {{ todayDate }}
            </p>
        </div>

        <!-- Duty Turn Alert Banner -->
        <div v-if="!authStore.isAdmin && !authStore.canCirculateBooks"
            class="rounded-xl border p-4 flex items-start gap-3"
            style="background: #fef9c3; border-color: #fcd34d;">
            <AlertTriangle :size="18" class="text-amber-600 shrink-0 mt-0.5" />
            <div>
                <p class="text-sm font-semibold text-amber-800">Not On Circulation Duty Today</p>
                <p class="text-xs text-amber-700 mt-0.5" v-if="stats?.current_user_duty?.reason">
                    {{ stats.current_user_duty.reason }}
                </p>
                <p class="text-xs text-amber-700 mt-1" v-if="stats?.current_user_duty?.next_turn">
                    Your next turn: <strong>{{ stats.current_user_duty.next_turn }}</strong>
                </p>
            </div>
        </div>

        <!-- Stats Grid -->
        <div v-if="loading" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <div v-for="i in 6" :key="i" class="rounded-xl h-24 animate-pulse" style="background: var(--border);"></div>
        </div>
        <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
            <StatCard
                label="Total Books"
                :value="stats?.stats.total_books"
                icon="BookOpen"
                color="#1e3a8a"
                bg="#dbeafe" />
            <StatCard
                label="Total Copies"
                :value="stats?.stats.total_copies"
                icon="Library"
                color="#059669"
                bg="#d1fae5" />
            <StatCard
                label="Available"
                :value="stats?.stats.available_copies"
                icon="CheckSquare"
                color="#0891b2"
                bg="#cffafe" />
            <StatCard
                label="Active Loans"
                :value="stats?.stats.active_loans"
                icon="BookMarked"
                color="#7c3aed"
                bg="#ede9fe" />
            <StatCard
                label="Overdue"
                :value="stats?.stats.overdue_loans"
                icon="Clock"
                color="#dc2626"
                bg="#fee2e2" />
            <StatCard
                label="Members"
                :value="stats?.stats.total_members"
                icon="Users"
                color="#d97706"
                bg="#fef3c7" />
        </div>

        <!-- Two-Column Section -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Recent Loans -->
            <div class="lg:col-span-2 rounded-xl border p-5"
                style="background: var(--bg-card); border-color: var(--border);">
                <h3 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">Recent Loans</h3>
                <div v-if="loading" class="space-y-2">
                    <div v-for="i in 5" :key="i" class="h-10 rounded animate-pulse" style="background: var(--bg-page);"></div>
                </div>
                <div v-else-if="stats?.recent_loans?.length === 0" class="py-8 text-center text-sm" style="color: var(--text-muted);">
                    No loans recorded yet.
                </div>
                <div v-else class="space-y-2">
                    <div v-for="loan in stats?.recent_loans" :key="loan.id"
                        class="flex items-center justify-between gap-3 px-3 py-2.5 rounded-lg text-xs"
                        style="background: var(--bg-page);">
                        <div class="min-w-0">
                            <p class="font-medium truncate" style="color: var(--text-primary);">{{ loan.book?.title }}</p>
                            <p class="mt-0.5 truncate" style="color: var(--text-muted);">
                                {{ loan.member?.name }} — Due {{ formatDate(loan.due_date) }}
                            </p>
                        </div>
                        <span class="shrink-0 px-2 py-0.5 rounded-full font-medium"
                            :style="loanStatusStyle(loan.status)">
                            {{ loan.status }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- On-Duty Staff Today -->
            <div class="rounded-xl border p-5"
                style="background: var(--bg-card); border-color: var(--border);">
                <h3 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">On Duty Today</h3>
                <div v-if="loading" class="space-y-2">
                    <div v-for="i in 3" :key="i" class="h-14 rounded animate-pulse" style="background: var(--bg-page);"></div>
                </div>
                <div v-else-if="!stats?.stats?.on_duty_staff_today?.length"
                    class="py-8 text-center space-y-2">
                    <CalendarOff :size="28" class="mx-auto" style="color: var(--text-muted);" />
                    <p class="text-xs" style="color: var(--text-muted);">No staff scheduled today.</p>
                </div>
                <div v-else class="space-y-2">
                    <div v-for="entry in stats?.stats?.on_duty_staff_today" :key="`${entry.user_id}-${entry.task_key}`"
                        class="rounded-lg p-3 text-xs"
                        style="background: var(--lib-success-light);">
                        <p class="font-semibold" style="color: #065f46;">{{ entry.user_name }}</p>
                        <p class="mt-0.5" style="color: #047857;">{{ entry.task_name }}</p>
                        <p class="mt-0.5 font-mono" style="color: #059669;">
                            {{ entry.start_time }} – {{ entry.end_time }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, computed, defineComponent, h } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { dashboardApi } from '@/api';
import { AlertTriangle, CalendarOff, BookOpen, Library, CheckSquare, BookMarked, Clock, Users } from '@lucide/vue';

const authStore = useAuthStore();
const loading = ref(true);
const stats = ref<any>(null);

const todayDate = computed(() =>
    new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
);

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function loanStatusStyle(status: string) {
    return {
        borrowed: 'background:#dbeafe; color:#1e40af;',
        returned: 'background:#d1fae5; color:#065f46;',
        overdue: 'background:#fee2e2; color:#991b1b;',
    }[status] ?? 'background:#f1f5f9; color:#475569;';
}

onMounted(async () => {
    try {
        const { data } = await dashboardApi.stats();
        stats.value = data;
    } finally {
        loading.value = false;
    }
});

// ── Inline StatCard ──────────────────────────────────────────────────────────
const iconMap: Record<string, any> = { BookOpen, Library, CheckSquare, BookMarked, Clock, Users };

const StatCard = defineComponent({
    props: { label: String, value: [Number, String], icon: String, color: String, bg: String },
    setup(props) {
        return () => h('div', {
            class: 'rounded-xl border p-4 flex flex-col gap-2',
            style: `background: var(--bg-card); border-color: var(--border);`,
        }, [
            h('div', {
                class: 'w-8 h-8 rounded-lg flex items-center justify-center',
                style: `background: ${props.bg};`,
            }, [h(iconMap[props.icon!] ?? BookOpen, { size: 16, style: `color: ${props.color};` })]),
            h('div', [
                h('p', { class: 'text-xl font-bold', style: `color: ${props.color};` }, props.value ?? '—'),
                h('p', { class: 'text-xs mt-0.5', style: 'color: var(--text-muted);' }, props.label),
            ]),
        ]);
    },
});
</script>
