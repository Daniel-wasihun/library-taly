<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { Activity, TrendingUp, AlertCircle, Trash2 } from 'lucide-vue-next';
import { useAttendanceStore } from '@/stores/attendanceStore';
import { useLibraryStore } from '@/stores/libraryStore';
import { useLanguageStore } from '@/stores/languageStore';
import { useAuthStore } from '@/stores/authStore';
import { useToastStore } from '@/stores/toastStore';
import Modal from '@/components/ui/Modal.vue';
import Button from '@/components/ui/Button.vue';
import api from '@/api/client';
import type { Attendance } from '@/types';
import { toEthiopian, toGregorian } from '@/utils/ethiopianDate';

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

// Shift duty authorization state
const dutyStatus = ref<{
  allowed: boolean;
  reason: string;
  code?: string;
  task?: string;
  task_name?: string;
  shift?: any;
  next_turn?: string | null;
} | null>(null);
const checkingDuty = ref(false);

const checkDutyStatus = async () => {
  if (authStore.isAdmin || authStore.isSuperAdmin) {
    dutyStatus.value = {
      allowed: true,
      reason: 'Administrative override granted.',
    };
    return;
  }

  if (!selectedLibrary.value) {
    dutyStatus.value = null;
    return;
  }

  try {
    checkingDuty.value = true;
    const res = await api.get('/schedules/check-duty', {
      params: {
        task_key: 'attendance_desk',
        library_id: selectedLibrary.value,
      },
    });
    dutyStatus.value = res.data?.duty_check || null;
  } catch (err: any) {
    dutyStatus.value = {
      allowed: false,
      reason: err?.response?.data?.message || 'Unable to verify duty schedule.',
      code: err?.response?.data?.code || 'OUTSIDE_DUTY_TURN',
    };
  } finally {
    checkingDuty.value = false;
  }
};

// Local ISO date formatter avoiding UTC midnight offset bugs
const toLocalIso = (d: Date = new Date()): string => {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

// Real-time ticking clock for exact 2-minute expiration
const currentTime = ref(Date.now());
let timerInterval: any = null;

const todayStr = toLocalIso(new Date());
const dateRange = ref({
  from: todayStr,
  to: todayStr,
});

const libraryOptions = computed(() => {
  const assignedId = authStore.user?.library_id;
  const canManageAll = authStore.canManageAllLibraries;

  let filtered = libraryStore.libraries;
  if (!canManageAll && assignedId) {
    filtered = libraryStore.libraries.filter((l) => Number(l.id) === Number(assignedId));
  }

  const options: { value: number | null; label: string }[] = [];

  // Provide General Library (Overall System) option for users who can manage all libraries or when on analytics tab
  if (canManageAll) {
    options.push({
      value: null,
      label: langStore.isAmharic()
        ? '🏛️ አጠቃላይ ቤተ-መጻሕፍት (ሁሉም ቅርንጫፎች / አጠቃላይ ትንተና)'
        : '🏛️ General Library (All Branches / Overall Analytics)',
    });
  }

  filtered.forEach((l) => {
    options.push({
      value: Number(l.id),
      label: `[${l.code}] ${langStore.localizeBranch(l)}`,
    });
  });

  return options;
});

const dailyStats = computed(() => {
  const statsFromStore = attendanceStore.stats;
  const kpis = statsFromStore?.kpis;
  const dist = statsFromStore?.library_distribution || [];

  const stats = {
    male: statsFromStore?.total_male ?? 0,
    female: statsFromStore?.total_female ?? 0,
    total: statsFromStore?.total_users ?? 0,
    student: kpis?.student ?? 0,
    teacher: kpis?.teacher ?? 0,
    staff: kpis?.staff ?? 0,
  };

  if (stats.student === 0 && stats.teacher === 0 && stats.staff === 0 && dist.length > 0) {
    dist.forEach((d: any) => {
      const count = parseInt(String(d.count || 0), 10) || 0;
      const type = d.user_type?.toLowerCase();

      if (type === 'student') stats.student += count;
      else if (type === 'teacher') stats.teacher += count;
      else if (type === 'staff') stats.staff += count;
    });
  }

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

const tabs = computed(() => {
  const list = [
    { id: 'counter', label: langStore.t('attendance.live_desk', 'Live Counter Desk'), icon: Activity },
  ];
  if (authStore.canManageReporting) {
    list.push({ id: 'analytics', label: langStore.t('attendance.analytics', 'Analytics & Reporting'), icon: TrendingUp });
  }
  return list;
});

// Guard: if user cannot manage reporting, ensure active tab stays on counter
watch(
  () => authStore.canManageReporting,
  (canReport) => {
    if (!canReport && activeTab.value === 'analytics') {
      activeTab.value = 'counter';
    }
  },
  { immediate: true }
);

/**
 * 2-minute modification and deletion rule:
 * Admin / Super Admin can modify or delete at any time.
 * Attendants are strictly disabled after 2 minutes from entry_at.
 */
const canModifyEntry = (entryAt: string) => {
  if (authStore.isAdmin || authStore.isSuperAdmin) return true;
  if (!entryAt) return false;
  const entryMs = new Date(entryAt).getTime();
  const diffMinutes = Math.abs(currentTime.value - entryMs) / 60000;
  return diffMinutes <= 2;
};

const canDeleteEntry = (entryAt: string) => canModifyEntry(entryAt);
const canUpdateEntry = (entryAt: string) => canModifyEntry(entryAt);

const handleLog = async (gender: 'male' | 'female', count = 1) => {
  if (!selectedLibrary.value) {
    showNoLibraryModal.value = true;
    return;
  }

  // Pre-flight check: ensure active shift duty
  if (!authStore.isAdmin && !authStore.isSuperAdmin && dutyStatus.value && !dutyStatus.value.allowed) {
    toast.error(dutyStatus.value.reason || 'You can only record attendance during your assigned shift date and time.');
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
    from = toLocalIso(pastWeek);
  } else if (range === 'month') {
    if (langStore.isAmharic()) {
      const [ey, em] = toEthiopian(now.getFullYear(), now.getMonth() + 1, now.getDate());
      const [gy, gm, gd] = toGregorian(ey, em, 1);
      from = `${gy}-${String(gm).padStart(2, '0')}-${String(gd).padStart(2, '0')}`;
    } else {
      from = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
    }
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
  if (!authStore.canManageReporting) return;
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
  timerInterval = setInterval(() => {
    currentTime.value = Date.now();
    // Periodic duty turn verification every 30s
    if (Math.floor(currentTime.value / 1000) % 30 === 0) {
      checkDutyStatus();
    }
  }, 5000);

  // Use cached library list
  await libraryStore.fetchLibraries(undefined, false);

  // Set default library branch selection
  const assignedId = authStore.user?.library_id;
  if (!authStore.user?.can_manage_all_libraries && assignedId) {
    selectedLibrary.value = Number(assignedId);
  } else if (libraryStore.libraries.length > 0 && !selectedLibrary.value) {
    selectedLibrary.value = Number(libraryStore.libraries[0].id);
  }

  // Verify shift duty for selected library branch
  await checkDutyStatus();

  attendanceStore.filters.library_id = selectedLibrary.value || '';
  attendanceStore.filters.date_from = todayStr;
  attendanceStore.filters.date_to = todayStr;

  // Fetch logs, and only fetch stats if authorized and on analytics tab
  const initPromises: Promise<any>[] = [
    attendanceStore.fetchLogs(1, undefined, false),
  ];
  if (authStore.canManageReporting && activeTab.value === 'analytics') {
    initPromises.push(attendanceStore.fetchStats(false));
  }
  await Promise.all(initPromises);
});

onUnmounted(() => {
  if (timerInterval) {
    clearInterval(timerInterval);
  }
});

watch(selectedLibrary, (newVal) => {
  attendanceStore.filters.library_id = newVal ? String(newVal) : '';
  checkDutyStatus();
  if (activeTab.value === 'counter') {
    attendanceStore.fetchLogs(1, undefined, true);
  }
  if (authStore.canManageReporting && activeTab.value === 'analytics') {
    attendanceStore.fetchStats(true);
  }
});

watch(activeTab, (newTab) => {
  if (newTab === 'analytics' && authStore.canManageReporting) {
    attendanceStore.filters.library_id = selectedLibrary.value ? String(selectedLibrary.value) : '';
    attendanceStore.fetchStats(true);
  } else if (newTab === 'counter') {
    // Live counter requires a physical branch desk
    if (!selectedLibrary.value) {
      const assignedId = authStore.user?.library_id;
      if (assignedId) {
        selectedLibrary.value = Number(assignedId);
      } else if (libraryStore.libraries.length > 0) {
        selectedLibrary.value = Number(libraryStore.libraries[0].id);
      }
    }
    attendanceStore.fetchLogs(1, undefined, true);
  }
});

watch(
  () => langStore.currentLanguage,
  () => {
    if (activeTab.value === 'analytics' && authStore.canManageReporting) {
      attendanceStore.fetchStats(true);
    }
  }
);
</script>

<template>
  <div class="space-y-3.5 sm:space-y-5 w-full">
    <!-- Modularized Header -->
    <AttendanceHeader
      v-model:selectedLibrary="selectedLibrary"
      v-model:activeTab="activeTab"
      :libraryOptions="libraryOptions"
      :tabs="tabs"
    />

    <!-- Active Tab 1: Live Counter Desk -->
    <div v-if="activeTab === 'counter'" class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5">
      <AttendanceCounter
        :selectedLibrary="selectedLibrary"
        v-model:selectedUserType="selectedUserType"
        :dutyStatus="dutyStatus"
        @log="handleLog"
      />

      <AttendanceMonitor
        :dailyStats="dailyStats"
        :logs="attendanceStore.logs"
        :loading="attendanceStore.loading"
        :canDeleteEntry="canDeleteEntry"
        :canUpdateEntry="canUpdateEntry"
        @updateCount="handleUpdateCount"
        @delete="openDeleteConfirm"
        @export="handleExport('day')"
        @print="handlePrint('day')"
      />
    </div>

    <!-- Active Tab 2: Analytics & Reporting -->
    <AttendanceAnalytics
      v-else-if="activeTab === 'analytics' && authStore.canManageReporting"
      v-model:dateRange="dateRange"
      :selectedLibrary="selectedLibrary"
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

    <div
      v-else-if="activeTab === 'analytics' && !authStore.canManageReporting"
      class="p-8 text-center text-main-text/60 bg-white dark:bg-dark-card border border-border rounded-xl"
    >
      <p class="text-sm font-medium">
        {{ langStore.t('common.unauthorized', 'You do not have permission to view reporting and analytics.') }}
      </p>
    </div>

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
