<template>
  <div class="space-y-5 w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
          <CalendarDays class="w-6 h-6 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('nav.schedules', 'Duty Shift Schedules') }}</span>
        </h2>
        <p class="text-xs text-main-text/50 mt-0.5">
          Assign, inspect, and enforce staff duty shifts for library operations
        </p>
      </div>

      <Button
        variant="primary"
        size="sm"
        :icon="Plus"
        @click="showCreate = true"
      >
        Assign Shift
      </Button>
    </div>

    <!-- Filters Toolbar -->
    <div class="flex flex-col sm:flex-row gap-3">
      <input
        v-model="dateFilter"
        type="date"
        class="px-3 py-2 text-xs rounded-sm bg-card-bg border border-card-border text-main-text shadow-2xs"
      />
      <select
        v-model="taskFilter"
        class="px-3 py-2 text-xs rounded-sm bg-card-bg border border-card-border text-main-text shadow-2xs"
      >
        <option value="">All Tasks</option>
        <option v-for="(name, key) in scheduleStore.availableTasks" :key="key" :value="key">
          {{ name }}
        </option>
      </select>
    </div>

    <!-- DataTable -->
    <DataTable
      :columns="columns"
      :items="scheduleStore.schedules"
      :loading="scheduleStore.loading"
      :total="scheduleStore.pagination.total"
      :per-page="15"
      :current-page="scheduleStore.pagination.current_page"
      @page-change="handlePageChange"
    >
      <template #user="{ item }">
        <div class="flex items-center gap-2.5">
          <div class="w-7 h-7 rounded-lg bg-nav-accent/10 text-nav-accent border border-nav-accent/25 flex items-center justify-center font-bold text-xs shrink-0">
            {{ (item.user?.name || '?').charAt(0).toUpperCase() }}
          </div>
          <div class="min-w-0">
            <p class="font-bold text-main-text truncate">{{ item.user?.name }}</p>
            <p class="text-[11px] text-main-text/50 font-mono truncate">@{{ item.user?.username }}</p>
          </div>
        </div>
      </template>

      <template #task="{ item }">
        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-semibold bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/25">
          {{ scheduleStore.availableTasks[item.task_key] || item.task_key }}
        </span>
      </template>

      <template #shift_date="{ item }">
        <span class="text-xs font-mono text-main-text/70">{{ formatDate(item.shift_date) }}</span>
      </template>

      <template #time="{ item }">
        <span class="text-xs font-mono text-main-text/70">{{ item.start_time }} – {{ item.end_time }}</span>
      </template>

      <template #status="{ item }">
        <StatusBadge :status="item.status" />
      </template>

      <template #actions="{ item }">
        <div class="flex items-center justify-end">
          <button
            type="button"
            class="table-action-btn text-rose-600 hover:bg-rose-500/10 transition cursor-pointer"
            title="Cancel Shift"
            @click="confirmDelete(item)"
          >
            <Trash2 class="w-4 h-4 stroke-[2.2]" />
          </button>
        </div>
      </template>
    </DataTable>

    <!-- Assign Shift Modal -->
    <Modal
      :show="showCreate"
      title="Assign Duty Shift"
      description="Schedule a turn for a staff member to authorize library duties."
      :icon="CalendarDays"
      size="lg"
      @close="showCreate = false"
    >
      <form @submit.prevent="handleCreate" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Staff Member User ID <span class="text-rose-500">*</span>
          </label>
          <input
            v-model.number="form.user_id"
            type="number"
            min="1"
            required
            placeholder="Staff User ID (e.g. 2 for tigist_duty, 3 for yohannes_offduty)"
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Library Duty Task <span class="text-rose-500">*</span>
          </label>
          <select
            v-model="form.task_key"
            required
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs"
          >
            <option value="">Select task...</option>
            <option v-for="(name, key) in scheduleStore.availableTasks" :key="key" :value="key">
              {{ name }}
            </option>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              Shift Date <span class="text-rose-500">*</span>
            </label>
            <input
              v-model="form.shift_date"
              type="date"
              required
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">Start Time</label>
            <input
              v-model="form.start_time"
              type="time"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">End Time</label>
            <input
              v-model="form.end_time"
              type="time"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">Notes (optional)</label>
          <textarea
            v-model="form.notes"
            rows="2"
            placeholder="Desk assignment, handover instructions..."
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs resize-none"
          ></textarea>
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showCreate = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button
          variant="primary"
          size="sm"
          :loading="scheduleStore.loading"
          @click="handleCreate"
        >
          Assign Shift
        </Button>
      </template>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <Modal
      :show="showDelete"
      title="Cancel Duty Shift"
      size="sm"
      @close="showDelete = false"
    >
      <div class="text-center py-2 space-y-3">
        <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center bg-rose-500/10 text-rose-600 border border-rose-500/20">
          <Trash2 class="w-6 h-6 stroke-[2.2]" />
        </div>
        <p class="text-xs text-main-text">
          Cancel and delete this assigned duty shift?
        </p>
      </div>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showDelete = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button variant="danger" size="sm" @click="handleDelete">
          Cancel Shift
        </Button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useScheduleStore } from '@/stores/scheduleStore';
import { useLanguageStore } from '@/stores/languageStore';
import Button from '@/components/ui/Button.vue';
import DataTable, { ColumnDef } from '@/components/ui/DataTable.vue';
import Modal from '@/components/ui/Modal.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import { CalendarDays, Plus, Trash2 } from 'lucide-vue-next';
import type { DutySchedule } from '@/types';

const scheduleStore = useScheduleStore();
const langStore = useLanguageStore();

const dateFilter = ref('');
const taskFilter = ref('');
const showCreate = ref(false);
const showDelete = ref(false);
const deletingId = ref<number | null>(null);

const columns = computed<ColumnDef[]>(() => [
  { key: 'user', label: 'Staff Member' },
  { key: 'task', label: 'Duty Task' },
  { key: 'shift_date', label: 'Shift Date' },
  { key: 'time', label: 'Time Window' },
  { key: 'status', label: langStore.t('common.status', 'Status') },
  { key: 'actions', label: langStore.t('common.actions', 'Actions'), align: 'right' },
]);

const form = reactive({
  user_id: 2,
  task_key: 'book_circulation',
  shift_date: new Date().toISOString().split('T')[0],
  start_time: '08:00',
  end_time: '17:00',
  notes: '',
});

const formatDate = (d: string) => {
  if (!d) return '—';
  return new Date(d).toLocaleDateString(langStore.currentLanguage === 'am' ? 'am-ET' : 'en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
  });
};

const confirmDelete = (s: DutySchedule) => {
  deletingId.value = s.id;
  showDelete.value = true;
};

const handleCreate = async () => {
  const success = await scheduleStore.createSchedule({ ...form });
  if (success) {
    showCreate.value = false;
  }
};

const handleDelete = async () => {
  if (deletingId.value) {
    await scheduleStore.deleteSchedule(deletingId.value);
    showDelete.value = false;
  }
};

const handlePageChange = (page: number) => {
  scheduleStore.fetchSchedules({
    date: dateFilter.value || undefined,
    task_key: taskFilter.value || undefined,
    page,
  });
};

watch([dateFilter, taskFilter], () => {
  scheduleStore.fetchSchedules({
    date: dateFilter.value || undefined,
    task_key: taskFilter.value || undefined,
  });
});

onMounted(() => scheduleStore.fetchSchedules());
</script>
