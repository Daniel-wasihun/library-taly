<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Activity, TrendingUp, AlertCircle, Trash2 } from 'lucide-vue-next';
import { useAttendanceStore } from '@/stores/attendanceStore';
import { useLibraryStore } from '@/stores/libraryStore';
import { useLanguageStore } from '@/stores/languageStore';
import { useAuthStore } from '@/stores/authStore';
import { useToastStore } from '@/stores/toastStore';
import Modal from '@/components/ui/Modal.vue';
import Button from '@/components/ui/Button.vue';
import type { Attendance } from '@/types';

// Sub-components
import AttendanceHeader from './components/AttendanceHeader.vue';
import AttendanceCounter from './components/AttendanceCounter.vue';
import AttendanceMonitor from './components/AttendanceMonitor.vue';
import AttendanceAnalytics from './components/AttendanceAnalytics.vue';

const attendanceStore = useAttendanceStore();
const libraryStore = useLibraryStore();
const langStore = useLanguageStore();
const authStore = useAuthStore();
const toast = useToastStore();

const activeTab = ref<'counter' | 'analytics'>('counter');
const selectedLibrary = ref<number | null>(null);
const selectedUserType = ref<'student' | 'teacher' | 'staff'>('student');

const showNoLibraryModal = ref(false);
const showDeleteConfirm = ref(false);
const logToDelete = ref<Attendance | null>(null);

const todayStr = new Date().toISOString().split('T')[0];
const dateRange = ref({
  from: todayStr,
  to: todayStr,
});

const libraryOptions = computed(() => {
  const assignedId = authStore.user?.library_id;
  const canManageAll = authStore.isAdmin || authStore.user?.can_manage_all_libraries;

  let filtered = libraryStore.libraries;
  if (!canManageAll) {
    if (assignedId) {
      filtered = libraryStore.libraries.filter((l) => l.id === assignedId);
    } else {
      return [];
    }
  }

  return filtered.map((l) => ({
    value: Number(l.id),
    label: `[${l.code}] ${langStore.localizeBranch(l)}`,
  }));
});

const dailyStats = computed(() => {
  const statsFromStore = attendanceStore.stats;
  const dist = statsFromStore?.library_distribution || [];

  const stats = {
    male: statsFromStore?.total_male ?? 0,
    female: statsFromStore?.total_female ?? 0,
    total: statsFromStore?.total_users ?? 0,
    student: 0,
    teacher: 0,
    staff: 0,
  };

  dist.forEach((d: any) => {
    const count = parseInt(String(d.count || 0), 10) || 0;
    const type = d.user_type?.toLowerCase();

    if (type === 'student') stats.student += count;
    else if (type === 'teacher') stats.teacher += count;
    else if (type === 'staff') stats.staff += count;
  });

  return stats;
});

const distributionReport = computed(() => {
  const dist = attendanceStore.stats?.library_distribution || [];
  const types = ['student', 'teacher', 'staff'];

  return types.map((type) => {
    const row: any = { type, male: 0, female: 0, total: 0 };
    dist.forEach((d: any) => {
      if (d.user_type?.toLowerCase() === type) {
        const count = parseInt(String(d.count || 0), 10) || 0;
        if (d.gender?.toLowerCase() === 'male') row.male += count;
        else if (d.gender?.toLowerCase() === 'female') row.female += count;
      }
    });
    row.total = row.male + row.female;
    return row;
  });
});

const statsBreakdown = computed(() => {
  const s = dailyStats.value;
  const malePercent = s.total > 0 ? Math.round((s.male / s.total) * 100) : 50;
  const femalePercent = s.total > 0 ? Math.round((s.female / s.total) * 100) : 50;
  return { malePercent, femalePercent };
});

const tabs = computed(() => [
  { id: 'counter', label: langStore.t('attendance.live_desk', 'Live Counter Desk'), icon: Activity },
  { id: 'analytics', label: langStore.t('attendance.analytics', 'Analytics & Reporting'), icon: TrendingUp },
]);

const canDeleteEntry = (entryAt: string) => {
  if (authStore.isAdmin) return true;
  if (!entryAt) return false;
  const diffMinutes = Math.abs(Date.now() - new Date(entryAt).getTime()) / 60000;
  return diffMinutes <= 2;
};

const handleLog = async (gender: 'male' | 'female', count = 1) => {
  if (!selectedLibrary.value) {
    showNoLibraryModal.value = true;
    return;
  }

  try {
    const res = await attendanceStore.logEntry(
      selectedLibrary.value,
      selectedUserType.value,
      gender,
      count
    );
    if (res?.success) {
      toast.success(`Logged ${count} ${langStore.localizeGender(gender)} ${langStore.localizeType(selectedUserType.value)}`);
    }
  } catch {
    // handled in store
  }
};

const handleUpdateCount = async (log: Attendance, delta: number) => {
  const newCount = log.visitor_count + delta;
  if (newCount < 1 || newCount > 100) return;
  await attendanceStore.updateLogCount(log.slug, newCount);
};

const openDeleteConfirm = (log: Attendance) => {
  logToDelete.value = log;
  showDeleteConfirm.value = true;
};

const confirmDelete = async () => {
  if (!logToDelete.value) return;
  const success = await attendanceStore.deleteLog(logToDelete.value.slug);
  if (success) {
    showDeleteConfirm.value = false;
    logToDelete.value = null;
  }
};

const handleExport = (range: 'day' | 'week' | 'month' | 'current') => {
  const now = new Date();
  let from = todayStr;
  let to = todayStr;

  if (range === 'week') {
    const pastWeek = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
    from = pastWeek.toISOString().split('T')[0];
  } else if (range === 'month') {
    const pastMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    from = pastMonth.toISOString().split('T')[0];
  } else if (range === 'current') {
    from = dateRange.value.from;
    to = dateRange.value.to;
  }

  attendanceStore.exportLogs({
    library_id: selectedLibrary.value || undefined,
    date_from: from,
    date_to: to,
  });
};

/**
 * Professional backend printable report generator
 */
const handlePrint = (range: 'day' | 'week' | 'month' | 'current' = 'current') => {
  let from = dateRange.value.from;
  let to = dateRange.value.to;
  if (range === 'day' || activeTab.value === 'counter') {
    from = todayStr;
    to = todayStr;
  }
  const token = authStore.token || localStorage.getItem('auth_token') || '';
  const lang = langStore.currentLanguage;
  const libId = selectedLibrary.value ? `&library_id=${selectedLibrary.value}` : '';
  const url = `/api/physical/attendance/report/print?date_from=${from}&date_to=${to}&lang=${lang}&token=${encodeURIComponent(token)}${libId}`;
  window.open(url, '_blank');
};

const refreshStats = async () => {
  attendanceStore.filters.date_from = dateRange.value.from;
  attendanceStore.filters.date_to = dateRange.value.to;
  attendanceStore.filters.library_id = selectedLibrary.value || '';
  await attendanceStore.fetchStats(true);
};

const resetDateRange = () => {
  dateRange.value = { from: todayStr, to: todayStr };
  refreshStats();
};

onMounted(async () => {
  // Use cached library list
  await libraryStore.fetchLibraries(undefined, false);

  // Set default library branch selection
  const assignedId = authStore.user?.library_id;
  if (!authStore.user?.can_manage_all_libraries && assignedId) {
    selectedLibrary.value = Number(assignedId);
  } else if (libraryStore.libraries.length > 0 && !selectedLibrary.value) {
    selectedLibrary.value = Number(libraryStore.libraries[0].id);
  }

  attendanceStore.filters.library_id = selectedLibrary.value || '';
  attendanceStore.filters.date_from = todayStr;
  attendanceStore.filters.date_to = todayStr;

  // Use cached logs and stats
  await Promise.all([
    attendanceStore.fetchLogs(1, undefined, false),
    attendanceStore.fetchStats(false),
  ]);
});

watch(selectedLibrary, (newVal) => {
  attendanceStore.filters.library_id = newVal ? String(newVal) : '';
  if (activeTab.value === 'counter') {
    attendanceStore.fetchLogs(1, undefined, true);
  }
  attendanceStore.fetchStats(true);
});
</script>

<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Modularized Header -->
    <AttendanceHeader
      v-model:selectedLibrary="selectedLibrary"
      v-model:selectedUserType="selectedUserType"
      v-model:activeTab="activeTab"
      :libraryOptions="libraryOptions"
      :tabs="tabs"
    />

    <!-- Active Tab 1: Live Counter Desk -->
    <div v-if="activeTab === 'counter'" class="grid grid-cols-1 lg:grid-cols-12 gap-4">
      <AttendanceCounter
        :selectedLibrary="selectedLibrary"
        :selectedUserType="selectedUserType"
        @log="handleLog"
      />

      <AttendanceMonitor
        :dailyStats="dailyStats"
        :logs="attendanceStore.logs"
        :loading="attendanceStore.loading"
        :canDeleteEntry="canDeleteEntry"
        @updateCount="handleUpdateCount"
        @delete="openDeleteConfirm"
        @export="handleExport('day')"
        @print="handlePrint('day')"
      />
    </div>

    <!-- Active Tab 2: Analytics & Reporting -->
    <AttendanceAnalytics
      v-else
      v-model:dateRange="dateRange"
      :dailyStats="dailyStats"
      :distributionReport="distributionReport"
      :malePercent="statsBreakdown.malePercent"
      :femalePercent="statsBreakdown.femalePercent"
      :trends="attendanceStore.stats?.trends || []"
      :statsLoading="attendanceStore.statsLoading"
      @refresh="refreshStats"
      @clear="resetDateRange"
      @export="handleExport"
      @print="handlePrint('current')"
    />

    <!-- Missing Library Selection Modal -->
    <Modal
      :show="showNoLibraryModal"
      :title="langStore.t('attendance.select_library', 'Branch Selection Required')"
      :icon="AlertCircle"
      size="sm"
      @close="showNoLibraryModal = false"
    >
      <div class="p-4 text-center space-y-3">
        <p class="text-xs text-main-text/70 leading-relaxed">
          {{ langStore.t('attendance.branch_required_msg', 'Please select a campus library branch from the top dropdown before recording live visitors.') }}
        </p>
        <Button variant="primary" class="w-full" size="sm" @click="showNoLibraryModal = false">
          {{ langStore.t('common.close', 'Understood') }}
        </Button>
      </div>
    </Modal>

    <!-- Delete Confirmation Modal with 2-minute safety check -->
    <Modal
      :show="showDeleteConfirm"
      :title="langStore.t('attendance.cancel_entry', 'Cancel Entry')"
      size="sm"
      @close="showDeleteConfirm = false"
    >
      <div class="text-center py-2 space-y-3">
        <div class="w-12 h-12 rounded-full mx-auto flex items-center justify-center bg-rose-500/10 text-rose-600 border border-rose-500/20">
          <Trash2 class="w-6 h-6 stroke-[2.2]" />
        </div>
        <p class="text-xs text-main-text">
          {{ langStore.t('attendance.confirm_cancel', 'Are you sure you want to cancel this entry?') }}
        </p>
        <p class="text-xs font-mono font-bold text-main-text/70" v-if="logToDelete">
          +{{ logToDelete.visitor_count }} {{ langStore.localizeGender(logToDelete.gender) }} · {{ langStore.localizeType(logToDelete.user_type) }}
        </p>
      </div>
      <template #footer>
        <Button variant="secondary" size="sm" @click="showDeleteConfirm = false">
          {{ langStore.t('common.cancel', 'Cancel') }}
        </Button>
        <Button variant="danger" size="sm" @click="confirmDelete">
          {{ langStore.t('attendance.confirm_delete', 'Confirm Deletion') }}
        </Button>
      </template>
    </Modal>
  </div>
</template>
