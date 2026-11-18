<template>
  <div class="space-y-5 w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
          <CalendarDays class="w-6 h-6 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('schedules.title', 'Daily Shift Schedules & Ethiopian Roster') }}</span>
        </h2>
        <p class="text-xs text-main-text/60 mt-0.5">
          {{ langStore.t('schedules.subtitle', 'Schedule duty shifts for groups (Group A, B...) across day & night shifts in the Ethiopian calendar.') }}
        </p>
      </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center gap-2 border-b border-card-border overflow-x-auto custom-scrollbar pb-px">
      <button
        type="button"
        class="flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-t-sm transition-all cursor-pointer whitespace-nowrap border-b-2"
        :class="activeTab === 'ethiopian_calendar'
          ? 'border-nav-accent text-nav-accent bg-nav-accent/5'
          : 'border-transparent text-main-text/60 hover:text-main-text hover:bg-card-bg'"
        @click="activeTab = 'ethiopian_calendar'"
      >
        <CalendarDays class="w-4 h-4" />
        <span>{{ langStore.t('schedules.tab_calendar', 'Ethiopian Monthly Scheduler') }}</span>
      </button>

      <button
        type="button"
        class="flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-t-sm transition-all cursor-pointer whitespace-nowrap border-b-2"
        :class="activeTab === 'groups'
          ? 'border-nav-accent text-nav-accent bg-nav-accent/5'
          : 'border-transparent text-main-text/60 hover:text-main-text hover:bg-card-bg'"
        @click="activeTab = 'groups'"
      >
        <Users class="w-4 h-4" />
        <span>{{ langStore.t('schedules.tab_groups', 'Shift Groups (Group A, B...)') }}</span>
      </button>

      <button
        type="button"
        class="flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-t-sm transition-all cursor-pointer whitespace-nowrap border-b-2"
        :class="activeTab === 'shifts'
          ? 'border-nav-accent text-nav-accent bg-nav-accent/5'
          : 'border-transparent text-main-text/60 hover:text-main-text hover:bg-card-bg'"
        @click="activeTab = 'shifts'"
      >
        <Clock class="w-4 h-4" />
        <span>{{ langStore.t('schedules.tab_shifts', 'Shift Definitions (Day / Night)') }}</span>
      </button>

      <button
        type="button"
        class="flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-t-sm transition-all cursor-pointer whitespace-nowrap border-b-2"
        :class="activeTab === 'holidays'
          ? 'border-nav-accent text-nav-accent bg-nav-accent/5'
          : 'border-transparent text-main-text/60 hover:text-main-text hover:bg-card-bg'"
        @click="activeTab = 'holidays'"
      >
        <CalendarOff class="w-4 h-4" />
        <span>{{ langStore.t('schedules.tab_holidays', 'Ethiopian Holidays (የበዓላት መዝገብ)') }}</span>
      </button>

      <button
        type="button"
        class="flex items-center gap-2 px-3.5 py-2 text-xs font-bold rounded-t-sm transition-all cursor-pointer whitespace-nowrap border-b-2"
        :class="activeTab === 'duty_logs'
          ? 'border-nav-accent text-nav-accent bg-nav-accent/5'
          : 'border-transparent text-main-text/60 hover:text-main-text hover:bg-card-bg'"
        @click="activeTab = 'duty_logs'"
      >
        <ClipboardList class="w-4 h-4" />
        <span>{{ langStore.t('schedules.tab_turns', 'Individual Duty Turns') }}</span>
      </button>
    </div>

    <!-- Tab 1: Ethiopian Monthly Calendar Scheduler -->
    <div v-if="activeTab === 'ethiopian_calendar'">
      <EthiopianShiftCalendar />
    </div>

    <!-- Tab 2: Shift Groups Management -->
    <div v-else-if="activeTab === 'groups'">
      <ShiftGroupsTab />
    </div>

    <!-- Tab 3: Shift Definitions CRUD -->
    <div v-else-if="activeTab === 'shifts'">
      <ShiftDefinitionsTab />
    </div>

    <!-- Tab 4: Ethiopian Holidays Manual Management -->
    <div v-else-if="activeTab === 'holidays'">
      <EthiopianHolidaysTab />
    </div>

    <!-- Tab 5: Individual Duty Turns & Logs -->
    <div v-else-if="activeTab === 'duty_logs'" class="space-y-4">
      <div class="flex flex-col sm:flex-row items-center justify-between gap-3">
        <div class="flex flex-col sm:flex-row gap-3 w-full sm:w-auto">
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

        <Button
          variant="primary"
          size="sm"
          :icon="Plus"
          @click="showCreateIndividual = true"
        >
          Assign Turn
        </Button>
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
            <div class="w-7 h-7 rounded-sm bg-nav-accent/10 text-nav-accent border border-nav-accent/25 flex items-center justify-center font-bold text-xs shrink-0">
              {{ (item.user?.name || '?').charAt(0).toUpperCase() }}
            </div>
            <div class="min-w-0">
              <p class="font-bold text-main-text truncate">{{ item.user?.name }}</p>
              <p class="text-[11px] text-main-text/50 font-mono truncate">@{{ item.user?.username }}</p>
            </div>
          </div>
        </template>

        <template #task="{ item }">
          <span class="inline-flex items-center px-2 py-0.5 rounded-sm text-[11px] font-semibold bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/25">
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
              @click="confirmDeleteIndividual(item)"
            >
              <Trash2 class="w-4 h-4 stroke-[2.2]" />
            </button>
          </div>
        </template>
      </DataTable>
    </div>

    <!-- Individual Turn Modal -->
    <Modal
      :show="showCreateIndividual"
      title="Assign Individual Duty Turn"
      description="Schedule a single turn for an individual staff member."
      :icon="CalendarDays"
      size="md"
      @close="showCreateIndividual = false"
    >
      <form @submit.prevent="handleCreateIndividual" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Staff Member User ID <span class="text-rose-500">*</span>
          </label>
          <input
            v-model.number="individualForm.user_id"
            type="number"
            min="1"
            required
            placeholder="Staff User ID"
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            Library Duty Task <span class="text-rose-500">*</span>
          </label>
          <select
            v-model="individualForm.task_key"
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
              v-model="individualForm.shift_date"
              type="date"
              required
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">Start Time</label>
            <input
              v-model="individualForm.start_time"
              type="time"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">End Time</label>
            <input
              v-model="individualForm.end_time"
              type="time"
              class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono"
            />
          </div>
        </div>
      </form>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showCreateIndividual = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button
          variant="primary"
          size="sm"
          :loading="scheduleStore.loading"
          @click="handleCreateIndividual"
        >
          Assign Turn
        </Button>
      </template>
    </Modal>

    <!-- Individual Turn Delete Modal -->
    <Modal
      :show="showDeleteIndividual"
      title="Cancel Duty Turn"
      size="sm"
      @close="showDeleteIndividual = false"
    >
      <div class="text-center py-2 space-y-3">
        <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center bg-rose-500/10 text-rose-600 border border-rose-500/20">
          <Trash2 class="w-6 h-6 stroke-[2.2]" />
        </div>
        <p class="text-xs text-main-text">
          Cancel and delete this assigned individual duty turn?
        </p>
      </div>

      <template #footer>
        <Button variant="secondary" size="sm" @click="showDeleteIndividual = false">
          Cancel
        </Button>
        <Button variant="danger" size="sm" @click="handleDeleteIndividual">
          Cancel Turn
        </Button>
      </template>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useScheduleStore } from '@/stores/scheduleStore';
import { useLanguageStore } from '@/stores/languageStore';
import EthiopianShiftCalendar from './components/EthiopianShiftCalendar.vue';
import ShiftGroupsTab from './components/ShiftGroupsTab.vue';
import ShiftDefinitionsTab from './components/ShiftDefinitionsTab.vue';
import EthiopianHolidaysTab from './components/EthiopianHolidaysTab.vue';
import Button from '@/components/ui/Button.vue';
import DataTable, { ColumnDef } from '@/components/ui/DataTable.vue';
import Modal from '@/components/ui/Modal.vue';
import StatusBadge from '@/components/ui/StatusBadge.vue';
import {
  CalendarDays,
  CalendarOff,
  Users,
  Clock,
  ClipboardList,
  Plus,
  Trash2,
} from 'lucide-vue-next';
import type { DutySchedule } from '@/types';

const scheduleStore = useScheduleStore();
const langStore = useLanguageStore();

const activeTab = ref<'ethiopian_calendar' | 'groups' | 'shifts' | 'duty_logs'>('ethiopian_calendar');

// Individual Turn Table State
const dateFilter = ref('');
const taskFilter = ref('');
const showCreateIndividual = ref(false);
const showDeleteIndividual = ref(false);
const deletingIndividualId = ref<number | null>(null);

const columns = computed<ColumnDef[]>(() => [
  { key: 'user', label: 'Staff Member' },
  { key: 'task', label: 'Duty Task' },
  { key: 'shift_date', label: 'Shift Date' },
  { key: 'time', label: 'Time Window' },
  { key: 'status', label: langStore.t('common.status', 'Status') },
  { key: 'actions', label: langStore.t('common.actions', 'Actions'), align: 'right' },
]);

const individualForm = reactive({
  user_id: 2,
  task_key: 'attendance_desk',
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

const confirmDeleteIndividual = (s: DutySchedule) => {
  deletingIndividualId.value = s.id;
  showDeleteIndividual.value = true;
};

const handleCreateIndividual = async () => {
  const success = await scheduleStore.createSchedule({ ...individualForm });
  if (success) {
    showCreateIndividual.value = false;
  }
};

const handleDeleteIndividual = async () => {
  if (deletingIndividualId.value) {
    await scheduleStore.deleteSchedule(deletingIndividualId.value);
    showDeleteIndividual.value = false;
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

onMounted(() => {
  scheduleStore.fetchSchedules();
});
</script>
