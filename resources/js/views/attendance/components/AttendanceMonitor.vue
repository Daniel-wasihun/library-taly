<script setup lang="ts">
import { History, Download, Plus, Minus, Trash2, Users, Building2, Clock, UserCheck } from 'lucide-vue-next';
import Button from '@/components/ui/Button.vue';
import { useLanguageStore } from '@/stores/languageStore';
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
  (e: 'updateCount', log: Attendance, delta: number): void;
  (e: 'delete', log: Attendance): void;
}>();

const langStore = useLanguageStore();

const formatTime = (dateStr: string) => {
  if (!dateStr) return '—';
  const d = new Date(dateStr);
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
  <div class="lg:col-span-7 xl:col-span-8 bg-card-bg border border-card-border rounded-2xl flex flex-col shadow-2xs overflow-hidden">
    <!-- Header with Live Metric Chips & Export Button -->
    <div class="px-5 py-4 border-b border-card-border flex flex-wrap items-center justify-between gap-3 bg-slate-50/50 dark:bg-slate-900/40">
      <div class="flex items-center gap-2">
        <History class="w-4 h-4 text-brand-blue" />
        <h3 class="text-xs font-bold text-main-text uppercase tracking-wider">
          {{ langStore.t('attendance.recent_entries', 'Recent Live Entries') }}
        </h3>
      </div>

      <!-- Quick Metrics Counters -->
      <div class="flex items-center gap-4">
        <!-- Male Pill -->
        <div class="text-right">
          <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
            {{ langStore.t('attendance.male', 'Male') }}
          </span>
          <div class="text-base font-bold text-blue-600 dark:text-blue-400 font-mono leading-none mt-0.5">
            {{ dailyStats.male }}
          </div>
        </div>

        <div class="h-6 w-px bg-card-border"></div>

        <!-- Female Pill -->
        <div class="text-right">
          <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">
            {{ langStore.t('attendance.female', 'Female') }}
          </span>
          <div class="text-base font-bold text-amber-600 dark:text-amber-400 font-mono leading-none mt-0.5">
            {{ dailyStats.female }}
          </div>
        </div>

        <div class="h-6 w-px bg-card-border"></div>

        <!-- Total Pill -->
        <div class="text-right pr-2">
          <span class="text-[10px] font-bold text-main-text/60 uppercase tracking-wider">
            {{ langStore.t('attendance.total_today', 'Total Today') }}
          </span>
          <div class="text-lg font-bold text-main-text font-mono leading-none mt-0.5">
            {{ dailyStats.total }}
          </div>
        </div>

        <!-- Quick Export CSV -->
        <Button
          variant="secondary"
          size="sm"
          :icon="Download"
          @click="emit('export')"
        >
          CSV
        </Button>
      </div>
    </div>

    <!-- Live Entries Table -->
    <div class="flex-1 overflow-x-auto">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="border-b border-card-border bg-slate-50/60 dark:bg-slate-800/40 select-none">
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">Staff Member</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">Patron Type</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px] text-center">Gender</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px] text-center">Visitor Count</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">Branch</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px]">Recorded At</th>
            <th class="px-4 py-3 font-bold text-main-text/60 uppercase text-[10px] text-right">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-card-border/60">
          <tr v-if="loading && logs.length === 0">
            <td colspan="7" class="px-4 py-12 text-center text-main-text/40">
              Loading recent entries...
            </td>
          </tr>
          <tr v-else-if="logs.length === 0">
            <td colspan="7" class="px-4 py-12 text-center text-main-text/40">
              No attendance records logged for today yet.
            </td>
          </tr>
          <tr
            v-for="item in logs"
            :key="item.id"
            class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition-colors"
          >
            <!-- Staff -->
            <td class="px-4 py-3 font-medium text-main-text">
              <div class="flex items-center gap-1.5">
                <UserCheck class="w-3.5 h-3.5 text-main-text/40 shrink-0" />
                <span class="truncate">{{ item.staff?.name || 'System' }}</span>
              </div>
            </td>

            <!-- User Type -->
            <td class="px-4 py-3">
              <span
                class="px-2 py-0.5 rounded-md text-[10px] font-bold capitalize"
                :class="item.user_type === 'student'
                  ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
                  : (item.user_type === 'teacher'
                      ? 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/20'
                      : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20')"
              >
                {{ item.user_type }}
              </span>
            </td>

            <!-- Gender -->
            <td class="px-4 py-3 text-center">
              <span
                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold capitalize"
                :class="item.gender === 'male'
                  ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
                  : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'"
              >
                {{ item.gender }}
              </span>
            </td>

            <!-- Count + Quick Buttons -->
            <td class="px-4 py-3 text-center">
              <div class="inline-flex items-center gap-1.5 p-0.5 rounded-lg border border-card-border bg-main-bg/50">
                <button
                  type="button"
                  :disabled="item.visitor_count <= 1"
                  @click="emit('updateCount', item, -1)"
                  class="w-5 h-5 rounded flex items-center justify-center hover:bg-rose-500/10 hover:text-rose-600 text-main-text/50 disabled:opacity-20 cursor-pointer"
                >
                  <Minus class="w-3 h-3" />
                </button>
                <span class="font-mono font-bold text-xs min-w-[20px] text-center text-main-text">
                  {{ item.visitor_count }}
                </span>
                <button
                  type="button"
                  :disabled="item.visitor_count >= 50"
                  @click="emit('updateCount', item, 1)"
                  class="w-5 h-5 rounded flex items-center justify-center hover:bg-emerald-500/10 hover:text-emerald-600 text-main-text/50 disabled:opacity-20 cursor-pointer"
                >
                  <Plus class="w-3 h-3" />
                </button>
              </div>
            </td>

            <!-- Library Branch -->
            <td class="px-4 py-3 text-main-text/70">
              <div class="flex items-center gap-1.5">
                <Building2 class="w-3.5 h-3.5 text-main-text/40 shrink-0" />
                <span class="truncate">{{ item.library?.name || 'General' }}</span>
              </div>
            </td>

            <!-- Entry Time -->
            <td class="px-4 py-3 text-main-text/60 font-mono text-[11px]">
              <div class="flex items-center gap-1">
                <Clock class="w-3 h-3 text-main-text/40" />
                <span>{{ formatTime(item.entry_at) }}</span>
              </div>
            </td>

            <!-- Delete with 2-minute safety check -->
            <td class="px-4 py-3 text-right">
              <button
                type="button"
                :disabled="!canDeleteEntry(item.entry_at)"
                @click="emit('delete', item)"
                class="p-1 rounded-md text-main-text/40 hover:text-rose-600 hover:bg-rose-500/10 transition disabled:opacity-20 disabled:cursor-not-allowed cursor-pointer"
                :title="canDeleteEntry(item.entry_at) ? 'Cancel this entry (within 2-minute window)' : 'Cannot cancel: older than 2 minutes'"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
