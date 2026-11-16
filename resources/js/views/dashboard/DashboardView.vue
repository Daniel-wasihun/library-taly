<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header matching Gibi-Gubae -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
          <LayoutDashboard class="w-6 h-6 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('nav.dashboard', 'Overview Dashboard') }}</span>
        </h2>
        <p class="text-xs text-main-text/50 mt-0.5">
          {{ todayDate }} · {{ langStore.t('app.tagline', 'Academic Excellence & Automated Duty Enforcement') }}
        </p>
      </div>

      <!-- Quick Duty Pill in Header -->
      <div class="flex items-center gap-2">
        <span
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold border shadow-2xs"
          :class="authStore.isAdmin
            ? 'bg-brand-blue/10 dark:bg-brand-blue/20 text-brand-blue dark:text-blue-300 border-brand-blue/25'
            : (authStore.isOnDutyToday
                ? 'bg-emerald-500/10 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-500/25'
                : 'bg-amber-500/10 dark:bg-amber-950/40 text-amber-700 dark:text-brand-yellow border-amber-500/25')"
        >
          <span
            class="w-2 h-2 rounded-full"
            :class="authStore.isAdmin ? 'bg-brand-blue' : (authStore.isOnDutyToday ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500')"
          ></span>
          {{ authStore.isAdmin
            ? langStore.t('duty.admin_override', 'Admin Override Active')
            : (authStore.isOnDutyToday ? langStore.t('duty.on_duty', 'On Duty Today') : langStore.t('duty.off_duty', 'Off Duty')) }}
        </span>

        <router-link
          to="/attendance"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-brand-blue text-white hover:bg-brand-blue/90 shadow-2xs transition"
        >
          <Activity class="w-3.5 h-3.5" />
          <span>{{ langStore.t('attendance.live_desk', 'Live Usage Counter') }}</span>
        </router-link>
      </div>
    </div>

    <!-- Duty Turn Alert Banner -->
    <div
      v-if="!authStore.isAdmin && !authStore.canLogAttendance"
      class="rounded-xl border p-4 flex items-start gap-3 bg-amber-500/10 dark:bg-amber-950/40 border-amber-500/30 text-amber-800 dark:text-amber-200"
    >
      <AlertTriangle class="w-5 h-5 text-amber-600 dark:text-brand-yellow shrink-0 mt-0.5" />
      <div>
        <p class="text-sm font-bold">{{ langStore.t('duty.restricted_notice', 'Desk Logging Restricted — Off Duty Today') }}</p>
        <p class="text-xs mt-0.5 text-main-text/70" v-if="stats?.current_user_duty?.reason">
          {{ stats.current_user_duty.reason }}
        </p>
        <p class="text-xs mt-1 font-semibold text-amber-700 dark:text-brand-yellow" v-if="stats?.current_user_duty?.next_turn">
          {{ langStore.t('duty.next_turn', 'Your next turn') }}: <span class="font-mono underline">{{ stats.current_user_duty.next_turn }}</span>
        </p>
      </div>
    </div>

    <!-- Stats Grid matching Gibi-Gubae -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3.5">
      <StatCard
        :label="langStore.t('attendance.today_total', 'Today Total Visits')"
        :value="stats?.stats?.today_total ?? 0"
        :icon="Activity"
        variant="blue"
        :loading="loading"
      />
      <StatCard
        :label="langStore.t('attendance.today_male', 'Male Visitors')"
        :value="stats?.stats?.today_male ?? 0"
        :icon="Users"
        variant="purple"
        :loading="loading"
      />
      <StatCard
        :label="langStore.t('attendance.today_female', 'Female Visitors')"
        :value="stats?.stats?.today_female ?? 0"
        :icon="Users"
        variant="yellow"
        :loading="loading"
      />
      <StatCard
        :label="langStore.t('attendance.branches', 'Library Branches')"
        :value="stats?.stats?.total_branches ?? 0"
        :icon="Library"
        variant="green"
        :loading="loading"
      />
      <StatCard
        :label="langStore.t('attendance.active_staff', 'Active Staff')"
        :value="stats?.stats?.total_staff ?? 0"
        :icon="ShieldCheck"
        variant="blue"
        :loading="loading"
      />
      <StatCard
        :label="langStore.t('duty.shifts_today', 'Staff On Duty')"
        :value="stats?.stats?.on_duty_staff_today?.length ?? 0"
        :icon="Clock"
        variant="green"
        :loading="loading"
      />
    </div>

    <!-- Two-Column Section matching Gibi-Gubae -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Recent Attendance Entries -->
      <div class="lg:col-span-2 bg-card-bg border border-card-border rounded-xl p-5 shadow-2xs">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-main-text flex items-center gap-2">
            <Activity class="w-4 h-4 text-nav-accent" />
            <span>{{ langStore.t('attendance.recent_entries', 'Recent Usage Entries') }}</span>
          </h3>
          <router-link to="/attendance" class="text-xs text-nav-accent font-semibold hover:underline">
            {{ langStore.t('common.view_all', 'Open Live Counter →') }}
          </router-link>
        </div>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 5" :key="i" class="h-12 bg-slate-100 dark:bg-slate-800/60 rounded-lg animate-pulse" />
        </div>

        <div v-else-if="!stats?.recent_entries?.length" class="py-10 text-center text-xs text-main-text/40">
          {{ langStore.t('common.no_records', 'No attendance entries logged yet today.') }}
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="entry in stats?.recent_entries"
            :key="entry.id"
            class="flex items-center justify-between gap-3 p-3 rounded-lg border border-card-border/60 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition text-xs"
          >
            <div class="flex items-center gap-3 min-w-0">
              <span
                class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs shrink-0 font-mono shadow-2xs"
                :class="entry.gender === 'male'
                  ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
                  : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20'"
              >
                +{{ entry.visitor_count }}
              </span>

              <div class="min-w-0">
                <p class="font-bold text-main-text truncate flex items-center gap-2">
                  <span class="capitalize">{{ entry.gender }}</span>
                  <span class="text-main-text/40 font-normal">·</span>
                  <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase bg-slate-100 dark:bg-slate-800 text-main-text/70 border border-card-border">
                    {{ entry.user_type }}
                  </span>
                  <span v-if="entry.library" class="text-[11px] text-main-text/60 truncate">
                    @ {{ entry.library.name }}
                  </span>
                </p>
                <p class="text-[11px] text-main-text/50 truncate mt-0.5">
                  Logged by {{ entry.staff?.name ?? 'Desk Staff' }} · {{ formatTime(entry.entry_at) }}
                </p>
              </div>
            </div>

            <span class="text-[11px] text-main-text/50 font-mono shrink-0">
              {{ formatDate(entry.entry_at) }}
            </span>
          </div>
        </div>
      </div>

      <!-- On-Duty Staff Today -->
      <div class="bg-card-bg border border-card-border rounded-xl p-5 shadow-2xs">
        <h3 class="text-sm font-bold text-main-text flex items-center gap-2 mb-4">
          <Users class="w-4 h-4 text-nav-accent" />
          <span>{{ langStore.t('duty.shifts_today', 'On Duty Staff Today') }}</span>
        </h3>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="h-16 bg-slate-100 dark:bg-slate-800/60 rounded-lg animate-pulse" />
        </div>

        <div v-else-if="!stats?.stats?.on_duty_staff_today?.length" class="py-10 text-center space-y-2">
          <CalendarOff class="w-8 h-8 text-main-text/30 mx-auto" />
          <p class="text-xs text-main-text/40">No librarians scheduled on duty today.</p>
        </div>

        <div v-else class="space-y-2.5">
          <div
            v-for="entry in stats?.stats?.on_duty_staff_today"
            :key="`${entry.user_id}-${entry.task_key}`"
            class="p-3 rounded-lg border border-emerald-500/25 bg-emerald-500/8 dark:bg-emerald-950/30 text-xs"
          >
            <div class="flex items-center justify-between gap-2">
              <p class="font-bold text-emerald-800 dark:text-emerald-300 truncate">{{ entry.user_name }}</p>
              <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 uppercase">
                Active
              </span>
            </div>
            <p class="text-[11px] text-emerald-700 dark:text-emerald-400 mt-1 font-medium">{{ entry.task_name }}</p>
            <p class="text-[10px] text-main-text/50 font-mono mt-0.5">{{ entry.library_name }} · {{ entry.start_time }} – {{ entry.end_time }}</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { useLanguageStore } from '@/stores/languageStore';
import { dashboardApi } from '@/api';
import StatCard from '@/components/ui/StatCard.vue';
import {
  LayoutDashboard,
  AlertTriangle,
  Activity,
  Library,
  Users,
  ShieldCheck,
  Clock,
  CalendarOff,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const langStore = useLanguageStore();

const loading = ref(true);
const stats = ref<any>(null);

const todayDate = computed(() => {
  return new Date().toLocaleDateString(langStore.currentLanguage === 'am' ? 'am-ET' : 'en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
  });
});

const formatDate = (d: string) => {
  if (!d) return '—';
  return new Date(d).toLocaleDateString(langStore.currentLanguage === 'am' ? 'am-ET' : 'en-US', {
    month: 'short',
    day: 'numeric',
  });
};

const formatTime = (d: string) => {
  if (!d) return '—';
  return new Date(d).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};

onMounted(async () => {
  try {
    const { data } = await dashboardApi.stats();
    stats.value = data;
  } finally {
    loading.value = false;
  }
});
</script>
