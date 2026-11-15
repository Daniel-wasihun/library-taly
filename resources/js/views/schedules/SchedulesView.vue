<template>
    <div class="space-y-5">
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 justify-between">
            <div>
                <h1 class="text-xl font-bold" style="color: var(--text-primary);">Duty Schedules</h1>
                <p class="text-sm mt-1" style="color: var(--text-secondary);">Assign and manage staff duty shifts.</p>
            </div>
            <button @click="showCreate = true"
                class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium text-white"
                style="background: var(--lib-primary-light);">
                <Plus :size="15" /> Assign Shift
            </button>
        </div>

        <!-- Filters -->
        <div class="flex flex-col sm:flex-row gap-3">
            <input v-model="dateFilter" type="date"
                class="px-3 py-2 text-sm rounded-lg border"
                style="background: var(--bg-card); border-color: var(--border); color: var(--text-primary);" />
            <select v-model="taskFilter"
                class="px-3 py-2 text-sm rounded-lg border"
                style="background: var(--bg-card); border-color: var(--border); color: var(--text-primary);">
                <option value="">All Tasks</option>
                <option v-for="(name, key) in scheduleStore.availableTasks" :key="key" :value="key">{{ name }}</option>
            </select>
        </div>

        <!-- Schedule Table -->
        <div class="rounded-xl border overflow-hidden" style="background: var(--bg-card); border-color: var(--border);">
            <div v-if="scheduleStore.loading" class="p-10 text-center">
                <Loader2 :size="24" class="animate-spin mx-auto" style="color: var(--lib-primary-light);" />
            </div>
            <table v-else class="w-full text-sm">
                <thead>
                    <tr style="background: var(--bg-page);">
                        <th v-for="col in ['Staff Member', 'Task', 'Date', 'Time', 'Status', '']" :key="col"
                            class="text-left px-4 py-3 text-xs font-semibold uppercase tracking-wide"
                            style="color: var(--text-muted);">{{ col }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-if="scheduleStore.schedules.length === 0">
                        <td colspan="6" class="py-12 text-center text-sm" style="color: var(--text-muted);">
                            No shifts found.
                        </td>
                    </tr>
                    <tr v-for="s in scheduleStore.schedules" :key="s.id"
                        class="border-t hover:bg-slate-50 transition-colors"
                        style="border-color: var(--border);">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white"
                                    style="background: var(--lib-primary-light);">
                                    {{ initials((s as any).user?.name ?? '') }}
                                </div>
                                <div class="min-w-0">
                                    <p class="font-medium text-xs truncate" style="color: var(--text-primary);">
                                        {{ (s as any).user?.name }}
                                    </p>
                                    <p class="text-xs" style="color: var(--text-muted);">
                                        @{{ (s as any).user?.username }}
                                    </p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-xs" style="color: var(--text-secondary);">
                            {{ scheduleStore.availableTasks[s.task_key] ?? s.task_key }}
                        </td>
                        <td class="px-4 py-3 text-xs font-mono" style="color: var(--text-secondary);">
                            {{ formatDate(s.shift_date as string) }}
                        </td>
                        <td class="px-4 py-3 text-xs font-mono" style="color: var(--text-secondary);">
                            {{ s.start_time }} – {{ s.end_time }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-0.5 text-xs rounded-full font-medium" :style="statusStyle(s.status)">
                                {{ s.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3">
                            <button @click="confirmDelete(s)"
                                class="p-1.5 rounded-md hover:bg-red-50 transition-colors">
                                <Trash2 :size="13" style="color: var(--lib-danger);" />
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Assign Shift Modal -->
        <AppModal v-model="showCreate" title="Assign Duty Shift" size="md">
            <form @submit.prevent="handleCreate" class="space-y-4">
                <div>
                    <label class="label">Staff Member (User ID)</label>
                    <input v-model.number="form.user_id" type="number" placeholder="User ID (e.g. 2)" required class="inp" />
                </div>
                <div>
                    <label class="label">Library Task</label>
                    <select v-model="form.task_key" required class="inp">
                        <option value="">Select task…</option>
                        <option v-for="(name, key) in scheduleStore.availableTasks" :key="key" :value="key">{{ name }}</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="label">Shift Date</label>
                        <input v-model="form.shift_date" type="date" required class="inp" />
                    </div>
                    <div>
                        <label class="label">Start Time</label>
                        <input v-model="form.start_time" type="time" class="inp" />
                    </div>
                    <div>
                        <label class="label">End Time</label>
                        <input v-model="form.end_time" type="time" class="inp" />
                    </div>
                </div>
                <div>
                    <label class="label">Notes (optional)</label>
                    <textarea v-model="form.notes" rows="2" placeholder="Shift notes…" class="inp resize-none"></textarea>
                </div>
            </form>
            <template #footer>
                <button @click="showCreate = false" class="px-4 py-2 text-sm rounded-lg border"
                    style="border-color: var(--border);">Cancel</button>
                <button @click="handleCreate" :disabled="scheduleStore.loading"
                    class="px-4 py-2 text-sm rounded-lg text-white font-medium disabled:opacity-50"
                    style="background: var(--lib-primary-light);">
                    {{ scheduleStore.loading ? 'Saving…' : 'Assign Shift' }}
                </button>
            </template>
        </AppModal>

        <!-- Delete Modal -->
        <AppModal v-model="showDelete" title="Cancel Shift" size="sm">
            <div class="text-center space-y-3 py-2">
                <Trash2 :size="28" class="mx-auto" style="color: var(--lib-danger);" />
                <p class="text-sm" style="color: var(--text-secondary);">Cancel this duty shift?</p>
            </div>
            <template #footer>
                <button @click="showDelete = false" class="px-4 py-2 text-sm rounded-lg border" style="border-color: var(--border);">Keep</button>
                <button @click="handleDelete" class="px-4 py-2 text-sm rounded-lg text-white" style="background: var(--lib-danger);">Cancel Shift</button>
            </template>
        </AppModal>
    </div>
</template>

<script setup lang="ts">
import { ref, reactive, watch, onMounted } from 'vue';
import { useScheduleStore } from '@/stores/scheduleStore';
import AppModal from '@/components/ui/AppModal.vue';
import { Plus, Trash2, Loader2 } from '@lucide/vue';
import type { DutySchedule } from '@/types';

const scheduleStore = useScheduleStore();
const dateFilter = ref('');
const taskFilter = ref('');
const showCreate = ref(false);
const showDelete = ref(false);
const deletingId = ref<number | null>(null);

const form = reactive({
    user_id: 0, task_key: '', shift_date: '', start_time: '08:00', end_time: '17:00', notes: '',
});

function initials(name: string) {
    return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase();
}

function formatDate(d: string) {
    return new Date(d).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' });
}

function statusStyle(s: string) {
    return {
        scheduled: 'background: var(--lib-primary-muted); color: var(--lib-primary);',
        active: 'background: var(--lib-success-light); color: var(--lib-success);',
        completed: 'background: #f3f4f6; color: #6b7280;',
        cancelled: 'background: var(--lib-danger-light); color: var(--lib-danger);',
    }[s] ?? 'background: var(--bg-page); color: var(--text-muted);';
}

function confirmDelete(s: DutySchedule) {
    deletingId.value = s.id;
    showDelete.value = true;
}

async function handleCreate() {
    const success = await scheduleStore.createSchedule({ ...form });
    if (success) showCreate.value = false;
}

async function handleDelete() {
    if (deletingId.value) {
        await scheduleStore.deleteSchedule(deletingId.value);
        showDelete.value = false;
    }
}

watch([dateFilter, taskFilter], () => {
    scheduleStore.fetchSchedules({ date: dateFilter.value || undefined, task_key: taskFilter.value || undefined });
});

onMounted(async () => {
    await scheduleStore.fetchSchedules();
});
</script>

<style scoped>
.label {
    display: block;
    font-size: 0.75rem;
    font-weight: 500;
    margin-bottom: 0.25rem;
    color: var(--text-secondary);
}
.inp {
    width: 100%;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    border: 1px solid var(--border);
    background: var(--bg-input);
    color: var(--text-primary);
}
</style>
