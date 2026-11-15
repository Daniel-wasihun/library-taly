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
      </div>
    </div>

    <!-- Duty Turn Alert Banner -->
    <div
      v-if="!authStore.isAdmin && !authStore.canCirculateBooks"
      class="rounded-xl border p-4 flex items-start gap-3 bg-amber-500/10 dark:bg-amber-950/40 border-amber-500/30 text-amber-800 dark:text-amber-200"
    >
      <AlertTriangle class="w-5 h-5 text-amber-600 dark:text-brand-yellow shrink-0 mt-0.5" />
      <div>
        <p class="text-sm font-bold">{{ langStore.t('duty.restricted_notice', 'Book Circulation Restricted — Off Duty Today') }}</p>
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
        :label="langStore.t('books.title', 'Total Books')"
        :value="stats?.stats?.total_books ?? 0"
        :icon="BookOpen"
        variant="blue"
        :loading="loading"
      />
      <StatCard
        :label="langStore.t('books.copies', 'Total Copies')"
        :value="stats?.stats?.total_copies ?? 0"
        :icon="Library"
        variant="purple"
        :loading="loading"
      />
      <StatCard
        label="Available Copies"
        :value="stats?.stats?.available_copies ?? 0"
        :icon="CheckSquare"
        variant="green"
        :loading="loading"
      />
      <StatCard
        :label="langStore.t('circulation.title', 'Active Loans')"
        :value="stats?.stats?.active_loans ?? 0"
        :icon="BookMarked"
        variant="blue"
        :loading="loading"
      />
      <StatCard
        label="Overdue Loans"
        :value="stats?.stats?.overdue_loans ?? 0"
        :icon="Clock"
        variant="red"
        :loading="loading"
      />
      <StatCard
        :label="langStore.t('users.title', 'Members')"
        :value="stats?.stats?.total_members ?? 0"
        :icon="Users"
        variant="yellow"
        :loading="loading"
      />
    </div>

    <!-- Two-Column Section matching Gibi-Gubae -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Recent Loans -->
      <div class="lg:col-span-2 bg-card-bg border border-card-border rounded-xl p-5 shadow-2xs">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-sm font-bold text-main-text flex items-center gap-2">
            <BookMarked class="w-4 h-4 text-nav-accent" />
            <span>{{ langStore.t('circulation.title', 'Recent Book Loans') }}</span>
          </h3>
          <router-link to="/circulation" class="text-xs text-nav-accent font-semibold hover:underline">
            View All →
          </router-link>
        </div>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 4" :key="i" class="h-12 bg-slate-100 dark:bg-slate-800/60 rounded-lg animate-pulse" />
        </div>

        <div v-else-if="!stats?.recent_loans?.length" class="py-10 text-center text-xs text-main-text/40">
          {{ langStore.t('common.no_records', 'No loan records found.') }}
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="loan in stats?.recent_loans"
            :key="loan.id"
            class="flex items-center justify-between gap-3 p-3 rounded-lg border border-card-border/60 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition text-xs"
          >
            <div class="min-w-0">
              <p class="font-bold text-main-text truncate">{{ loan.book?.title }}</p>
              <p class="text-[11px] text-main-text/50 truncate mt-0.5">
                {{ loan.member?.name }} (@{{ loan.member?.username }}) · Due {{ formatDate(loan.due_date) }}
              </p>
            </div>
            <StatusBadge :status="loan.status" />
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
            <p class="text-[10px] text-main-text/50 font-mono mt-0.5">{{ entry.start_time }} – {{ entry.end_time }}</p>
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
import StatusBadge from '@/components/ui/StatusBadge.vue';
import {
  LayoutDashboard,
  AlertTriangle,
  BookOpen,
  Library,
  CheckSquare,
  BookMarked,
  Clock,
  Users,
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
    year: 'numeric',
  });
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
