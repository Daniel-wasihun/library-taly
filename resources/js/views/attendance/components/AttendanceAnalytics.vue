<script setup lang="ts">
import { ref, computed } from 'vue';
import {
  Calendar,
  RotateCcw,
  Download,
  Printer,
  BarChart3,
  Users,
  Clock,
  History,
  ArrowRight,
  FileSpreadsheet,
  FileText,
  FileCheck2,
  Building2,
  TrendingUp,
  TrendingDown,
  Layers,
  Sparkles,
  Award,
  ShieldCheck,
  CheckCircle2,
  Activity,
  Sun,
  Sunrise,
  Moon,
  AlertCircle,
  CalendarDays,
  Compass,
  Flame,
} from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import { useLanguageStore } from '@/stores/languageStore';
import { useAttendanceStore } from '@/stores/attendanceStore';
import { useLibraryStore } from '@/stores/libraryStore';
import { formatDT } from '@/utils/ethiopianDate';

const props = defineProps<{
  dailyStats: {
    total: number;
    student: number;
    teacher: number;
    staff: number;
    male: number;
    female: number;
  };
  distributionReport: any[];
  malePercent: number;
  femalePercent: number;
  trends: { date: string; count: number }[];
  dateRange: { from: string; to: string };
  statsLoading: boolean;
}>();

const emit = defineEmits<{
  (e: 'update:dateRange', value: { from: string; to: string }): void;
  (e: 'refresh'): void;
  (e: 'clear'): void;
  (e: 'export', range: 'day' | 'week' | 'month' | 'current'): void;
  (e: 'print'): void;
}>();

const langStore = useLanguageStore();
const attendanceStore = useAttendanceStore();
const libraryStore = useLibraryStore();

const downloadingFormat = ref<string | null>(null);
const selectedDowIndex = ref<number | null>(null);
const selectedOccDow = ref<number>(1); // 1 = Monday

const handleDateInput = (key: 'from' | 'to', event: Event) => {
  const val = (event.target as HTMLInputElement).value;
  emit('update:dateRange', { ...props.dateRange, [key]: val });
};

const getFormattedTrendDate = (dateStr: string) => {
  return formatDT(dateStr, langStore.isAmharic()).full;
};

// Preset range handlers
const setPresetRange = (preset: 'today' | 'week' | 'month' | 'all') => {
  const now = new Date();
  const todayStr = now.toISOString().split('T')[0];
  let from = todayStr;

  if (preset === 'week') {
    const pastWeek = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
    from = pastWeek.toISOString().split('T')[0];
  } else if (preset === 'month') {
    const pastMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    from = pastMonth.toISOString().split('T')[0];
  } else if (preset === 'all') {
    from = '2025-10-01';
  }

  emit('update:dateRange', { from, to: todayStr });
  emit('refresh');
};

const triggerDownload = async (format: 'xlsx' | 'doc' | 'csv' | 'pdf') => {
  downloadingFormat.value = format;
  try {
    await attendanceStore.downloadFormalReport(format);
  } finally {
    downloadingFormat.value = null;
  }
};

// Multi-perspective analytics computeds
const branchComparisonList = computed(() => {
  return attendanceStore.stats?.branch_comparison || [];
});

const shiftWindowsList = computed(() => {
  const sw = attendanceStore.stats?.shift_windows;
  if (!sw) return [];
  return Object.values(sw);
});

const staffActivityList = computed(() => {
  return attendanceStore.stats?.staff_activity || [];
});

const narrativeDescription = computed(() => {
  return attendanceStore.stats?.description || '';
});

const dayOfWeekList = computed(() => {
  return attendanceStore.stats?.day_of_week_analytics || [];
});

const maxDowAvg = computed(() => {
  if (!dayOfWeekList.value.length) return 1;
  return Math.max(...dayOfWeekList.value.map(d => d.daily_average), 1);
});

const intraMonthData = computed(() => {
  return attendanceStore.stats?.intra_month_analytics || null;
});

const currentOccDay = computed(() => {
  if (!intraMonthData.value) return null;
  return intraMonthData.value.occurrences_by_day.find(d => d.day_index === selectedOccDow.value) || intraMonthData.value.occurrences_by_day[0];
});

const monthlyList = computed(() => {
  return attendanceStore.stats?.monthly_analytics || [];
});

const maxMonthTotal = computed(() => {
  if (!monthlyList.value.length) return 1;
  return Math.max(...monthlyList.value.map(m => m.total), 1);
});

const yearlyList = computed(() => {
  return attendanceStore.stats?.yearly_analytics || [];
});

const shiftTimeData = computed(() => {
  return attendanceStore.stats?.shift_time_analytics || null;
});

const hourlyCurveList = computed(() => {
  return shiftTimeData.value?.hourly_curve || [];
});

const maxHourlyCount = computed(() => {
  if (!hourlyCurveList.value.length) return 1;
  return Math.max(...hourlyCurveList.value.map(h => h.count), 1);
});

const kpis = computed(() => {
  return attendanceStore.stats?.kpis || {
    total: props.dailyStats.total,
    male: props.dailyStats.male,
    female: props.dailyStats.female,
    male_percent: props.malePercent,
    female_percent: props.femalePercent,
    peak_hour: '10:00 – 11:00',
    peak_hour_count: 0,
    busiest_branch: 'Techno Library',
    dominant_patron: 'Students',
  };
});
</script>

<template>
  <div class="space-y-6 pb-8">
    <!-- Controls Bar with Gregorian Inputs + Ethiopian Calendar Overlay -->
    <div class="bg-card-bg border border-card-border rounded-md p-4 sm:p-5 flex flex-col lg:flex-row lg:items-end justify-between gap-4 shadow-2xs">
      <!-- Date Range Controls -->
      <div class="flex flex-col gap-2 flex-1 min-w-0 lg:max-w-[550px]">
        <div class="flex items-center justify-between text-[10px] font-bold text-main-text/60 uppercase">
          <div class="flex items-center gap-2">
            <Calendar class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.select_date_range', 'Analytical Date Window & Filter Scope') }}</span>
          </div>
          <!-- Quick Presets -->
          <div class="flex items-center gap-1.5 font-sans">
            <button
              type="button"
              @click="setPresetRange('today')"
              class="px-2 py-0.5 rounded text-[10px] font-semibold transition hover:bg-brand-blue/10 hover:text-brand-blue"
            >
              {{ langStore.t('common.today', 'Today') }}
            </button>
            <span class="text-main-text/30">·</span>
            <button
              type="button"
              @click="setPresetRange('week')"
              class="px-2 py-0.5 rounded text-[10px] font-semibold transition hover:bg-brand-blue/10 hover:text-brand-blue"
            >
              {{ langStore.t('common.week', '7 Days') }}
            </button>
            <span class="text-main-text/30">·</span>
            <button
              type="button"
              @click="setPresetRange('month')"
              class="px-2 py-0.5 rounded text-[10px] font-semibold transition hover:bg-brand-blue/10 hover:text-brand-blue"
            >
              {{ langStore.t('common.month', 'Month') }}
            </button>
          </div>
        </div>

        <div class="flex items-center gap-2 bg-main-bg border border-card-border p-2 rounded-md text-xs shadow-2xs">
          <input
            type="date"
            :value="dateRange.from"
            @input="handleDateInput('from', $event)"
            class="bg-transparent text-xs font-mono font-bold text-main-text focus:outline-none flex-1 min-w-0"
          />
          <ArrowRight class="w-3.5 h-3.5 text-main-text/40 shrink-0" />
          <input
            type="date"
            :value="dateRange.to"
            @input="handleDateInput('to', $event)"
            class="bg-transparent text-xs font-mono font-bold text-main-text focus:outline-none flex-1 min-w-0"
          />
        </div>

        <!-- Ethiopian Dates Subtext when Amharic is active -->
        <div v-if="langStore.isAmharic()" class="flex items-center gap-2 px-2 text-[11px] text-brand-blue font-medium truncate">
          <span class="flex-1 truncate">{{ formatDT(dateRange.from, true).date }}</span>
          <span class="w-6 text-center text-main-text/40">→</span>
          <span class="flex-1 truncate">{{ formatDT(dateRange.to, true).date }}</span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2 flex-wrap">
        <Button
          variant="secondary"
          size="sm"
          :icon="RotateCcw"
          :loading="statsLoading"
          @click="emit('refresh')"
        >
          {{ langStore.t('common.refresh', 'Apply & Refresh') }}
        </Button>
        <Button
          variant="secondary"
          size="sm"
          @click="emit('clear')"
        >
          {{ langStore.t('attendance.reset_today', 'Reset Today') }}
        </Button>
        <Button
          variant="primary"
          size="sm"
          :icon="Printer"
          @click="triggerDownload('pdf')"
          class="!bg-brand-blue text-white shadow-2xs"
        >
          {{ langStore.t('attendance.print_report', 'Print Official Report') }}
        </Button>
      </div>
    </div>

    <!-- Skeleton Loading State -->
    <div v-if="statsLoading" class="space-y-6 animate-pulse">
      <!-- Executive narrative skeleton -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 space-y-3">
        <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/4"></div>
        <div class="h-3 bg-slate-200 dark:bg-slate-700/60 rounded w-full"></div>
        <div class="h-3 bg-slate-200 dark:bg-slate-700/60 rounded w-5/6"></div>
      </div>

      <!-- 6 KPI Skeletons -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div v-for="i in 6" :key="i" class="bg-card-bg border border-card-border rounded-md p-4 space-y-2">
          <div class="h-2.5 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div>
          <div class="h-7 bg-slate-200 dark:bg-slate-700 rounded w-3/4"></div>
          <div class="h-2 bg-slate-200 dark:bg-slate-700/50 rounded w-1/3"></div>
        </div>
      </div>

      <!-- 2-Col Demographics & Branch Skeletons -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <div class="bg-card-bg border border-card-border rounded-md p-5 space-y-4">
          <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/3"></div>
          <div class="h-3 bg-slate-200 dark:bg-slate-700/60 rounded w-full"></div>
          <div class="space-y-2 pt-2">
            <div v-for="i in 4" :key="i" class="h-7 bg-slate-200 dark:bg-slate-700/40 rounded"></div>
          </div>
        </div>
        <div class="bg-card-bg border border-card-border rounded-md p-5 space-y-4">
          <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/3"></div>
          <div class="space-y-2 pt-2">
            <div v-for="i in 4" :key="i" class="h-7 bg-slate-200 dark:bg-slate-700/40 rounded"></div>
          </div>
        </div>
      </div>

      <!-- Day of Week Chart Skeleton -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 space-y-4">
        <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/4"></div>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
          <div v-for="i in 7" :key="i" class="p-3.5 rounded-md border border-card-border bg-main-bg/40 space-y-3">
            <div class="h-3 bg-slate-200 dark:bg-slate-700 rounded w-1/2"></div>
            <div class="h-6 bg-slate-200 dark:bg-slate-700 rounded w-3/4"></div>
            <div class="h-2 bg-slate-200 dark:bg-slate-700 rounded w-full"></div>
            <div class="h-2 bg-slate-200 dark:bg-slate-700/40 rounded w-2/3"></div>
          </div>
        </div>
      </div>

      <!-- Intra-month & Monthly Trajectory Skeletons -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 space-y-4">
        <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/3"></div>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
          <div class="lg:col-span-5 space-y-2">
            <div v-for="i in 4" :key="i" class="h-8 bg-slate-200 dark:bg-slate-700/50 rounded"></div>
          </div>
          <div class="lg:col-span-7 grid grid-cols-4 gap-2">
            <div v-for="i in 4" :key="i" class="h-20 bg-slate-200 dark:bg-slate-700/50 rounded"></div>
          </div>
        </div>
      </div>

      <!-- Shift Windows & Density Curve Skeleton -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 space-y-4">
        <div class="h-4 bg-slate-200 dark:bg-slate-700 rounded w-1/3"></div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div v-for="i in 3" :key="i" class="h-24 bg-slate-200 dark:bg-slate-700/50 rounded"></div>
        </div>
        <div class="h-24 bg-slate-200 dark:bg-slate-700/40 rounded w-full mt-2"></div>
      </div>
    </div>

    <!-- Active Loaded Analytics State -->
    <div v-else class="space-y-6">
      <!-- Executive Context & Formal Audit Narrative Banner -->
      <div v-if="narrativeDescription" class="bg-card-bg border-l-4 border-l-brand-blue border border-card-border rounded-md p-4 shadow-2xs space-y-1.5">
      <div class="flex items-center justify-between text-xs font-bold text-brand-blue uppercase tracking-wider">
        <span class="flex items-center gap-1.5">
          <ShieldCheck class="w-4 h-4 text-brand-blue" />
          {{ langStore.t('attendance.executive_summary', 'Executive Analytics Context & Institutional Scope') }}
        </span>
        <span class="text-[10px] font-mono opacity-60">REF-{{ attendanceStore.stats?.kpis?.total ?? 0 }}</span>
      </div>
      <p class="text-xs text-main-text/80 leading-relaxed text-justify">
        {{ narrativeDescription }}
      </p>
    </div>

    <!-- Perspective 1: Key Performance Metrics & Executive Indicators -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <!-- Total Visitors -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-main-text/50 uppercase tracking-wider">
          {{ langStore.t('attendance.total_label', 'Total Patrons') }}
        </span>
        <div class="text-2xl font-bold text-brand-blue font-mono mt-1">{{ dailyStats.total }}</div>
        <span class="text-[10px] text-main-text/50 mt-0.5 inline-block">{{ langStore.t('attendance.active_window', 'In period') }}</span>
      </div>

      <!-- Students -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
          {{ langStore.localizeType('student') }}
        </span>
        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono mt-1">{{ dailyStats.student }}</div>
        <span class="text-[10px] text-emerald-700/70 dark:text-emerald-300/70 mt-0.5 inline-block">
          {{ dailyStats.total > 0 ? Math.round((dailyStats.student / dailyStats.total) * 100) : 0 }}% {{ langStore.t('attendance.of_traffic', 'of total') }}
        </span>
      </div>

      <!-- Faculty / Teachers -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
          {{ langStore.localizeType('teacher') }}
        </span>
        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 font-mono mt-1">{{ dailyStats.teacher }}</div>
        <span class="text-[10px] text-blue-700/70 dark:text-blue-300/70 mt-0.5 inline-block">
          {{ dailyStats.total > 0 ? Math.round((dailyStats.teacher / dailyStats.total) * 100) : 0 }}% {{ langStore.t('attendance.of_traffic', 'of total') }}
        </span>
      </div>

      <!-- Administrative Staff -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider">
          {{ langStore.localizeType('staff') }}
        </span>
        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 font-mono mt-1">{{ dailyStats.staff }}</div>
        <span class="text-[10px] text-purple-700/70 dark:text-purple-300/70 mt-0.5 inline-block">
          {{ dailyStats.total > 0 ? Math.round((dailyStats.staff / dailyStats.total) * 100) : 0 }}% {{ langStore.t('attendance.of_traffic', 'of total') }}
        </span>
      </div>

      <!-- Male Visitors -->
      <div class="bg-brand-blue/5 border border-brand-blue/15 rounded-md p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-brand-blue uppercase tracking-wider">
          {{ langStore.localizeGender('male') }}
        </span>
        <div class="text-2xl font-bold text-brand-blue font-mono mt-1">
          {{ dailyStats.male }} <span class="text-xs font-normal opacity-75">({{ malePercent }}%)</span>
        </div>
        <span class="text-[10px] text-brand-blue/70 mt-0.5 inline-block">{{ langStore.t('attendance.recorded_patrons', 'Male count') }}</span>
      </div>

      <!-- Female Visitors -->
      <div class="bg-brand-yellow/8 border border-brand-yellow/25 rounded-md p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-amber-700 dark:text-brand-yellow uppercase tracking-wider">
          {{ langStore.localizeGender('female') }}
        </span>
        <div class="text-2xl font-bold text-brand-yellow font-mono mt-1">
          {{ dailyStats.female }} <span class="text-xs font-normal opacity-75">({{ femalePercent }}%)</span>
        </div>
        <span class="text-[10px] text-amber-700/70 dark:text-brand-yellow/70 mt-0.5 inline-block">{{ langStore.t('attendance.recorded_patrons', 'Female count') }}</span>
      </div>
    </div>

    <!-- Perspective 2 & 3: Demographic Segmentation & Multi-Branch Facilities Comparison -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Demographic Segmentation Matrix -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between">
          <h4 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <BarChart3 class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.demographic_breakdown', 'Patron Demographic Segmentation') }}</span>
          </h4>
          <span class="text-[10px] font-mono text-main-text/50">100% Normalized</span>
        </div>

        <!-- Gender Proportional Split Bar -->
        <div class="space-y-1.5">
          <div class="flex justify-between text-[11px] font-bold">
            <span class="text-brand-blue">{{ langStore.localizeGender('male') }}: {{ dailyStats.male }} ({{ malePercent }}%)</span>
            <span class="text-amber-600 dark:text-brand-yellow">{{ langStore.localizeGender('female') }}: {{ dailyStats.female }} ({{ femalePercent }}%)</span>
          </div>
          <div class="w-full h-3 rounded-full overflow-hidden flex bg-slate-100 dark:bg-slate-800 shadow-inner">
            <div class="bg-brand-blue h-full transition-all duration-700" :style="{ width: `${malePercent}%` }"></div>
            <div class="bg-brand-yellow h-full transition-all duration-700" :style="{ width: `${femalePercent}%` }"></div>
          </div>
        </div>

        <!-- Demographic Table -->
        <div class="overflow-x-auto pt-2">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-card-border text-[10px] uppercase text-main-text/50 font-bold">
                <th class="py-2">{{ langStore.t('attendance.user_type', 'Patron Type') }}</th>
                <th class="py-2 text-center text-brand-blue">{{ langStore.localizeGender('male') }}</th>
                <th class="py-2 text-center text-amber-600 dark:text-brand-yellow">{{ langStore.localizeGender('female') }}</th>
                <th class="py-2 text-right">{{ langStore.t('attendance.total_label', 'Total') }}</th>
                <th class="py-2 text-right">{{ langStore.t('attendance.share', 'Share') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-card-border/60">
              <tr v-for="row in distributionReport" :key="row.type">
                <td class="py-2.5 font-bold text-main-text capitalize">{{ langStore.localizeType(row.type) }}</td>
                <td class="py-2.5 text-center text-brand-blue font-mono font-semibold">{{ row.male }}</td>
                <td class="py-2.5 text-center text-amber-600 dark:text-brand-yellow font-mono font-semibold">{{ row.female }}</td>
                <td class="py-2.5 text-right font-bold text-main-text font-mono">{{ row.total }}</td>
                <td class="py-2.5 text-right font-mono text-main-text/60">
                  {{ dailyStats.total > 0 ? Math.round((row.total / dailyStats.total) * 100) : 0 }}%
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Multi-Branch Facility Comparison -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between">
          <h4 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <Building2 class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.branch_comparison', 'Campus Facilities Foot-Traffic') }}</span>
          </h4>
          <span class="text-[10px] font-mono text-main-text/50">3 Active Branches</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-card-border text-[10px] uppercase text-main-text/50 font-bold">
                <th class="py-2">{{ langStore.t('libraries.branch', 'Branch') }}</th>
                <th class="py-2 text-center">{{ langStore.localizeType('student') }}</th>
                <th class="py-2 text-center">{{ langStore.localizeType('teacher') }}</th>
                <th class="py-2 text-center">{{ langStore.localizeType('staff') }}</th>
                <th class="py-2 text-right">{{ langStore.t('attendance.total_label', 'Total') }}</th>
                <th class="py-2 text-right">{{ langStore.t('attendance.traffic_share', 'Share') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-card-border/60">
              <tr v-for="branch in branchComparisonList" :key="branch.id">
                <td class="py-2.5 font-bold text-main-text">
                  <div class="flex items-center gap-1.5">
                    <span class="px-1.5 py-0.2 rounded text-[10px] font-mono bg-slate-100 dark:bg-slate-800 text-main-text/70 border border-card-border">
                      {{ branch.code }}
                    </span>
                    <span class="truncate">{{ branch.name }}</span>
                  </div>
                </td>
                <td class="py-2.5 text-center font-mono">{{ branch.students }}</td>
                <td class="py-2.5 text-center font-mono">{{ branch.teachers ?? 0 }}</td>
                <td class="py-2.5 text-center font-mono">{{ branch.staff ?? 0 }}</td>
                <td class="py-2.5 text-right font-bold text-brand-blue dark:text-brand-yellow font-mono">{{ branch.total }}</td>
                <td class="py-2.5 text-right font-mono font-semibold text-main-text/70">{{ branch.percent }}%</td>
              </tr>
              <tr v-if="branchComparisonList.length === 0">
                <td colspan="6" class="py-4 text-center text-xs text-main-text/40">
                  {{ langStore.t('common.no_data', 'No branch facility records available.') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Perspective 4: Day-of-the-Week Influx Profile & Surge Prediction Graph -->
    <div class="bg-card-bg border border-card-border rounded-md p-5 sm:p-6 shadow-2xs space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-card-border pb-3">
        <div>
          <h4 class="text-sm font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <CalendarDays class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.day_of_week_title', 'Day-of-the-Week Influx Profile & Surge Prediction') }}</span>
          </h4>
          <p class="text-xs text-main-text/60 mt-0.5">
            {{ langStore.t('attendance.day_of_week_desc', 'Predict peak patron traffic days across Monday to Sunday to optimize study hall staffing, catalog desks, and crowd control.') }}
          </p>
        </div>
        <span class="text-[11px] font-mono font-semibold px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 text-main-text/70 border border-card-border self-start sm:self-auto">
          7-Day Longitudinal Profile
        </span>
      </div>

      <!-- Graph: 7 Days Column Bars (Monday to Sunday) -->
      <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-3">
        <div
          v-for="day in dayOfWeekList"
          :key="day.day_index"
          @click="selectedDowIndex = day.day_index"
          class="p-3.5 rounded-md border transition-all cursor-pointer flex flex-col justify-between gap-3 relative group"
          :class="[
            selectedDowIndex === day.day_index
              ? 'border-brand-blue dark:border-brand-yellow bg-brand-blue/5 dark:bg-brand-yellow/5 ring-1 ring-brand-blue/30'
              : 'border-card-border bg-main-bg/40 hover:border-brand-blue/40 hover:bg-card-bg'
          ]"
        >
          <!-- Top Tag & Peak Badge -->
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-main-text uppercase tracking-wide">{{ day.short_name }}</span>
            <span
              v-if="day.is_peak"
              class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/20"
              title="Peak Surge Day"
            >
              <Flame class="w-2.5 h-2.5" />
              PEAK
            </span>
            <span
              v-else
              class="text-[9px] font-mono uppercase px-1 py-0.2 rounded"
              :class="day.surge_level === 'HIGH' ? 'bg-amber-500/15 text-amber-600' : 'text-main-text/40'"
            >
              {{ day.surge_level }}
            </span>
          </div>

          <!-- Total & Average Numbers -->
          <div>
            <div class="text-lg font-bold font-mono text-brand-blue dark:text-brand-yellow leading-tight">
              {{ day.total.toLocaleString() }}
            </div>
            <div class="text-[10px] text-main-text/60 mt-0.5">
              <span>{{ langStore.t('attendance.daily_average', 'Avg') }}:</span>
              <strong class="font-mono text-main-text ml-1">{{ day.daily_average }}</strong>
            </div>
          </div>

          <!-- Comparative Graph Bar -->
          <div class="space-y-1.5">
            <div class="w-full h-2 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="day.is_peak ? 'bg-rose-500' : 'bg-brand-blue dark:bg-brand-yellow'"
                :style="{ width: `${Math.min(100, Math.round((day.daily_average / maxDowAvg) * 100))}%` }"
              ></div>
            </div>

            <!-- Diurnal Distribution Segment (Morn / Aft / Night) -->
            <div class="flex items-center justify-between text-[9px] font-mono text-main-text/50">
              <span>{{ day.percentage }}%</span>
              <span>M:{{ day.morning_share }}% N:{{ day.night_share }}%</span>
            </div>

            <!-- Patron Type Segmentation -->
            <div class="flex items-center justify-between text-[9px] font-mono pt-1 border-t border-card-border/40">
              <span class="text-emerald-600 dark:text-emerald-400 font-semibold" title="Students">S: {{ day.students }}</span>
              <span class="text-blue-600 dark:text-blue-400 font-semibold" title="Faculty/Teachers">T: {{ day.teachers ?? 0 }}</span>
              <span class="text-purple-600 dark:text-purple-400 font-semibold" title="Staff">St: {{ day.staff ?? 0 }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Administrative Predictive Guidance Banner -->
      <div class="p-3.5 rounded-md border border-card-border bg-slate-50 dark:bg-slate-800/50 flex items-start gap-3 text-xs">
        <Sparkles class="w-4 h-4 text-brand-blue dark:text-brand-yellow shrink-0 mt-0.5" />
        <div class="space-y-1">
          <div class="font-bold text-main-text">
            {{ langStore.t('attendance.admin_prediction', 'Administrative Prediction & Staffing Recommendation') }}:
          </div>
          <p class="text-main-text/70 leading-relaxed">
            {{ selectedDowIndex && dayOfWeekList.find(d => d.day_index === selectedDowIndex)?.prediction
              ? dayOfWeekList.find(d => d.day_index === selectedDowIndex)?.prediction
              : (dayOfWeekList.find(d => d.is_peak)?.prediction || langStore.t('attendance.day_of_week_desc')) }}
          </p>
        </div>
      </div>
    </div>

    <!-- Perspective 5: Intra-Month Weekly Cycles & Recurring Day Occurrences -->
    <div class="bg-card-bg border border-card-border rounded-md p-5 sm:p-6 shadow-2xs space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-card-border pb-3">
        <div>
          <h4 class="text-sm font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <Compass class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.intra_month_title', 'Intra-Month Weekly Cycles & Recurring Day Occurrences') }}</span>
          </h4>
          <p class="text-xs text-main-text/60 mt-0.5">
            {{ langStore.t('attendance.intra_month_desc', 'Compare recurring day positions (1st Monday vs 2nd Monday) and 4-week academic progress cycles to anticipate exam preparation rushes.') }}
          </p>
        </div>
        <span class="text-[11px] font-mono font-semibold px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 text-main-text/70 border border-card-border self-start sm:self-auto">
          Intra-Month Recurrence Matrix
        </span>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Left: 4-Week Progress Progression (Weeks 1 to 4) -->
        <div class="lg:col-span-5 p-4 rounded-md border border-card-border bg-main-bg/30 space-y-3">
          <div class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center justify-between">
            <span>{{ langStore.t('attendance.weekly_cycles', 'Weekly Cycles in Month') }}</span>
            <span class="text-[10px] font-mono text-main-text/50">4-Week Flow</span>
          </div>

          <div class="space-y-3">
            <div
              v-for="wk in (intraMonthData?.week_progression || [])"
              :key="wk.week"
              class="space-y-1.5"
            >
              <div class="flex items-center justify-between text-xs">
                <span class="font-bold text-main-text">{{ wk.label }}</span>
                <span class="font-mono font-bold text-brand-blue dark:text-brand-yellow">
                  {{ wk.total.toLocaleString() }} <span class="font-normal text-[10px] text-main-text/50">({{ wk.percentage }}%)</span>
                </span>
              </div>
              <div class="w-full h-2 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
                <div
                  class="bg-brand-blue dark:bg-brand-yellow h-full rounded-full transition-all duration-500"
                  :style="{ width: `${wk.percentage}%` }"
                ></div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Day Selector & 4 Occurrences Comparison (1st, 2nd, 3rd, 4th Occurrence) -->
        <div class="lg:col-span-7 p-4 rounded-md border border-card-border bg-main-bg/30 space-y-3">
          <!-- Day Tabs (Mon, Tue, Wed, Thu, Fri, Sat, Sun) -->
          <div class="flex items-center justify-between flex-wrap gap-1">
            <span class="text-xs font-bold text-main-text uppercase tracking-wider">
              {{ langStore.t('attendance.recurring_occurrences', 'Recurring Day Occurrences') }}
            </span>
            <div class="flex items-center gap-1 bg-card-bg p-0.5 rounded border border-card-border">
              <button
                v-for="day in (intraMonthData?.occurrences_by_day || [])"
                :key="day.day_index"
                type="button"
                @click="selectedOccDow = day.day_index"
                class="px-2 py-0.5 rounded text-[11px] font-bold transition cursor-pointer"
                :class="selectedOccDow === day.day_index
                  ? 'bg-brand-blue dark:bg-brand-yellow text-white dark:text-slate-900 shadow-2xs'
                  : 'text-main-text/70 hover:text-main-text hover:bg-main-bg'"
              >
                {{ day.day_name.substring(0, 3) }}
              </button>
            </div>
          </div>

          <!-- 4 Occurrences Cards for Selected Day -->
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            <div
              v-for="occ in (currentOccDay?.occurrences || [])"
              :key="occ.occurrence"
              class="p-3 rounded-md border bg-card-bg flex flex-col justify-between gap-2"
              :class="occ.is_peak ? 'border-rose-500/50 ring-1 ring-rose-500/20' : 'border-card-border'"
            >
              <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold text-main-text">{{ occ.label }}</span>
                <span
                  v-if="occ.is_peak"
                  class="px-1 py-0.2 rounded text-[8px] font-bold bg-rose-500/15 text-rose-600 dark:text-rose-400"
                >
                  SURGE
                </span>
              </div>
              <div>
                <div class="text-base font-bold font-mono text-brand-blue dark:text-brand-yellow">
                  {{ occ.total.toLocaleString() }}
                </div>
                <div class="text-[10px] text-main-text/50 font-mono mt-0.5">
                  {{ occ.percentage }}% of {{ currentOccDay?.day_name }}s
                </div>
              </div>
              <div class="w-full h-1.5 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
                <div
                  class="h-full rounded-full transition-all duration-500"
                  :class="occ.is_peak ? 'bg-rose-500' : 'bg-brand-blue dark:bg-brand-yellow'"
                  :style="{ width: `${occ.percentage}%` }"
                ></div>
              </div>
              <div class="flex items-center justify-between text-[8px] font-mono text-main-text/60 pt-0.5 border-t border-card-border/40">
                <span class="text-emerald-600 font-medium">S: {{ occ.students ?? 0 }}</span>
                <span class="text-blue-600 font-medium">T: {{ occ.teachers ?? 0 }}</span>
                <span class="text-purple-600 font-medium">St: {{ occ.staff ?? 0 }}</span>
              </div>
            </div>
          </div>

          <!-- Selected Day Prediction Guidance -->
          <div class="text-xs text-main-text/80 bg-card-bg p-2.5 rounded border border-card-border flex items-center gap-2">
            <AlertCircle class="w-4 h-4 text-brand-blue dark:text-brand-yellow shrink-0" />
            <span class="text-[11px] leading-tight">
              <strong>{{ currentOccDay?.day_name }}:</strong> {{ currentOccDay?.prediction }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Perspective 6: Longitudinal Month-over-Month & Multi-Year Growth Trajectory -->
    <div class="bg-card-bg border border-card-border rounded-md p-5 sm:p-6 shadow-2xs space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-card-border pb-3">
        <div>
          <h4 class="text-sm font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <TrendingUp class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.monthly_trends_title', 'Longitudinal Month-over-Month & Annual Growth Trajectory') }}</span>
          </h4>
          <p class="text-xs text-main-text/60 mt-0.5">
            {{ langStore.t('attendance.monthly_trends_desc', 'Monitor month-over-month influx fluctuations, seasonal university milestones, and multi-year adoption trends.') }}
          </p>
        </div>
        <span class="text-[11px] font-mono font-semibold px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 text-main-text/70 border border-card-border self-start sm:self-auto">
          Multi-Year Influx Model
        </span>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Left: Month-over-Month Interactive Bar Flow (8 Cols) -->
        <div class="lg:col-span-8 space-y-3">
          <div class="flex items-center justify-between text-xs font-bold text-main-text uppercase tracking-wider">
            <span>Month-by-Month Foot-Traffic Trajectory</span>
            <span class="text-[10px] font-mono text-main-text/50">{{ monthlyList.length }} Reporting Months</span>
          </div>

          <div class="space-y-2.5 max-h-[360px] overflow-y-auto pr-1">
            <div
              v-for="m in monthlyList"
              :key="m.month_key"
              class="p-2.5 rounded-md border border-card-border bg-main-bg/30 hover:bg-main-bg/60 transition flex flex-col gap-1.5"
            >
              <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-2 font-bold text-main-text">
                  <span>{{ m.month_label }}</span>
                  <span class="text-[10px] font-mono px-1.5 py-0.2 rounded bg-slate-100 dark:bg-slate-800 text-main-text/60 border border-card-border">
                    {{ m.ethiopian_month }}
                  </span>
                </div>
                <div class="flex items-center gap-3">
                  <span
                    v-if="m.growth_rate && m.growth_rate !== '—'"
                    class="text-[10px] font-mono font-bold px-1.5 py-0.2 rounded flex items-center gap-0.5"
                    :class="m.growth_direction === 'up' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/15 text-rose-600 dark:text-rose-400'"
                  >
                    <component :is="m.growth_direction === 'up' ? TrendingUp : TrendingDown" class="w-3 h-3" />
                    {{ m.growth_rate }}
                  </span>
                  <span class="font-mono font-bold text-brand-blue dark:text-brand-yellow">
                    {{ m.total.toLocaleString() }} <span class="font-normal text-[10px] text-main-text/50">{{ langStore.t('attendance.patrons', 'patrons') }}</span>
                  </span>
                </div>
              </div>

              <!-- Bar Line -->
              <div class="w-full h-2 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
                <div
                  class="bg-brand-blue dark:bg-brand-yellow h-full rounded-full transition-all duration-500"
                  :style="{ width: `${Math.min(100, Math.round((m.total / maxMonthTotal) * 100))}%` }"
                ></div>
              </div>

              <div class="flex items-center justify-between text-[10px] text-main-text/60 font-mono flex-wrap gap-1">
                <span>{{ langStore.t('attendance.operating_days', 'Days') }}: {{ m.operating_days }} · {{ langStore.t('attendance.daily_average', 'Daily Avg') }}: {{ m.daily_average }}</span>
                <span class="flex items-center gap-1.5 font-semibold">
                  <span class="text-emerald-600 dark:text-emerald-400">S:{{ m.students }}</span>
                  <span class="text-blue-600 dark:text-blue-400">T:{{ m.teachers ?? 0 }}</span>
                  <span class="text-purple-600 dark:text-purple-400">St:{{ m.staff ?? 0 }}</span>
                </span>
                <span>{{ langStore.t('attendance.peak_day', 'Peak Day') }}: <strong>{{ m.peak_weekday }}</strong></span>
              </div>
            </div>
          </div>
        </div>

        <!-- Right: Year-over-Year Annual Growth Cards (4 Cols) -->
        <div class="lg:col-span-4 space-y-3">
          <div class="text-xs font-bold text-main-text uppercase tracking-wider">
            Year-over-Year Trajectory
          </div>

          <div class="space-y-3">
            <div
              v-for="yr in yearlyList"
              :key="yr.year"
              class="p-4 rounded-md border border-card-border bg-main-bg/40 space-y-2.5"
            >
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-base font-bold text-main-text">{{ yr.year }} G.C.</div>
                  <div class="text-[10px] font-mono text-main-text/50">({{ yr.ethiopian_year }} E.C.)</div>
                </div>
                <span
                  v-if="yr.growth_rate && yr.growth_rate !== '—'"
                  class="text-xs font-mono font-bold px-2 py-0.5 rounded flex items-center gap-1"
                  :class="yr.growth_rate.startsWith('+') ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/15 text-rose-600 dark:text-rose-400'"
                >
                  <TrendingUp class="w-3.5 h-3.5" />
                  {{ yr.growth_rate }} YoY
                </span>
              </div>

              <div class="grid grid-cols-2 gap-2 pt-2 border-t border-card-border">
                <div>
                  <div class="text-[10px] text-main-text/50 uppercase">{{ langStore.t('attendance.recorded_patrons', 'Annual Patrons') }}</div>
                  <div class="text-sm font-bold font-mono text-brand-blue dark:text-brand-yellow">
                    {{ yr.total.toLocaleString() }}
                  </div>
                </div>
                <div>
                  <div class="text-[10px] text-main-text/50 uppercase">{{ langStore.t('attendance.daily_average', 'Daily Average') }}</div>
                  <div class="text-sm font-bold font-mono text-main-text">
                    {{ yr.daily_average }} / day
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Perspective 7: Diurnal Shift Windows & 16-Hour Peak Foot-Traffic Curve -->
    <div class="bg-card-bg border border-card-border rounded-md p-5 sm:p-6 shadow-2xs space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-card-border pb-3">
        <div>
          <h4 class="text-sm font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <Sun class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.diurnal_shift_title', 'Diurnal Shift Windows & 16-Hour Peak Foot-Traffic Curve') }}</span>
          </h4>
          <p class="text-xs text-main-text/60 mt-0.5">
            {{ langStore.t('attendance.diurnal_shift_desc', 'Continuous diurnal density tracking across Morning (08:00–12:00), Afternoon (12:00–17:00), and Night (17:00–23:00) shift duty intervals.') }}
          </p>
        </div>
        <span class="text-[11px] font-mono font-semibold px-2.5 py-1 rounded bg-slate-100 dark:bg-slate-800 text-main-text/70 border border-card-border self-start sm:self-auto">
          Diurnal 08:00 – 23:00
        </span>
      </div>

      <!-- Upper Tier: 3 Shift Window Cards -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
        <!-- Morning -->
        <div
          v-if="shiftTimeData?.windows?.morning"
          class="p-4 rounded-md border border-card-border bg-main-bg/40 space-y-3"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="p-1.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400">
                <Sunrise class="w-4 h-4" />
              </div>
              <div>
                <div class="text-xs font-bold text-main-text">{{ shiftTimeData.windows.morning.title }}</div>
                <div class="text-[10px] font-mono text-main-text/50">{{ shiftTimeData.windows.morning.time_range }}</div>
              </div>
            </div>
            <div class="text-right">
              <div class="text-base font-bold font-mono text-brand-blue dark:text-brand-yellow">
                {{ shiftTimeData.windows.morning.count.toLocaleString() }}
              </div>
              <div class="text-[10px] font-semibold text-main-text/60 font-mono">
                {{ shiftTimeData.windows.morning.percent }}%
              </div>
            </div>
          </div>
          <div class="w-full h-2 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
            <div class="bg-amber-500 h-full rounded-full transition-all duration-500" :style="{ width: `${shiftTimeData.windows.morning.percent}%` }"></div>
          </div>
          <div class="flex items-center justify-between text-[10px] text-main-text/60">
            <span>
              {{ langStore.localizeType('student') }}: {{ shiftTimeData.windows.morning.students }} · 
              {{ langStore.localizeType('teacher') }}: {{ shiftTimeData.windows.morning.teachers ?? 0 }} · 
              {{ langStore.localizeType('staff') }}: {{ shiftTimeData.windows.morning.staff ?? 0 }}
            </span>
            <span class="font-bold text-main-text/80">{{ shiftTimeData.windows.morning.peak_hour || shiftTimeData.windows.morning.peak }}</span>
          </div>
        </div>

        <!-- Afternoon -->
        <div
          v-if="shiftTimeData?.windows?.afternoon"
          class="p-4 rounded-md border border-card-border bg-main-bg/40 space-y-3"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="p-1.5 rounded bg-blue-500/10 text-blue-600 dark:text-blue-400">
                <Sun class="w-4 h-4" />
              </div>
              <div>
                <div class="text-xs font-bold text-main-text">{{ shiftTimeData.windows.afternoon.title }}</div>
                <div class="text-[10px] font-mono text-main-text/50">{{ shiftTimeData.windows.afternoon.time_range }}</div>
              </div>
            </div>
            <div class="text-right">
              <div class="text-base font-bold font-mono text-brand-blue dark:text-brand-yellow">
                {{ shiftTimeData.windows.afternoon.count.toLocaleString() }}
              </div>
              <div class="text-[10px] font-semibold text-main-text/60 font-mono">
                {{ shiftTimeData.windows.afternoon.percent }}%
              </div>
            </div>
          </div>
          <div class="w-full h-2 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
            <div class="bg-blue-600 h-full rounded-full transition-all duration-500" :style="{ width: `${shiftTimeData.windows.afternoon.percent}%` }"></div>
          </div>
          <div class="flex items-center justify-between text-[10px] text-main-text/60">
            <span>
              {{ langStore.localizeType('student') }}: {{ shiftTimeData.windows.afternoon.students }} · 
              {{ langStore.localizeType('teacher') }}: {{ shiftTimeData.windows.afternoon.teachers ?? 0 }} · 
              {{ langStore.localizeType('staff') }}: {{ shiftTimeData.windows.afternoon.staff ?? 0 }}
            </span>
            <span class="font-bold text-main-text/80">{{ shiftTimeData.windows.afternoon.peak_hour || shiftTimeData.windows.afternoon.peak }}</span>
          </div>
        </div>

        <!-- Night -->
        <div
          v-if="shiftTimeData?.windows?.night"
          class="p-4 rounded-md border border-card-border bg-main-bg/40 space-y-3"
        >
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <div class="p-1.5 rounded bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                <Moon class="w-4 h-4" />
              </div>
              <div>
                <div class="text-xs font-bold text-main-text">{{ shiftTimeData.windows.night.title }}</div>
                <div class="text-[10px] font-mono text-main-text/50">{{ shiftTimeData.windows.night.time_range }}</div>
              </div>
            </div>
            <div class="text-right">
              <div class="text-base font-bold font-mono text-brand-blue dark:text-brand-yellow">
                {{ shiftTimeData.windows.night.count.toLocaleString() }}
              </div>
              <div class="text-[10px] font-semibold text-main-text/60 font-mono">
                {{ shiftTimeData.windows.night.percent }}%
              </div>
            </div>
          </div>
          <div class="w-full h-2 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
            <div class="bg-indigo-600 h-full rounded-full transition-all duration-500" :style="{ width: `${shiftTimeData.windows.night.percent}%` }"></div>
          </div>
          <div class="flex items-center justify-between text-[10px] text-main-text/60">
            <span>
              {{ langStore.localizeType('student') }}: {{ shiftTimeData.windows.night.students }} · 
              {{ langStore.localizeType('teacher') }}: {{ shiftTimeData.windows.night.teachers ?? 0 }} · 
              {{ langStore.localizeType('staff') }}: {{ shiftTimeData.windows.night.staff ?? 0 }}
            </span>
            <span class="font-bold text-main-text/80">{{ shiftTimeData.windows.night.peak_hour || shiftTimeData.windows.night.peak }}</span>
          </div>
        </div>
      </div>

      <!-- Lower Tier: 16-Hour Foot-Traffic Density Timeline Curve (08:00 to 23:00) -->
      <div class="p-4 rounded-md border border-card-border bg-main-bg/30 space-y-3">
        <div class="flex items-center justify-between text-xs font-bold text-main-text uppercase tracking-wider">
          <span class="flex items-center gap-1.5">
            <Activity class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            16-Hour Continuous Diurnal Density Curve
          </span>
          <span class="text-[10px] font-mono text-rose-600 dark:text-rose-400 bg-rose-500/10 px-2 py-0.5 rounded border border-rose-500/20 font-bold flex items-center gap-1">
            <Flame class="w-3 h-3" />
            Peak Hour: {{ shiftTimeData?.peak_hour || '10:00 – 11:00' }}
          </span>
        </div>

            <!-- Density Histogram Bars -->
        <div class="grid grid-cols-8 sm:grid-cols-16 gap-1.5 items-end pt-4 pb-1">
          <div
            v-for="h in hourlyCurveList"
            :key="h.hour"
            class="flex flex-col items-center gap-1.5 group cursor-pointer"
            :title="`${h.display_label || h.label || (h.hour + ':00')}: ${h.count} patrons (${h.percent}%)`"
          >
            <!-- Vertical Column -->
            <div class="w-full bg-slate-200 dark:bg-slate-700/60 rounded-t h-20 flex items-end overflow-hidden">
              <div
                class="w-full transition-all duration-500 rounded-t"
                :class="h.is_peak ? 'bg-rose-500' : 'bg-brand-blue/80 dark:bg-brand-yellow/80 group-hover:bg-brand-blue'"
                :style="{ height: `${Math.max(8, Math.round((h.count / maxHourlyCount) * 100))}%` }"
              ></div>
            </div>
            <!-- Hour label -->
            <span
              class="text-[9px] font-mono truncate"
              :class="h.is_peak ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-main-text/50'"
            >
              {{ (h.label || String(h.hour)).split(':')[0] }}h
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Attendant Duty Desk Operational Activity Log -->
    <div class="bg-card-bg border border-card-border rounded-md p-5 sm:p-6 shadow-2xs space-y-4">
      <div class="flex items-center justify-between border-b border-card-border pb-3">
        <h4 class="text-sm font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
          <Users class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('attendance.staff_activity', 'Attendant Duty Desk Operational Activity Log') }}</span>
        </h4>
        <span class="text-[11px] font-mono text-main-text/50 font-semibold">{{ staffActivityList.length }} Staff Members Active</span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead>
            <tr class="border-b border-card-border text-[10px] uppercase text-main-text/50 font-bold">
              <th class="py-2.5">{{ langStore.t('attendance.staff_name', 'Staff Member') }}</th>
              <th class="py-2.5">{{ langStore.t('attendance.branch', 'Facility') }}</th>
              <th class="py-2.5 text-center">{{ langStore.t('attendance.entries_logged', 'Logs') }}</th>
              <th class="py-2.5 text-right">{{ langStore.t('attendance.patrons_handled', 'Patrons') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-card-border/60">
            <tr v-for="staff in staffActivityList" :key="staff.staff_id">
              <td class="py-2.5 font-bold text-main-text truncate">
                {{ staff.name }}
                <span class="text-[10px] font-mono text-main-text/40 block">@{{ staff.username }}</span>
              </td>
              <td class="py-2.5 text-main-text/70 truncate">{{ staff.branch }}</td>
              <td class="py-2.5 text-center font-mono font-semibold">{{ staff.entries_count }}</td>
              <td class="py-2.5 text-right font-mono font-bold text-brand-blue dark:text-brand-yellow">{{ staff.visitors_handled }}</td>
            </tr>
            <tr v-if="staffActivityList.length === 0">
              <td colspan="4" class="py-4 text-center text-xs text-main-text/40">
                {{ langStore.t('common.no_data', 'No attendant activity recorded.') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Perspective 6 & Export Hub: Professional Download Hub & Exported Files List -->
    <div class="bg-card-bg border border-card-border rounded-md p-5 sm:p-6 shadow-2xs space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-card-border pb-4">
        <div>
          <h4 class="text-sm font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <Download class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.export_hub_title', 'Institutional Multi-Format Export Center') }}</span>
          </h4>
          <p class="text-xs text-main-text/60 mt-0.5">
            {{ langStore.t('attendance.export_hub_desc', 'Export authoritative audit files formatted for institutional review, spreadsheets, word processing, and archival records.') }}
          </p>
        </div>

        <!-- 4 Formal Export Buttons -->
        <div class="flex items-center gap-2 flex-wrap">
          <!-- Excel .xlsx -->
          <button
            type="button"
            @click="triggerDownload('xlsx')"
            :disabled="Boolean(downloadingFormat)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition shadow-2xs cursor-pointer disabled:opacity-50"
          >
            <FileSpreadsheet class="w-3.5 h-3.5" />
            <span>Excel (.xlsx)</span>
          </button>

          <!-- Word .doc -->
          <button
            type="button"
            @click="triggerDownload('doc')"
            :disabled="Boolean(downloadingFormat)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold bg-blue-700 hover:bg-blue-800 text-white transition shadow-2xs cursor-pointer disabled:opacity-50"
          >
            <FileText class="w-3.5 h-3.5" />
            <span>Word (.doc)</span>
          </button>

          <!-- CSV .csv -->
          <button
            type="button"
            @click="triggerDownload('csv')"
            :disabled="Boolean(downloadingFormat)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold bg-slate-700 hover:bg-slate-800 text-white transition shadow-2xs cursor-pointer disabled:opacity-50"
          >
            <FileCheck2 class="w-3.5 h-3.5" />
            <span>CSV (.csv)</span>
          </button>

          <!-- Official Print / PDF -->
          <button
            type="button"
            @click="triggerDownload('pdf')"
            :disabled="Boolean(downloadingFormat)"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold bg-brand-blue hover:bg-blue-900 text-white transition shadow-2xs cursor-pointer disabled:opacity-50"
          >
            <Printer class="w-3.5 h-3.5" />
            <span>PDF (Print)</span>
          </button>
        </div>
      </div>

      <!-- Exported File Analytics List -->
      <div class="space-y-3">
        <div class="flex items-center justify-between text-[11px] font-bold text-main-text/60 uppercase">
          <span class="flex items-center gap-1.5">
            <History class="w-3.5 h-3.5 text-brand-blue" />
            {{ langStore.t('attendance.exported_list_title', 'Exported Files & Document Vault Registry') }}
          </span>
          <span>{{ attendanceStore.exportHistory.length }} {{ langStore.t('attendance.files_ready', 'files available') }}</span>
        </div>

        <div class="divide-y divide-card-border/60 border border-card-border rounded-md overflow-hidden bg-main-bg/40">
          <div
            v-for="item in attendanceStore.exportHistory"
            :key="item.id"
            class="p-3 sm:px-4 flex items-center justify-between gap-3 hover:bg-card-bg transition"
          >
            <!-- File Info -->
            <div class="flex items-center gap-3 min-w-0">
              <div
                class="w-8 h-8 rounded flex items-center justify-center font-bold text-[10px] uppercase shrink-0 font-mono"
                :class="item.format === 'xlsx'
                  ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 border border-emerald-500/20'
                  : (item.format === 'doc'
                      ? 'bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/20'
                      : (item.format === 'csv'
                          ? 'bg-slate-500/10 text-slate-700 dark:text-slate-300 border border-slate-500/20'
                          : 'bg-rose-500/10 text-rose-700 dark:text-rose-300 border border-rose-500/20'))"
              >
                {{ item.format }}
              </div>
              <div class="min-w-0">
                <div class="text-xs font-bold text-main-text truncate flex items-center gap-2">
                  <span class="truncate">{{ item.name }}</span>
                  <span class="text-[9px] font-mono text-main-text/40 bg-card-bg px-1 rounded border border-card-border">{{ item.id }}</span>
                </div>
                <div class="text-[10px] text-main-text/50 flex items-center gap-2 mt-0.5">
                  <span>{{ item.scope }}</span>
                  <span>·</span>
                  <span>{{ item.size }}</span>
                  <span>·</span>
                  <span>{{ item.date }} at {{ item.timestamp }}</span>
                </div>
              </div>
            </div>

            <!-- Action button -->
            <button
              type="button"
              @click="triggerDownload(item.format)"
              class="px-2.5 py-1 rounded text-xs font-bold border border-card-border hover:bg-brand-blue hover:text-white hover:border-brand-blue transition cursor-pointer shrink-0"
              title="Download File"
            >
              {{ langStore.t('common.download', 'Download') }}
            </button>
          </div>
        </div>
      </div>
    </div>
    </div>
  </div>
</template>
