<script setup lang="ts">
import { Calendar, RotateCcw, Download, Printer, BarChart3, Users, Clock, History, ArrowRight } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import { useLanguageStore } from '@/stores/languageStore';
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

const handleDateInput = (key: 'from' | 'to', event: Event) => {
  const val = (event.target as HTMLInputElement).value;
  emit('update:dateRange', { ...props.dateRange, [key]: val });
};

const getFormattedTrendDate = (dateStr: string) => {
  return formatDT(dateStr, langStore.isAmharic()).full;
};
</script>

<template>
  <div class="space-y-6 pb-6">
    <!-- Controls Bar with Gregorian Inputs + Ethiopian Calendar Overlay -->
    <div class="bg-card-bg border border-card-border rounded-2xl p-4 flex flex-col lg:flex-row lg:items-end justify-between gap-4 shadow-2xs">
      <!-- Date Range Controls -->
      <div class="flex flex-col gap-1.5 flex-1 min-w-0 lg:max-w-[500px]">
        <div class="flex items-center px-2 text-[10px] font-bold text-main-text/60 uppercase">
          <div class="flex-1 ml-6">{{ langStore.t('common.from', 'From') }}</div>
          <div class="w-8"></div>
          <div class="flex-1 ml-2">{{ langStore.t('common.to', 'To') }}</div>
        </div>

        <div class="flex items-center gap-2 bg-main-bg/50 border border-card-border p-2 rounded-xl text-xs">
          <Calendar class="w-4 h-4 text-brand-blue shrink-0" />
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
        <div v-if="langStore.isAmharic()" class="flex items-center gap-2 px-3 text-[11px] text-brand-blue font-medium truncate">
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
          @click="emit('print')"
          class="!bg-brand-blue text-white shadow-sm"
        >
          {{ langStore.t('attendance.print_report', 'Print Official Report') }}
        </Button>
      </div>
    </div>

    <!-- Overview Metric Cards matching LMS exact colors -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <!-- Total Visitors -->
      <div class="bg-card-bg border border-card-border rounded-xl p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-main-text/50 uppercase tracking-wider">
          {{ langStore.t('attendance.total_label', 'Total Visitors') }}
        </span>
        <div class="text-2xl font-bold text-brand-blue font-mono mt-1">{{ dailyStats.total }}</div>
      </div>

      <!-- Students -->
      <div class="bg-card-bg border border-card-border rounded-xl p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
          {{ langStore.localizeType('student') }}
        </span>
        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono mt-1">{{ dailyStats.student }}</div>
      </div>

      <!-- Teachers -->
      <div class="bg-card-bg border border-card-border rounded-xl p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
          {{ langStore.localizeType('teacher') }}
        </span>
        <div class="text-2xl font-bold text-brand-blue font-mono mt-1">{{ dailyStats.teacher }}</div>
      </div>

      <!-- Staff -->
      <div class="bg-card-bg border border-card-border rounded-xl p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider">
          {{ langStore.localizeType('staff') }}
        </span>
        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 font-mono mt-1">{{ dailyStats.staff }}</div>
      </div>

      <!-- Male Ratio -->
      <div class="bg-brand-blue/5 border border-brand-blue/15 rounded-xl p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-brand-blue uppercase tracking-wider">
          {{ langStore.localizeGender('male') }}
        </span>
        <div class="text-2xl font-bold text-brand-blue font-mono mt-1">
          {{ dailyStats.male }} <span class="text-xs font-normal opacity-75">({{ malePercent }}%)</span>
        </div>
      </div>

      <!-- Female Ratio -->
      <div class="bg-brand-yellow/8 border border-brand-yellow/25 rounded-xl p-4 shadow-2xs">
        <span class="text-[10px] font-bold text-amber-700 dark:text-brand-yellow uppercase tracking-wider">
          {{ langStore.localizeGender('female') }}
        </span>
        <div class="text-2xl font-bold text-brand-yellow font-mono mt-1">
          {{ dailyStats.female }} <span class="text-xs font-normal opacity-75">({{ femalePercent }}%)</span>
        </div>
      </div>
    </div>

    <!-- Gender Breakdown & Periodic Distribution matching LMS layout -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Gender Distribution Bar -->
      <div class="bg-card-bg border border-card-border rounded-2xl p-6 shadow-2xs space-y-6">
        <h4 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
          <BarChart3 class="w-4 h-4 text-brand-blue" />
          <span>{{ langStore.t('attendance.gender_distribution', 'Gender Ratio & Distribution') }}</span>
        </h4>

        <!-- Proportional Cards -->
        <div class="grid grid-cols-2 gap-4">
          <div class="text-center p-4 rounded-xl bg-brand-blue/5 border border-brand-blue/15">
            <span class="text-[10px] font-bold text-brand-blue uppercase tracking-wider">{{ langStore.localizeGender('male') }}</span>
            <div class="text-3xl font-extrabold text-brand-blue mt-1">{{ dailyStats.male }}</div>
            <span class="text-[11px] text-brand-blue/70 font-medium mt-1 inline-block">{{ malePercent }}% {{ langStore.t('attendance.of_traffic', 'of traffic') }}</span>
          </div>

          <div class="text-center p-4 rounded-xl bg-brand-yellow/10 border border-brand-yellow/20">
            <span class="text-[10px] font-bold text-amber-700 dark:text-brand-yellow uppercase tracking-wider">{{ langStore.localizeGender('female') }}</span>
            <div class="text-3xl font-extrabold text-brand-yellow mt-1">{{ dailyStats.female }}</div>
            <span class="text-[11px] text-amber-700/80 dark:text-brand-yellow/80 font-medium mt-1 inline-block">{{ femalePercent }}% {{ langStore.t('attendance.of_traffic', 'of traffic') }}</span>
          </div>
        </div>

        <!-- Proportional Progress Bar -->
        <div class="w-full h-4 rounded-full overflow-hidden flex bg-slate-100 dark:bg-slate-800 shadow-inner">
          <div
            class="bg-brand-blue h-full transition-all duration-700"
            :style="{ width: `${malePercent}%` }"
            :title="`Male: ${malePercent}%`"
          ></div>
          <div
            class="bg-brand-yellow h-full transition-all duration-700"
            :style="{ width: `${femalePercent}%` }"
            :title="`Female: ${femalePercent}%`"
          ></div>
        </div>

        <!-- Breakdown Table by User Type -->
        <div class="pt-2">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-card-border text-[10px] uppercase text-main-text/50 font-bold">
                <th class="py-2.5">{{ langStore.t('attendance.user_type', 'Patron Type') }}</th>
                <th class="py-2.5 text-center text-brand-blue">{{ langStore.localizeGender('male') }}</th>
                <th class="py-2.5 text-center text-brand-yellow">{{ langStore.localizeGender('female') }}</th>
                <th class="py-2.5 text-right">{{ langStore.t('attendance.total_label', 'Total') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-card-border/60">
              <tr v-for="row in distributionReport" :key="row.type">
                <td class="py-2.5 font-bold text-main-text capitalize">{{ langStore.localizeType(row.type) }}</td>
                <td class="py-2.5 text-center text-brand-blue font-mono font-semibold">{{ row.male }}</td>
                <td class="py-2.5 text-center text-brand-yellow font-mono font-semibold">{{ row.female }}</td>
                <td class="py-2.5 text-right font-bold text-main-text font-mono">{{ row.total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Export Hub & Daily Trends matching LMS -->
      <div class="bg-card-bg border border-card-border rounded-2xl p-6 shadow-2xs space-y-6">
        <h4 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
          <Download class="w-4 h-4 text-brand-blue" />
          <span>{{ langStore.t('common.report_only', 'Automated Periodic Export Hub') }}</span>
        </h4>

        <!-- Export Buttons Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <Button
            variant="primary"
            size="sm"
            :icon="Download"
            @click="emit('export', 'day')"
            class="!bg-brand-blue text-white"
          >
            {{ langStore.t('common.daily_only', "Today's Log") }}
          </Button>
          <Button
            variant="secondary"
            size="sm"
            :icon="Download"
            @click="emit('export', 'week')"
          >
            {{ langStore.t('attendance.export_week', 'This Week (CSV)') }}
          </Button>
          <Button
            variant="secondary"
            size="sm"
            :icon="Download"
            @click="emit('export', 'month')"
          >
            {{ langStore.t('attendance.export_month', 'This Month (CSV)') }}
          </Button>
          <Button
            variant="secondary"
            size="sm"
            :icon="Printer"
            @click="emit('print')"
          >
            {{ langStore.t('attendance.print', 'Print') }}
          </Button>
        </div>

        <!-- Periodic Trends -->
        <div class="pt-2 space-y-3">
          <div class="text-[10px] font-bold text-main-text/50 uppercase tracking-wider flex items-center gap-1.5">
            <History class="w-3.5 h-3.5 text-brand-blue" />
            <span>{{ langStore.t('attendance.usage_trends', 'Daily Activity Trends in Period') }}</span>
          </div>
          <div class="max-h-[220px] overflow-y-auto space-y-2 pr-1 divide-y divide-card-border/30">
            <div
              v-for="t in trends"
              :key="t.date"
              class="flex items-center justify-between py-2 px-3 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors"
            >
              <div class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-lg bg-card-bg border border-card-border flex items-center justify-center text-main-text/60">
                  <Clock :size="14" />
                </div>
                <span class="text-xs font-semibold text-main-text">
                  {{ getFormattedTrendDate(t.date) }}
                </span>
              </div>
              <span class="font-mono font-bold text-sm text-brand-blue dark:text-brand-yellow">
                {{ t.count }} {{ langStore.t('attendance.visitors', 'visitors') }}
              </span>
            </div>
            <div v-if="trends.length === 0" class="text-center py-6 text-xs text-main-text/40">
              {{ langStore.t('attendance.no_trend_data', 'No trend data available for selected range.') }}
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
