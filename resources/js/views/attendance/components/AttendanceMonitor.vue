<script setup lang="ts">
import { History, Download, Plus, Minus, Trash2, Printer, Building2, Clock, UserCheck, Users2, Users } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import { useLanguageStore } from '@/stores/languageStore';
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
}>();

const emit = defineEmits<{
  (e: 'export'): void;
  (e: 'print'): void;
  (e: 'updateCount', log: Attendance, delta: number): void;
  (e: 'delete', log: Attendance): void;
}>();

const langStore = useLanguageStore();

const getTimeDisplay = (dateStr: string) => {
  return formatDT(dateStr, langStore.isAmharic()).time;
};

const getDateDisplay = (dateStr: string) => {
  return formatDT(dateStr, langStore.isAmharic()).date;
};
</script>

<template>
  <div class="lg:col-span-8 bg-card-bg border border-card-border rounded-2xl flex flex-col shadow-2xs overflow-hidden min-h-[480px] lg:min-h-[580px]">
    
    <!-- Header with Metric Counters & Actions (Mobile Responsive) -->
    <div class="p-3.5 sm:px-5 sm:py-4 border-b border-card-border flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-card-hover/20">
      
      <!-- Title & Metric Bar -->
      <div class="flex items-center justify-between sm:justify-start gap-3 w-full sm:w-auto">
        <div class="flex items-center gap-2">
          <History class="w-4 h-4 text-brand-blue" />
          <h3 class="text-xs font-bold text-main-text uppercase tracking-wider">
            {{ langStore.t('attendance.recent_entries', 'Recent Usage Entries') }}
          </h3>
        </div>

        <!-- Quick Metrics (Inline on mobile) -->
        <div class="flex items-center gap-2.5 sm:hidden">
          <span class="inline-flex items-center gap-1 text-[11px] font-bold text-brand-blue">
            <span>{{ langStore.localizeGender('male') }}:</span>
            <span class="font-mono">{{ dailyStats.male }}</span>
          </span>
          <span class="text-card-border">|</span>
          <span class="inline-flex items-center gap-1 text-[11px] font-bold text-brand-yellow">
            <span>{{ langStore.localizeGender('female') }}:</span>
            <span class="font-mono">{{ dailyStats.female }}</span>
          </span>
          <span class="text-card-border">|</span>
          <span class="inline-flex items-center gap-1 text-[11px] font-bold text-main-text">
            <span class="font-mono">{{ dailyStats.total }}</span>
          </span>
        </div>
      </div>

      <!-- Quick Metrics Counters & Actions (Desktop / Tablet) -->
      <div class="flex items-center justify-between sm:justify-end gap-3 w-full sm:w-auto">
        <!-- Desktop Metrics Display -->
        <div class="hidden sm:flex items-center gap-4">
          <!-- Male Pill -->
          <div class="text-right">
            <span class="text-[10px] font-bold text-brand-blue uppercase tracking-wider">
              {{ langStore.localizeGender('male') }}
            </span>
            <div class="text-base font-bold text-brand-blue font-mono leading-none mt-0.5">
              {{ dailyStats.male }}
            </div>
          </div>

          <div class="h-6 w-px bg-card-border"></div>

          <!-- Female Pill -->
          <div class="text-right">
            <span class="text-[10px] font-bold text-brand-yellow uppercase tracking-wider">
              {{ langStore.localizeGender('female') }}
            </span>
            <div class="text-base font-bold text-brand-yellow font-mono leading-none mt-0.5">
              {{ dailyStats.female }}
            </div>
          </div>

          <div class="h-6 w-px bg-card-border"></div>

          <!-- Total Pill -->
          <div class="text-right pr-2">
            <span class="text-[10px] font-bold text-main-text/60 uppercase tracking-wider">
              {{ langStore.t('attendance.total_label', 'Total Today') }}
            </span>
            <div class="text-lg font-bold text-brand-blue font-mono leading-none mt-0.5">
              {{ dailyStats.total }}
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
          <!-- Backend Print Button -->
          <Button
            variant="primary"
            size="sm"
            :icon="Printer"
            @click="emit('print')"
            class="!bg-brand-blue text-white shadow-2xs flex-1 sm:flex-initial"
          >
            {{ langStore.t('attendance.print', 'Print') }}
          </Button>

          <!-- Quick Export CSV -->
          <Button
            variant="secondary"
            size="sm"
            :icon="Download"
            @click="emit('export')"
            class="flex-1 sm:flex-initial"
          >
            CSV
          </Button>
        </div>
      </div>
    </div>

    <!-- MOBILE CARDS PREVIEW (< md screens) -->
    <div class="block md:hidden flex-1 p-3 space-y-2.5 overflow-y-auto max-h-[500px]">
      <div v-if="loading && logs.length === 0" class="py-10 text-center text-xs text-main-text/40">
        {{ langStore.t('common.loading', 'Loading recent entries...') }}
      </div>
      <div v-else-if="logs.length === 0" class="py-10 text-center text-xs text-main-text/40">
        {{ langStore.t('common.no_records', 'No attendance records logged for today yet.') }}
      </div>
      <div
        v-for="item in logs"
        :key="'mobile-' + item.id"
        class="bg-card-bg border border-card-border rounded-xl p-3 shadow-2xs transition-all flex flex-col gap-2"
      >
        <!-- Top row: Gender badge, Type badge, and Actions -->
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <!-- Gender Icon Badge -->
            <div
              class="w-7 h-7 rounded-lg flex items-center justify-center text-white shadow-2xs"
              :class="item.gender === 'male' ? 'bg-brand-blue' : 'bg-brand-yellow'"
            >
              <Users2 v-if="item.user_type === 'teacher'" :size="13" />
              <Users v-else :size="13" />
            </div>

            <!-- Gender & Patron Type Badges -->
            <div class="flex items-center gap-1.5">
              <span
                class="text-xs font-bold capitalize"
                :class="item.gender === 'male' ? 'text-brand-blue' : 'text-brand-yellow'"
              >
                {{ langStore.localizeGender(item.gender) }}
              </span>
              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-bold capitalize border"
                :class="item.user_type === 'student'
                  ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border-emerald-500/20'
                  : (item.user_type === 'teacher'
                      ? 'bg-brand-blue/10 text-brand-blue dark:text-blue-300 border-brand-blue/20'
                      : 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border-purple-500/20')"
              >
                {{ langStore.localizeType(item.user_type) }}
              </span>
            </div>
          </div>

          <!-- Stepper and Delete Button -->
          <div class="flex items-center gap-2">
            <!-- Count Adjuster -->
            <div class="inline-flex items-center gap-1 p-0.5 rounded-lg border border-card-border bg-main-bg/70">
              <button
                type="button"
                :disabled="item.visitor_count <= 1 || !canDeleteEntry(item.entry_at)"
                @click="emit('updateCount', item, -1)"
                class="w-6 h-6 rounded flex items-center justify-center hover:bg-rose-500/10 hover:text-rose-600 text-main-text/60 disabled:opacity-20 cursor-pointer"
              >
                <Minus class="w-3 h-3" />
              </button>
              <span class="font-mono font-bold text-xs min-w-[20px] text-center text-main-text">
                {{ item.visitor_count }}
              </span>
              <button
                type="button"
                :disabled="item.visitor_count >= 100 || !canDeleteEntry(item.entry_at)"
                @click="emit('updateCount', item, 1)"
                class="w-6 h-6 rounded flex items-center justify-center hover:bg-emerald-500/10 hover:text-emerald-600 text-main-text/60 disabled:opacity-20 cursor-pointer"
              >
                <Plus class="w-3 h-3" />
              </button>
            </div>

            <!-- Cancel/Delete -->
            <button
              type="button"
              :disabled="!canDeleteEntry(item.entry_at)"
              @click="emit('delete', item)"
              class="w-7 h-7 rounded-lg flex items-center justify-center text-main-text/40 hover:text-rose-600 hover:bg-rose-500/10 transition disabled:opacity-20 cursor-pointer"
            >
              <Trash2 class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- Bottom row: Location, Staff, and Time (12h) -->
        <div class="flex items-center justify-between text-[11px] text-main-text/60 pt-1 border-t border-card-border/40">
          <div class="flex items-center gap-1.5 truncate">
            <Building2 class="w-3 h-3 text-main-text/40 shrink-0" />
            <span class="truncate font-medium">{{ langStore.localizeBranch(item.library) }}</span>
          </div>

          <div class="flex items-center gap-1 font-mono font-semibold text-main-text shrink-0">
            <Clock class="w-3 h-3 text-main-text/40 shrink-0" />
            <span>{{ getTimeDisplay(item.entry_at) }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- DESKTOP TABLE (>= md screens) -->
    <div class="hidden md:block flex-1 overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-card-border bg-slate-50/60 dark:bg-slate-800/40 select-none">
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px] w-12 text-center">Status</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">{{ langStore.t('common.staff', 'Staff Member') }}</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">{{ langStore.t('attendance.user_type', 'Patron Type') }}</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px] text-center">{{ langStore.t('common.gender', 'Gender') }}</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px] text-center">{{ langStore.t('common.count', 'Visitor Count') }}</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">{{ langStore.t('common.location', 'Branch') }}</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">{{ langStore.t('common.time', 'Time (12h)') }}</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px] text-right">{{ langStore.t('common.actions', 'Actions') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-card-border/60">
          <tr v-if="loading && logs.length === 0">
            <td colspan="8" class="px-4 py-12 text-center text-main-text/40">
              {{ langStore.t('common.loading', 'Loading recent entries...') }}
            </td>
          </tr>
          <tr v-else-if="logs.length === 0">
            <td colspan="8" class="px-4 py-12 text-center text-main-text/40">
              {{ langStore.t('common.no_records', 'No attendance records logged for today yet.') }}
            </td>
          </tr>
          <tr
            v-for="item in logs"
            :key="item.id"
            class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
          >
            <!-- Status Badge matching LMS -->
            <td class="px-3 py-3 text-center">
              <div class="flex items-center justify-center">
                <div
                  class="w-7 h-7 rounded-lg flex items-center justify-center text-white shadow-2xs border border-white/10"
                  :class="item.gender === 'male' ? 'bg-brand-blue' : 'bg-brand-yellow'"
                >
                  <Users2 v-if="item.user_type === 'teacher'" :size="13" />
                  <Users v-else :size="13" />
                </div>
              </div>
            </td>

            <!-- Staff Member -->
            <td class="px-4 py-3 font-medium text-main-text">
              <div class="flex items-center gap-1.5">
                <UserCheck class="w-3.5 h-3.5 text-main-text/40 shrink-0" />
                <span class="truncate">{{ item.staff?.name || 'System' }}</span>
              </div>
            </td>

            <!-- Patron Type -->
            <td class="px-4 py-3">
              <span
                class="px-2.5 py-0.5 rounded-full text-[10px] font-bold capitalize border"
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
            <td class="px-4 py-3 text-center font-medium capitalize text-main-text">
              <span
                class="inline-block text-[11px]"
                :class="item.gender === 'male' ? 'text-brand-blue font-semibold' : 'text-brand-yellow font-semibold'"
              >
                {{ langStore.localizeGender(item.gender) }}
              </span>
            </td>

            <!-- Count + +/- quick adjustment buttons -->
            <td class="px-4 py-3 text-center">
              <div class="inline-flex items-center gap-1.5 p-0.5 rounded-lg border border-card-border bg-main-bg/50">
                <button
                  type="button"
                  :disabled="item.visitor_count <= 1 || !canDeleteEntry(item.entry_at)"
                  @click="emit('updateCount', item, -1)"
                  class="w-5 h-5 rounded flex items-center justify-center hover:bg-rose-500/10 hover:text-rose-600 text-main-text/50 disabled:opacity-20 cursor-pointer"
                  title="Decrement count (-1)"
                >
                  <Minus class="w-3 h-3" />
                </button>
                <span class="font-mono font-bold text-xs min-w-[20px] text-center text-main-text select-none">
                  {{ item.visitor_count }}
                </span>
                <button
                  type="button"
                  :disabled="item.visitor_count >= 100 || !canDeleteEntry(item.entry_at)"
                  @click="emit('updateCount', item, 1)"
                  class="w-5 h-5 rounded flex items-center justify-center hover:bg-emerald-500/10 hover:text-emerald-600 text-main-text/50 disabled:opacity-20 cursor-pointer"
                  title="Increment count (+1)"
                >
                  <Plus class="w-3 h-3" />
                </button>
              </div>
            </td>

            <!-- Library Branch -->
            <td class="px-4 py-3 text-main-text/70">
              <div class="flex items-center gap-1.5">
                <Building2 class="w-3.5 h-3.5 text-main-text/40 shrink-0" />
                <span class="truncate font-medium">{{ langStore.localizeBranch(item.library) }}</span>
              </div>
            </td>

            <!-- 12-hour Time & Date -->
            <td class="px-4 py-3 text-main-text/75 font-mono text-[11px]">
              <div class="flex flex-col">
                <span class="font-semibold text-main-text flex items-center gap-1">
                  <Clock class="w-3 h-3 text-main-text/40 shrink-0" />
                  {{ getTimeDisplay(item.entry_at) }}
                </span>
                <span class="text-[10px] text-main-text/45">
                  {{ getDateDisplay(item.entry_at) }}
                </span>
              </div>
            </td>

            <!-- Delete with 2-minute safety check -->
            <td class="px-4 py-3 text-right">
              <button
                type="button"
                :disabled="!canDeleteEntry(item.entry_at)"
                @click="emit('delete', item)"
                class="p-1 rounded-md text-main-text/40 hover:text-rose-600 hover:bg-rose-500/10 transition disabled:opacity-20 disabled:cursor-not-allowed cursor-pointer"
                :title="canDeleteEntry(item.entry_at) ? langStore.t('attendance.cancel_entry', 'Cancel this entry (within 2-minute window)') : langStore.t('attendance.cancel_notice', 'Entries can be cancelled within 2 minutes of logging.')"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

  </div>
</template>
