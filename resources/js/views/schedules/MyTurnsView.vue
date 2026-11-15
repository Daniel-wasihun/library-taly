<template>
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <div>
                <h1 class="text-xl font-bold" style="color: var(--text-primary);">My Duty Turns</h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">
                    Your assigned duty shifts and scheduled task access.
                </p>
            </div>
        </div>

        <!-- Admin Override Notice -->
        <div v-if="scheduleStore.isAdmin"
            class="rounded-xl p-4 flex items-start gap-3"
            style="background: var(--lib-primary-muted); border: 1px solid #93c5fd;">
            <Shield :size="18" style="color: var(--lib-primary);" class="shrink-0 mt-0.5" />
            <div>
                <p class="text-sm font-semibold" style="color: var(--lib-primary);">Administrative Override Active</p>
                <p class="text-xs mt-0.5" style="color: #3b82f6;">
                    As an administrator, you have unrestricted access to all library tasks regardless of schedule.
                </p>
            </div>
        </div>

        <!-- Today's Shifts -->
        <div class="rounded-xl border p-5" style="background: var(--bg-card); border-color: var(--border);">
            <h3 class="text-sm font-semibold mb-4 flex items-center gap-2" style="color: var(--text-primary);">
                <Zap :size="15" style="color: var(--lib-accent);" />
                Today's Active Shifts
            </h3>

            <div v-if="scheduleStore.todayTurns.length === 0 && !scheduleStore.isAdmin"
                class="py-8 text-center space-y-2">
                <CalendarOff :size="32" class="mx-auto" style="color: var(--text-muted);" />
                <p class="text-sm font-medium" style="color: var(--text-secondary);">No shifts scheduled for today</p>
                <p class="text-xs" style="color: var(--text-muted);">
                    You cannot perform restricted tasks today. Check your upcoming turns below.
                </p>
            </div>

            <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div v-for="shift in scheduleStore.isAdmin ? [] : scheduleStore.todayTurns" :key="shift.id"
                    class="rounded-lg p-4 border flex items-start gap-3"
                    style="background: var(--lib-success-light); border-color: #34d399;">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                        style="background: var(--lib-success);">
                        <Clock :size="18" class="text-white" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold" style="color: #065f46;">
                            {{ availableTasks[shift.task_key] ?? shift.task_key }}
                        </p>
                        <p class="text-xs mt-0.5 font-mono" style="color: #047857;">
                            {{ shift.start_time }} – {{ shift.end_time }}
                        </p>
                        <p v-if="shift.notes" class="text-xs mt-1" style="color: #059669;">{{ shift.notes }}</p>
                    </div>
                    <span class="ml-auto shrink-0 px-2 py-0.5 rounded-full text-xs font-medium"
                        style="background: #d1fae5; color: #065f46;">Active</span>
                </div>

                <!-- Admin All-Access Card -->
                <div v-if="scheduleStore.isAdmin"
                    class="rounded-lg p-4 border flex items-start gap-3"
                    style="background: var(--lib-primary-muted); border-color: #93c5fd;">
                    <div class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0"
                        style="background: var(--lib-primary-light);">
                        <Shield :size="18" class="text-white" />
                    </div>
                    <div>
                        <p class="text-sm font-semibold" style="color: var(--lib-primary);">All Tasks Authorized</p>
                        <p class="text-xs mt-0.5" style="color: #3b82f6;">Administrative override is active for all duties.</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Upcoming Turns -->
        <div class="rounded-xl border p-5" style="background: var(--bg-card); border-color: var(--border);">
            <h3 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">Upcoming Scheduled Turns</h3>
            <div v-if="scheduleStore.myTurns.length === 0"
                class="py-8 text-center space-y-2">
                <CalendarDays :size="28" class="mx-auto" style="color: var(--text-muted);" />
                <p class="text-xs" style="color: var(--text-muted);">No upcoming shifts scheduled.</p>
            </div>
            <div v-else class="space-y-2">
                <div v-for="shift in scheduleStore.myTurns" :key="shift.id"
                    class="flex items-center justify-between gap-3 px-4 py-3 rounded-lg border text-sm"
                    style="border-color: var(--border); background: var(--bg-page);">
                    <div class="min-w-0">
                        <p class="font-medium truncate" style="color: var(--text-primary);">
                            {{ availableTasks[shift.task_key] ?? shift.task_key }}
                        </p>
                        <p class="text-xs mt-0.5" style="color: var(--text-muted);">
                            {{ formatDate(shift.shift_date) }} · {{ shift.start_time }} – {{ shift.end_time }}
                        </p>
                    </div>
                    <span class="shrink-0 px-2 py-0.5 text-xs rounded-full font-medium"
                        style="background: var(--lib-primary-muted); color: var(--lib-primary);">
                        {{ shift.status }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Task Descriptions -->
        <div class="rounded-xl border p-5" style="background: var(--bg-card); border-color: var(--border);">
            <h3 class="text-sm font-semibold mb-4" style="color: var(--text-primary);">Duty-Gated Library Tasks</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div v-for="(name, key) in availableTasks" :key="key"
                    class="flex items-start gap-3 p-3 rounded-lg"
                    style="background: var(--bg-page);">
                    <div class="w-7 h-7 rounded flex items-center justify-center shrink-0"
                        style="background: var(--lib-primary-muted);">
                        <ClipboardList :size="14" style="color: var(--lib-primary-light);" />
                    </div>
                    <div>
                        <p class="text-xs font-semibold" style="color: var(--text-primary);">{{ name }}</p>
                        <code class="text-xs" style="color: var(--text-muted);">{{ key }}</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useScheduleStore } from '@/stores/scheduleStore';
import { Shield, Zap, Clock, CalendarOff, CalendarDays, ClipboardList } from '@lucide/vue';

const scheduleStore = useScheduleStore();
const availableTasks = computed(() => scheduleStore.availableTasks);

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
}

onMounted(async () => {
    await scheduleStore.fetchMyTurns();
});
</script>
