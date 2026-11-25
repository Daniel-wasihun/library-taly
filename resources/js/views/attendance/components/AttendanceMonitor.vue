<script setup lang="ts">
import { computed } from 'vue';
import { History, Download, Plus, Minus, Trash2, Printer, Building2, Clock, UserCheck, Users2, Users, Sparkles } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import { useLanguageStore } from '@/stores/languageStore';
import { useAuthStore } from '@/stores/authStore';
import { useAttendanceStore } from '@/stores/attendanceStore';
import { formatDT } from '@/utils/ethiopianDate';
import type { Attendance } from '@/types';

const props = defineProps<{
  dailyStats: {
    male: number;
    female: number;
    total: number;
  };
  logs: Attendance[];
  loading: boolean;
  canDeleteEntry: (date: string) => boolean;
  canUpdateEntry: (date: string) => boolean;
}>();

const emit = defineEmits<{
  (e: 'export'): void;
  (e: 'print'): void;
  (e: 'updateCount', log: Attendance, delta: number): void;
  (e: 'delete', log: Attendance): void;
}>();

const langStore = useLanguageStore();
const authStore = useAuthStore();
const attendanceStore = useAttendanceStore();

const latestEntry = computed(() => {
  return props.logs && props.logs.length > 0 ? props.logs[0] : null;
});

const getTimeDisplay = (dateStr: string) => {
  return formatDT(dateStr, langStore.isAmharic()).time;
};

const getDateDisplay = (dateStr: string) => {
  return formatDT(dateStr, langStore.isAmharic()).date;
};
</script>

<template>
  <div class="lg:col-span-8 xl:col-span-8 2xl:col-span-9 bg-card-bg border border-card-border rounded-sm sm:rounded flex flex-col shadow-2xs overflow-hidden min-h-[440px] sm:min-h-[580px]">
    
    <!-- Header with Metric Counters & Actions -->
    <div class="p-2.5 sm:px-5 sm:py-3.5 border-b border-card-border flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 sm:gap-3 bg-card-hover/20">
      
      <!-- Title & Metric Bar -->
      <div class="flex items-center justify-between sm:justify-start gap-3 w-full sm:w-auto">
        <div class="flex items-center gap-1.5 sm:gap-2">
          <History class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-brand-blue" />
          <h3 class="text-[11px] sm:text-xs font-bold text-main-text uppercase tracking-wider">
            {{ langStore.t('attendance.recent_entries', 'Recent Usage Entries') }}
          </h3>
        </div>

        <!-- Quick Metrics (Inline on mobile) -->
        <div class="flex items-center gap-2 sm:hidden text-[10px] font-bold">
          <span class="inline-flex items-center gap-0.5 text-brand-blue">
            <span>{{ langStore.localizeGender('male') }}:</span>
            <span v-if="loading && logs.length === 0" class="h-3 w-5 bg-slate-200 dark:bg-slate-700 rounded animate-pulse inline-block"></span>
            <span v-else class="font-mono">{{ dailyStats.male }}</span>
          </span>
          <span class="text-card-border">|</span>
          <span class="inline-flex items-center gap-0.5 text-brand-yellow">
            <span>{{ langStore.localizeGender('female') }}:</span>
            <span v-if="loading && logs.length === 0" class="h-3 w-5 bg-slate-200 dark:bg-slate-700 rounded animate-pulse inline-block"></span>
            <span v-else class="font-mono">{{ dailyStats.female }}</span>
          </span>
          <span class="text-card-border">|</span>
          <span class="inline-flex items-center gap-0.5 text-main-text">
            <span v-if="loading && logs.length === 0" class="h-3 w-6 bg-slate-200 dark:bg-slate-700 rounded animate-pulse inline-block"></span>
            <span v-else class="font-mono">{{ dailyStats.total }}</span>
          </span>
        </div>
      </div>

      <!-- Quick Metrics Counters & Actions (Desktop / Tablet) -->
      <div class="flex items-center justify-between sm:justify-end gap-2 sm:gap-3 w-full sm:w-auto">
        <!-- Desktop Metrics Display -->
        <div class="hidden sm:flex items-center gap-4">
          <!-- Male Pill -->
          <div class="text-right">
            <span class="text-[10px] font-bold text-brand-blue uppercase tracking-wider">
              {{ langStore.localizeGender('male') }}
            </span>
            <div v-if="loading && logs.length === 0" class="h-4 w-10 bg-slate-200 dark:bg-slate-700 rounded animate-pulse mt-1 ml-auto"></div>
            <div v-else class="text-sm sm:text-base font-bold text-brand-blue font-mono leading-none mt-0.5">
              {{ dailyStats.male }}
            </div>
          </div>

          <div class="h-5 sm:h-6 w-px bg-card-border"></div>

          <!-- Female Pill -->
          <div class="text-right">
            <span class="text-[10px] font-bold text-brand-yellow uppercase tracking-wider">
              {{ langStore.localizeGender('female') }}
            </span>
            <div v-if="loading && logs.length === 0" class="h-4 w-10 bg-slate-200 dark:bg-slate-700 rounded animate-pulse mt-1 ml-auto"></div>
            <div v-else class="text-sm sm:text-base font-bold text-brand-yellow font-mono leading-none mt-0.5">
              {{ dailyStats.female }}
            </div>
          </div>

          <div class="h-5 sm:h-6 w-px bg-card-border"></div>

          <!-- Total Pill -->
          <div class="text-right pr-2">
            <span class="text-[10px] font-bold text-main-text/60 uppercase tracking-wider">
              {{ langStore.t('attendance.total_label', 'Total Today') }}
            </span>
            <div v-if="loading && logs.length === 0" class="h-4 w-12 bg-slate-200 dark:bg-slate-700 rounded animate-pulse mt-1 ml-auto"></div>
            <div v-else class="text-base sm:text-lg font-bold text-brand-blue font-mono leading-none mt-0.5">
              {{ dailyStats.total }}
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div v-if="authStore.canManageReporting || authStore.hasPermission('attendance.view')" class="flex items-center gap-1.5 sm:gap-2 w-full sm:w-auto justify-end">
          <!-- Backend Print Button -->
          <Button
            variant="primary"
            size="sm"
            :icon="Printer"
            :loading="attendanceStore.isDownloadingReport && attendanceStore.downloadingReportFormat === 'pdf'"
            :disabled="attendanceStore.isDownloadingReport"
            @click="emit('print')"
            class="!bg-brand-blue text-white shadow-2xs text-[11px] sm:text-xs py-1 px-2.5 sm:py-1.5 sm:px-3 flex-1 sm:flex-initial"
          >
            {{ langStore.t('attendance.print', 'Print') }}
          </Button>

          <!-- Quick Export CSV -->
          <Button
            variant="secondary"
            size="sm"
            :icon="Download"
            :loading="attendanceStore.isDownloadingReport && attendanceStore.downloadingReportFormat === 'csv'"
            :disabled="attendanceStore.isDownloadingReport"
            @click="emit('export')"
            class="text-[11px] sm:text-xs py-1 px-2.5 sm:py-1.5 sm:px-3 flex-1 sm:flex-initial"
          >
            CSV
          </Button>
        </div>
      </div>
    </div>

    <!-- Active Latest Entry Banner: Immediately removes previous entry and shows the new one on every log -->
    <transition name="fade" mode="out-in">
      <div
        v-if="latestEntry"
        :key="latestEntry.id || latestEntry.slug"
        class="mx-2.5 sm:mx-5 mt-2.5 sm:mt-3 px-3 py-2 rounded-sm bg-blue-50/90 dark:bg-blue-950/40 border border-brand-blue/30 flex items-center justify-between text-xs transition-all shadow-2xs"
      >
        <div class="flex items-center gap-2 min-w-0">
          <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0 animate-ping"></span>
          <span class="font-bold text-main-text shrink-0 text-[11px] sm:text-xs">
            {{ langStore.isAmharic() ? 'አሁን የተመዘገበ፡' : 'Latest Entry:' }}
          </span>
          <span
            class="font-mono font-extrabold truncate text-[11px] sm:text-xs"
            :class="latestEntry.gender === 'male' ? 'text-brand-blue dark:text-white' : 'text-brand-yellow'"
          >
            +{{ latestEntry.visitor_count }} {{ langStore.localizeGender(latestEntry.gender) }} {{ langStore.localizeType(latestEntry.user_type) }}
          </span>
          <span class="hidden sm:inline text-main-text/40">·</span>
          <span class="hidden sm:inline text-[10px] text-main-text/60 truncate">
            {{ langStore.localizeBranch(latestEntry.library) }}
          </span>
        </div>
        <span class="text-[10px] sm:text-[11px] text-main-text/70 font-mono font-semibold shrink-0">
          {{ getTimeDisplay(latestEntry.entry_at) }}
        </span>
      </div>
    </transition>

    <!-- CLEAN, STRAIGHT-LINE, NON-WRAPPING DATA TABLE -->
    <div class="flex-1 overflow-x-auto w-full relative min-h-[360px]">
      <!-- Slim Loading Progress Bar when refreshing existing records -->
      <div v-if="loading && logs.length > 0" class="absolute top-0 left-0 right-0 h-0.5 bg-brand-blue/20 overflow-hidden z-10">
        <div class="h-full bg-brand-blue animate-pulse w-full"></div>
      </div>

      <table class="w-full text-left text-[11px] sm:text-xs border-collapse whitespace-nowrap">
        <thead>
          <tr class="border-b border-card-border bg-slate-50/70 dark:bg-slate-800/50 select-none whitespace-nowrap text-nowrap">
            <th class="py-2.5 px-3 sm:py-3 sm:px-4 font-bold text-main-text/60 uppercase text-[10px] text-center w-10">
              {{ langStore.t('common.status', 'Status') }}
            </th>
            <th class="py-2.5 px-3 sm:py-3 sm:px-4 font-bold text-main-text/60 uppercase text-[10px]">{{ langStore.t('common.staff', 'Staff Member') }}</th>
            <th class="py-2.5 px-3 sm:py-3 sm:px-4 font-bold text-main-text/60 uppercase text-[10px]">{{ langStore.t('attendance.user_type', 'Patron Type') }}</th>
            <th class="py-2.5 px-3 sm:py-3 sm:px-4 font-bold text-main-text/60 uppercase text-[10px] text-center">{{ langStore.t('common.gender', 'Gender') }}</th>
            <th class="py-2.5 px-3 sm:py-3 sm:px-4 font-bold text-main-text/60 uppercase text-[10px] text-center">{{ langStore.t('common.count', 'Visitor Count') }}</th>
            <th class="py-2.5 px-3 sm:py-3 sm:px-4 font-bold text-main-text/60 uppercase text-[10px]">{{ langStore.t('common.location', 'Branch') }}</th>
            <th class="py-2.5 px-3 sm:py-3 sm:px-4 font-bold text-main-text/60 uppercase text-[10px]">{{ langStore.t('common.time', 'Time (12h)') }}</th>
            <th class="py-2.5 px-3 sm:py-3 sm:px-4 font-bold text-main-text/60 uppercase text-[10px] text-right">
              {{ langStore.t('common.actions', 'Actions') }}
            </th>
          </tr>
        </thead>
        <tbody class="divide-y divide-card-border/60">
          <!-- Skeleton Loading Rows (Shown ONLY during initial load when there are no logs yet) -->
          <template v-if="loading && logs.length === 0">
            <tr v-for="i in 6" :key="`skel-${i}`" class="animate-pulse">
              <td class="py-3 px-3 text-center"><div class="w-2.5 h-2.5 rounded-full bg-slate-200 dark:bg-slate-700 mx-auto"></div></td>
              <td class="py-3 px-4"><div class="h-3.5 bg-slate-200 dark:bg-slate-700/60 rounded w-28"></div></td>
              <td class="py-3 px-4"><div class="h-5 bg-slate-200 dark:bg-slate-700/60 rounded-full w-18"></div></td>
              <td class="py-3 px-4 text-center"><div class="h-4 bg-slate-200 dark:bg-slate-700/60 rounded w-12 mx-auto"></div></td>
              <td class="py-3 px-4 text-center"><div class="h-5 bg-slate-200 dark:bg-slate-700/60 rounded-md w-8 mx-auto"></div></td>
              <td class="py-3 px-4"><div class="h-3.5 bg-slate-200 dark:bg-slate-700/60 rounded w-20"></div></td>
              <td class="py-3 px-4"><div class="h-3.5 bg-slate-200 dark:bg-slate-700/60 rounded w-16"></div></td>
              <td class="py-3 px-4 text-right"><div class="h-6 bg-slate-200 dark:bg-slate-700/60 rounded w-16 ml-auto"></div></td>
            </tr>
          </template>
          <tr v-else-if="logs.length === 0">
            <td colspan="8" class="px-4 py-12 text-center text-main-text/40">
              {{ langStore.isAmharic() ? 'ለዛሬ እስካሁን የተመዘገበ የመገኘት መረጃ የለም።' : langStore.t('common.no_records', 'No attendance records logged for today yet.') }}
            </td>
          </tr>
          <tr
            v-for="item in logs"
            :key="item.id"
            class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors whitespace-nowrap text-nowrap"
          >
            <!-- Status Badge matching LMS -->
            <td class="py-2 px-2.5 sm:py-2.5 sm:px-3 text-center whitespace-nowrap">
              <div class="inline-flex items-center justify-center">
                <div
                  class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg flex items-center justify-center text-white shadow-2xs border border-white/10"
                  :class="item.gender === 'male' ? 'bg-brand-blue' : 'bg-brand-yellow'"
                >
                  <Users2 v-if="item.user_type === 'teacher'" :size="12" />
                  <Users v-else :size="12" />
                </div>
              </div>
            </td>

            <!-- Staff Member -->
            <td class="py-2 px-3 sm:py-2.5 sm:px-4 font-medium text-main-text whitespace-nowrap">
              <div class="inline-flex items-center gap-1.5">
                <UserCheck class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-main-text/40 shrink-0" />
                <span class="whitespace-nowrap">{{ item.staff?.name || 'System' }}</span>
              </div>
            </td>

            <!-- Patron Type -->
            <td class="py-2 px-3 sm:py-2.5 sm:px-4 whitespace-nowrap">
              <span
                class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold capitalize border whitespace-nowrap"
                :class="item.user_type === 'student'
                  ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'
                  : (item.user_type === 'teacher'
                      ? 'bg-brand-blue/10 text-brand-blue dark:text-blue-300 border-brand-blue/20'
                      : 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20')"
              >
                {{ langStore.localizeType(item.user_type) }}
              </span>
            </td>

            <!-- Gender -->
            <td class="py-2 px-3 sm:py-2.5 sm:px-4 text-center font-medium capitalize text-main-text whitespace-nowrap">
              <span
                class="inline-block text-[11px] whitespace-nowrap"
                :class="item.gender === 'male' ? 'text-brand-blue font-semibold' : 'text-brand-yellow font-semibold'"
              >
                {{ langStore.localizeGender(item.gender) }}
              </span>
            </td>

            <!-- Count + +/- quick adjustment buttons in straight line -->
            <td class="py-2 px-3 sm:py-2.5 sm:px-4 text-center whitespace-nowrap">
              <div class="inline-flex items-center gap-1 p-0.5 rounded-xs border border-card-border bg-main-bg/50">
                <button
                  type="button"
                  :disabled="item.visitor_count <= 1 || !canUpdateEntry(item.entry_at)"
                  @click="emit('updateCount', item, -1)"
                  class="w-4 h-4 sm:w-5 sm:h-5 rounded-xs flex items-center justify-center hover:bg-rose-500/10 hover:text-rose-600 text-main-text/50 disabled:opacity-20 disabled:cursor-not-allowed cursor-pointer"
                  :title="!canUpdateEntry(item.entry_at) ? langStore.t('attendance.update_expired', 'Adjustments disabled after 2 minutes') : 'Decrement count (-1)'"
                >
                  <Minus class="w-2.5 h-2.5 sm:w-3 sm:h-3" />
                </button>
                <span class="font-mono font-bold text-[11px] sm:text-xs min-w-[16px] sm:min-w-[20px] text-center text-main-text select-none">
                  {{ item.visitor_count }}
                </span>
                <button
                  type="button"
                  :disabled="item.visitor_count >= 100 || !canUpdateEntry(item.entry_at)"
                  @click="emit('updateCount', item, 1)"
                  class="w-4 h-4 sm:w-5 sm:h-5 rounded-xs flex items-center justify-center hover:bg-emerald-500/10 hover:text-emerald-600 text-main-text/50 disabled:opacity-20 disabled:cursor-not-allowed cursor-pointer"
                  :title="!canUpdateEntry(item.entry_at) ? langStore.t('attendance.update_expired', 'Adjustments disabled after 2 minutes') : 'Increment count (+1)'"
                >
                  <Plus class="w-2.5 h-2.5 sm:w-3 sm:h-3" />
                </button>
              </div>
            </td>

            <!-- Library Branch -->
            <td class="py-2 px-3 sm:py-2.5 sm:px-4 text-main-text/70 whitespace-nowrap">
              <div class="inline-flex items-center gap-1.5">
                <Building2 class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-main-text/40 shrink-0" />
                <span class="font-medium whitespace-nowrap">{{ langStore.localizeBranch(item.library) }}</span>
              </div>
            </td>

            <!-- 12-hour Time & Date in straight single line -->
            <td class="py-2 px-3 sm:py-2.5 sm:px-4 text-main-text/75 font-mono text-[10px] sm:text-[11px] whitespace-nowrap">
              <div class="inline-flex items-center gap-1.5">
                <Clock class="w-3 h-3 text-main-text/40 shrink-0" />
                <span class="font-semibold text-main-text">{{ getTimeDisplay(item.entry_at) }}</span>
                <span class="text-main-text/40">·</span>
                <span class="text-main-text/50">{{ getDateDisplay(item.entry_at) }}</span>
              </div>
            </td>

            <!-- Delete with 2-minute safety check -->
            <td class="py-2 px-3 sm:py-2.5 sm:px-4 text-right whitespace-nowrap">
              <button
                type="button"
                :disabled="!canDeleteEntry(item.entry_at)"
                @click="emit('delete', item)"
                class="p-1 rounded-xs text-main-text/40 hover:text-rose-600 hover:bg-rose-500/10 transition disabled:opacity-20 disabled:cursor-not-allowed cursor-pointer"
                :title="canDeleteEntry(item.entry_at) ? langStore.t('attendance.cancel_entry', 'Cancel this entry (within 2-minute window)') : langStore.t('attendance.cancel_notice', 'Entries can be cancelled within 2 minutes of logging.')"
              >
                <Trash2 class="w-3.5 h-3.5 sm:w-4 sm:h-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</template>
