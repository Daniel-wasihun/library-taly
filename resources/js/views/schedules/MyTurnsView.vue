<template>
  <div class="space-y-6 w-full">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
          <Clock class="w-6 h-6 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('nav.my_turns', 'My Duty Turns') }}</span>
        </h2>
        <p class="text-xs text-main-text/50 mt-0.5">
          Your personal duty calendar, turn schedules, and task authorizations
        </p>
      </div>

      <!-- Live Duty Badge -->
      <span
        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-sm text-xs font-semibold border shadow-2xs w-fit"
        :class="scheduleStore.isAdmin
          ? 'bg-brand-blue/10 dark:bg-brand-blue/20 text-brand-blue dark:text-blue-300 border-brand-blue/25'
          : (scheduleStore.todayTurns.length
              ? 'bg-emerald-500/10 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-500/25'
              : 'bg-amber-500/10 dark:bg-amber-950/40 text-amber-700 dark:text-brand-yellow border-amber-500/25')"
      >
        <span
          class="w-2 h-2 rounded-full"
          :class="scheduleStore.isAdmin ? 'bg-brand-blue' : (scheduleStore.todayTurns.length ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500')"
        ></span>
        {{ scheduleStore.isAdmin
          ? langStore.t('duty.admin_override', 'Admin Override Active')
          : (scheduleStore.todayTurns.length ? langStore.t('duty.on_duty', 'On Duty Today') : langStore.t('duty.off_duty', 'Off Duty')) }}
      </span>
    </div>

    <!-- Admin Master Banner -->
    <div
      v-if="scheduleStore.isAdmin"
      class="rounded-sm p-4 flex items-start gap-3 bg-brand-blue/10 dark:bg-brand-blue/20 border border-brand-blue/25 text-brand-blue dark:text-blue-200"
    >
      <Shield class="w-5 h-5 text-brand-blue dark:text-brand-yellow shrink-0 mt-0.5" />
      <div>
        <p class="text-sm font-bold">{{ langStore.t('duty.admin_override', 'Administrative Master Override Active') }}</p>
        <p class="text-xs text-main-text/70 mt-0.5">
          You hold Super Admin / Head Librarian status. You can perform all library tasks (circulation, audit, waivers) without schedule restrictions.
        </p>
      </div>
    </div>

    <!-- Today's Active Shifts -->
    <div class="bg-card-bg border border-card-border rounded-sm p-5 shadow-2xs space-y-4">
      <h3 class="text-sm font-bold text-main-text flex items-center gap-2">
        <Zap class="w-4 h-4 text-brand-yellow" />
        <span>{{ langStore.t('duty.shifts_today', "Today's Active Shifts") }}</span>
      </h3>

      <div
        v-if="!scheduleStore.isAdmin && !scheduleStore.todayTurns.length"
        class="py-10 text-center space-y-2"
      >
        <CalendarOff class="w-8 h-8 text-main-text/30 mx-auto" />
        <p class="text-xs font-semibold text-main-text/60">No shifts scheduled for today</p>
        <p class="text-[11px] text-main-text/40">You are off-duty today. Review your upcoming scheduled turns below.</p>
      </div>

      <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
        <div
          v-for="shift in (scheduleStore.isAdmin ? [] : scheduleStore.todayTurns)"
          :key="shift.id"
          class="p-4 rounded-sm border border-emerald-500/30 bg-emerald-500/8 dark:bg-emerald-950/30 flex items-start gap-3"
        >
          <div class="w-9 h-9 rounded-xs bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-2xs">
            <Clock class="w-5 h-5 stroke-[2.2]" />
          </div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center justify-between gap-2">
              <p class="text-xs font-bold text-emerald-800 dark:text-emerald-300 truncate">
                {{ availableTasks[shift.task_key] || shift.task_key }}
              </p>
              <span class="px-1.5 py-0.5 rounded-xs text-[9px] font-bold bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 uppercase">
                Active Turn
              </span>
            </div>
            <p class="text-xs font-mono text-emerald-700 dark:text-emerald-400 mt-1">
              {{ shift.start_time }} – {{ shift.end_time }}
            </p>
            <p v-if="shift.notes" class="text-[11px] text-main-text/60 mt-1">
              {{ shift.notes }}
            </p>
          </div>
        </div>

        <div
          v-if="scheduleStore.isAdmin"
          class="p-4 rounded-sm border border-brand-blue/30 bg-brand-blue/8 dark:bg-brand-blue/20 flex items-start gap-3"
        >
          <div class="w-9 h-9 rounded-xs bg-brand-blue text-white flex items-center justify-center font-bold text-sm shrink-0 shadow-2xs">
            <Shield class="w-5 h-5 stroke-[2.2]" />
          </div>
          <div>
            <p class="text-xs font-bold text-brand-blue dark:text-blue-300">All Operations Unlocked</p>
            <p class="text-[11px] text-main-text/60 mt-0.5">Admin privilege automatically satisfies all duty schedule checks.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Upcoming Turns -->
    <div class="bg-card-bg border border-card-border rounded-sm p-5 shadow-2xs space-y-4">
      <h3 class="text-sm font-bold text-main-text flex items-center gap-2">
        <CalendarDays class="w-4 h-4 text-nav-accent" />
        <span>{{ langStore.t('duty.next_turn', 'Upcoming Scheduled Turns') }}</span>
      </h3>

      <div v-if="!scheduleStore.myTurns.length" class="py-10 text-center text-xs text-main-text/40">
        No upcoming duty shifts on your calendar.
      </div>

      <div v-else class="space-y-2">
        <div
          v-for="shift in scheduleStore.myTurns"
          :key="shift.id"
          class="flex items-center justify-between gap-3 p-3.5 rounded-lg border border-card-border/70 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition text-xs"
        >
          <div class="min-w-0">
            <p class="font-bold text-main-text truncate">
              {{ availableTasks[shift.task_key] || shift.task_key }}
            </p>
            <p class="text-[11px] text-main-text/50 font-mono mt-0.5">
              {{ formatDate(shift.shift_date as string) }} · {{ shift.start_time }} – {{ shift.end_time }}
            </p>
          </div>
          <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-blue-500/10 text-blue-700 dark:text-blue-300 border border-blue-500/20 capitalize">
            {{ shift.status }}
          </span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { useScheduleStore } from '@/stores/scheduleStore';
import { useLanguageStore } from '@/stores/languageStore';
import { Clock, Shield, Zap, CalendarOff, CalendarDays } from 'lucide-vue-next';

const scheduleStore = useScheduleStore();
const langStore = useLanguageStore();

const availableTasks = computed(() => scheduleStore.availableTasks);

const formatDate = (d: string) => {
  if (!d) return '—';
  return new Date(d).toLocaleDateString(langStore.currentLanguage === 'am' ? 'am-ET' : 'en-US', {
    weekday: 'short',
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
};

onMounted(() => scheduleStore.fetchMyTurns());
</script>
