<script setup lang="ts">
import { Calendar, RotateCcw, Download, TrendingUp, Users, ArrowRight } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import { useLanguageStore } from '@/stores/languageStore';

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
}>();

const langStore = useLanguageStore();

const handleDateInput = (key: 'from' | 'to', event: Event) => {
  const val = (event.target as HTMLInputElement).value;
  emit('update:dateRange', { ...props.dateRange, [key]: val });
};
</script>

<template>
  <div class="space-y-6 pb-6">
    <!-- Controls Bar -->
    <div class="bg-card-bg border border-card-border rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 shadow-2xs">
      <!-- Date Range Controls -->
      <div class="flex items-center gap-2 bg-main-bg/50 border border-card-border p-1.5 rounded-xl text-xs">
        <Calendar class="w-4 h-4 text-brand-blue ml-2 shrink-0" />
        <span class="text-[10px] uppercase font-bold text-main-text/50">From</span>
        <input
          type="date"
          :value="dateRange.from"
          @input="handleDateInput('from', $event)"
          class="bg-transparent text-xs font-mono font-bold text-main-text focus:outline-none px-1"
        />
        <ArrowRight class="w-3.5 h-3.5 text-main-text/40" />
        <span class="text-[10px] uppercase font-bold text-main-text/50">To</span>
        <input
          type="date"
          :value="dateRange.to"
          @input="handleDateInput('to', $event)"
          class="bg-transparent text-xs font-mono font-bold text-main-text focus:outline-none px-1"
        />
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-2">
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
          Reset Today
        </Button>
      </div>
    </div>

    <!-- Overview Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <!-- Total Visitors -->
      <div class="bg-card-bg border border-card-border rounded-xl p-3.5 shadow-2xs">
        <span class="text-[10px] font-bold text-main-text/50 uppercase tracking-wider">Total Visitors</span>
        <div class="text-xl font-bold text-main-text font-mono mt-1">{{ dailyStats.total }}</div>
      </div>

      <!-- Students -->
      <div class="bg-card-bg border border-card-border rounded-xl p-3.5 shadow-2xs">
        <span class="text-[10px] font-bold text-blue-500 uppercase tracking-wider">Students</span>
        <div class="text-xl font-bold text-blue-600 dark:text-blue-400 font-mono mt-1">{{ dailyStats.student }}</div>
      </div>

      <!-- Teachers -->
      <div class="bg-card-bg border border-card-border rounded-xl p-3.5 shadow-2xs">
        <span class="text-[10px] font-bold text-purple-500 uppercase tracking-wider">Teachers</span>
        <div class="text-xl font-bold text-purple-600 dark:text-purple-400 font-mono mt-1">{{ dailyStats.teacher }}</div>
      </div>

      <!-- Staff -->
      <div class="bg-card-bg border border-card-border rounded-xl p-3.5 shadow-2xs">
        <span class="text-[10px] font-bold text-emerald-500 uppercase tracking-wider">Staff</span>
        <div class="text-xl font-bold text-emerald-600 dark:text-emerald-400 font-mono mt-1">{{ dailyStats.staff }}</div>
      </div>

      <!-- Male Ratio -->
      <div class="bg-card-bg border border-card-border rounded-xl p-3.5 shadow-2xs">
        <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">Male Visitors</span>
        <div class="text-xl font-bold text-blue-600 dark:text-blue-400 font-mono mt-1">
          {{ dailyStats.male }} <span class="text-xs font-normal opacity-70">({{ malePercent }}%)</span>
        </div>
      </div>

      <!-- Female Ratio -->
      <div class="bg-card-bg border border-card-border rounded-xl p-3.5 shadow-2xs">
        <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">Female Visitors</span>
        <div class="text-xl font-bold text-amber-600 dark:text-amber-400 font-mono mt-1">
          {{ dailyStats.female }} <span class="text-xs font-normal opacity-70">({{ femalePercent }}%)</span>
        </div>
      </div>
    </div>

    <!-- Gender Breakdown & Periodic Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
      <!-- Gender Distribution Bar -->
      <div class="bg-card-bg border border-card-border rounded-2xl p-5 shadow-2xs space-y-4">
        <h4 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
          <Users class="w-4 h-4 text-brand-blue" />
          <span>Gender Ratio & Distribution</span>
        </h4>

        <!-- Proportional Progress Bar -->
        <div class="w-full h-4 rounded-full overflow-hidden flex bg-slate-100 dark:bg-slate-800">
          <div
            class="bg-blue-600 h-full transition-all duration-500"
            :style="{ width: `${malePercent}%` }"
            :title="`Male: ${malePercent}%`"
          ></div>
          <div
            class="bg-amber-500 h-full transition-all duration-500"
            :style="{ width: `${femalePercent}%` }"
            :title="`Female: ${femalePercent}%`"
          ></div>
        </div>

        <div class="flex items-center justify-between text-xs font-semibold">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-blue-600 shrink-0"></span>
            <span>Male: {{ dailyStats.male }} ({{ malePercent }}%)</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-amber-500 shrink-0"></span>
            <span>Female: {{ dailyStats.female }} ({{ femalePercent }}%)</span>
          </div>
        </div>

        <!-- Breakdown Table by User Type -->
        <div class="pt-2">
          <table class="w-full text-left text-xs">
            <thead>
              <tr class="border-b border-card-border text-[10px] uppercase text-main-text/50">
                <th class="py-2">Patron Type</th>
                <th class="py-2 text-center">Male</th>
                <th class="py-2 text-center">Female</th>
                <th class="py-2 text-right">Total</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-card-border/60">
              <tr v-for="row in distributionReport" :key="row.type">
                <td class="py-2.5 font-bold text-main-text capitalize">{{ row.type }}</td>
                <td class="py-2.5 text-center text-blue-600 dark:text-blue-400 font-mono">{{ row.male }}</td>
                <td class="py-2.5 text-center text-amber-600 dark:text-amber-400 font-mono">{{ row.female }}</td>
                <td class="py-2.5 text-right font-bold text-main-text font-mono">{{ row.total }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Export Hub & Daily Trends -->
      <div class="bg-card-bg border border-card-border rounded-2xl p-5 shadow-2xs space-y-4">
        <h4 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
          <Download class="w-4 h-4 text-brand-blue" />
          <span>Automated Periodic Export Hub</span>
        </h4>

        <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
          <Button
            variant="secondary"
            size="sm"
            :icon="Download"
            @click="emit('export', 'day')"
          >
            Today's Log
          </Button>
          <Button
            variant="secondary"
            size="sm"
            :icon="Download"
            @click="emit('export', 'week')"
          >
            This Week (CSV)
          </Button>
          <Button
            variant="secondary"
            size="sm"
            :icon="Download"
            @click="emit('export', 'month')"
          >
            This Month (CSV)
          </Button>
        </div>

        <!-- Periodic Trends -->
        <div class="pt-2 space-y-2">
          <div class="text-[10px] font-bold text-main-text/50 uppercase tracking-wider flex items-center gap-1.5">
            <TrendingUp class="w-3.5 h-3.5 text-emerald-500" />
            <span>Daily Activity Trends in Period</span>
          </div>
          <div class="max-h-[160px] overflow-y-auto space-y-1.5 pr-1">
            <div
              v-for="t in trends"
              :key="t.date"
              class="flex items-center justify-between p-2 rounded-lg bg-main-bg/50 border border-card-border text-xs"
            >
              <span class="font-mono text-[11px] text-main-text">{{ t.date }}</span>
              <span class="font-mono font-bold text-xs text-brand-blue dark:text-brand-yellow">
                {{ t.count }} visitors
              </span>
            </div>
            <div v-if="trends.length === 0" class="text-center py-6 text-xs text-main-text/40">
              No trend data available for selected range.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
