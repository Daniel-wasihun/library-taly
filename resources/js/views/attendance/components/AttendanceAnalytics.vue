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
  Layers,
  Sparkles,
  Award,
  ShieldCheck,
  CheckCircle2,
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
    from = '2026-09-01';
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

const kpis = computed(() => {
  return attendanceStore.stats?.kpis || {
    total: props.dailyStats.total,
    male: props.dailyStats.male,
    female: props.dailyStats.female,
    male_percent: props.malePercent,
    female_percent: props.femalePercent,
    peak_hour: '14:00 – 15:00',
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
                <th class="py-2 text-center">{{ langStore.t('attendance.faculty_staff', 'Faculty') }}</th>
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
                <td class="py-2.5 text-center font-mono">{{ branch.faculty_staff }}</td>
                <td class="py-2.5 text-right font-bold text-brand-blue dark:text-brand-yellow font-mono">{{ branch.total }}</td>
                <td class="py-2.5 text-right font-mono font-semibold text-main-text/70">{{ branch.percent }}%</td>
              </tr>
              <tr v-if="branchComparisonList.length === 0">
                <td colspan="5" class="py-4 text-center text-xs text-main-text/40">
                  {{ langStore.t('common.no_data', 'No branch facility records available.') }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Perspective 4 & 5: Operational Shift Windows & Duty Staff Registry -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Operational Shift Windows (Morning, Afternoon, Night) -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <h4 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
          <Clock class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('attendance.shift_windows', 'Operational Shift Windows & Density') }}</span>
        </h4>

        <div class="space-y-3">
          <div
            v-for="shift in shiftWindowsList"
            :key="shift.code"
            class="p-3 rounded-md border border-card-border bg-main-bg/50 flex flex-col gap-1.5"
          >
            <div class="flex items-center justify-between">
              <div class="font-bold text-xs text-main-text flex items-center gap-2">
                <span>{{ shift.title }}</span>
                <span class="text-[10px] font-mono text-main-text/50 font-normal">({{ shift.time_range }})</span>
              </div>
              <div class="font-mono font-bold text-sm text-brand-blue dark:text-brand-yellow">
                {{ shift.count }} {{ langStore.t('attendance.patrons', 'patrons') }}
              </div>
            </div>
            <!-- Progress Density Bar -->
            <div class="w-full h-2 rounded-full overflow-hidden bg-slate-100 dark:bg-slate-800">
              <div
                class="bg-brand-blue dark:bg-brand-yellow h-full transition-all duration-500"
                :style="{ width: `${shift.percent}%` }"
              ></div>
            </div>
            <div class="flex items-center justify-between text-[10px] text-main-text/60">
              <span>{{ langStore.localizeType('student') }}: {{ shift.students }} · {{ langStore.t('attendance.faculty', 'Faculty') }}: {{ shift.faculty }}</span>
              <span class="font-semibold">{{ shift.percent }}% of traffic</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Attendant Duty Desk Activity Log -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <h4 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
          <Users class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('attendance.staff_activity', 'Attendant Duty Desk Audit & Activity') }}</span>
        </h4>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-card-border text-[10px] uppercase text-main-text/50 font-bold">
                <th class="py-2">{{ langStore.t('attendance.staff_name', 'Staff Member') }}</th>
                <th class="py-2">{{ langStore.t('attendance.branch', 'Facility') }}</th>
                <th class="py-2 text-center">{{ langStore.t('attendance.entries_logged', 'Logs') }}</th>
                <th class="py-2 text-right">{{ langStore.t('attendance.patrons_handled', 'Patrons') }}</th>
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
</template>
