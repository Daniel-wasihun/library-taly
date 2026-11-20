<template>
  <div class="space-y-6 w-full max-w-7xl mx-auto pb-10">
    <!-- Institutional Command Center Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-card-bg border border-card-border rounded-md p-4 sm:p-5 shadow-2xs">
      <div>
        <div class="flex items-center gap-2">
          <h2 class="text-lg sm:text-xl font-bold text-main-text tracking-tight flex items-center gap-2">
            <LayoutDashboard class="w-5 h-5 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('nav.dashboard', 'Dashboard') }}</span>
          </h2>
          <span class="text-[10px] font-mono px-2 py-0.5 rounded font-bold uppercase bg-brand-blue/10 text-brand-blue border border-brand-blue/20">
            {{ langStore.isAmharic() ? 'ወልድያ ዩኒቨርሲቲ' : 'Woldia University' }}
          </span>
        </div>
        <p class="text-xs text-main-text/60 mt-1 flex items-center gap-2 flex-wrap">
          <span class="font-mono font-medium text-brand-blue dark:text-brand-yellow">{{ todayDate }}</span>
          <span>·</span>
          <span>{{ langStore.t('app.tagline', 'Academic Excellence & Automated Duty Enforcement') }}</span>
        </p>
      </div>

      <!-- Quick Action Navigation Hub -->
      <div class="flex items-center gap-2 flex-wrap">
        <router-link
          v-if="authStore.canRecordAttendance"
          to="/attendance"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold bg-brand-blue text-white hover:bg-blue-900 shadow-2xs transition active:scale-95 cursor-pointer"
        >
          <Activity class="w-3.5 h-3.5" />
          <span>{{ langStore.t('attendance.live_desk', 'Live Counter') }}</span>
        </router-link>

        <router-link
          v-if="authStore.canManageAttendanceAnalytics"
          to="/attendance"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold border border-card-border bg-card-bg hover:bg-nav-accent/10 hover:border-nav-accent text-main-text shadow-2xs transition active:scale-95 cursor-pointer"
        >
          <TrendingUp class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('attendance.analytics', 'Analytics & Reports') }}</span>
        </router-link>

        <router-link
          to="/my-turns"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-bold border border-card-border bg-card-bg hover:bg-nav-accent/10 hover:border-nav-accent text-main-text shadow-2xs transition active:scale-95 cursor-pointer"
        >
          <Clock class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.t('nav.my_turns', 'My Shifts') }}</span>
        </router-link>
      </div>
    </div>

    <!-- Duty Turn Notification Banner (For Non-Admin Attendants when Off-Duty) -->
    <div
      v-if="!authStore.isAdmin && !authStore.canLogAttendance"
      class="rounded-md border p-4 flex items-start gap-3 bg-amber-500/10 dark:bg-amber-950/40 border-amber-500/30 text-amber-800 dark:text-amber-200"
    >
      <AlertTriangle class="w-5 h-5 text-amber-600 dark:text-brand-yellow shrink-0 mt-0.5" />
      <div class="text-xs">
        <p class="font-bold text-sm">{{ langStore.t('duty.restricted_notice', 'Desk Logging Restricted — Off Duty Today') }}</p>
        <p class="mt-0.5 opacity-80" v-if="stats?.current_user_duty?.reason">
          {{ stats.current_user_duty.reason }}
        </p>
        <p class="mt-1 font-semibold text-amber-700 dark:text-brand-yellow" v-if="stats?.current_user_duty?.next_turn">
          {{ langStore.t('duty.next_turn', 'Your next turn') }}: <span class="font-mono underline">{{ stats.current_user_duty.next_turn }}</span>
        </p>
      </div>
    </div>

    <!-- Metric Insight Cards (6 Core Performance Indicators) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <!-- Total Patrons Today -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-main-text/50 uppercase tracking-wider">
          <span>{{ langStore.t('attendance.today_total', 'Today Patrons') }}</span>
          <Activity class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow" />
        </div>
        <div class="text-2xl font-bold text-brand-blue dark:text-brand-yellow font-mono">
          {{ loading ? '—' : (stats?.stats?.today_total ?? 0).toLocaleString() }}
        </div>
        <div class="text-[10px] text-main-text/50 flex items-center gap-1 font-mono">
          <span>7-day:</span>
          <strong class="text-main-text">{{ loading ? '—' : (stats?.stats?.week_total ?? 0).toLocaleString() }}</strong>
        </div>
      </div>

      <!-- Students -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
          <span>{{ langStore.localizeType('student') }}</span>
          <GraduationCap class="w-3.5 h-3.5" />
        </div>
        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">
          {{ loading ? '—' : (stats?.stats?.today_student ?? 0).toLocaleString() }}
        </div>
        <div class="text-[10px] text-emerald-700/70 dark:text-emerald-300/70 font-mono">
          {{ stats?.stats?.student_percent ?? 0 }}% {{ langStore.t('attendance.of_traffic', 'of total') }}
        </div>
      </div>

      <!-- Faculty / Researchers -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-wider">
          <span>{{ langStore.localizeType('teacher') }}</span>
          <BookOpen class="w-3.5 h-3.5" />
        </div>
        <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 font-mono">
          {{ loading ? '—' : (stats?.stats?.today_teacher ?? 0).toLocaleString() }}
        </div>
        <div class="text-[10px] text-blue-700/70 dark:text-blue-300/70 font-mono">
          {{ stats?.stats?.teacher_percent ?? 0 }}% {{ langStore.t('attendance.of_traffic', 'of total') }}
        </div>
      </div>

      <!-- Administrative Staff -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider">
          <span>{{ langStore.localizeType('staff') }}</span>
          <Briefcase class="w-3.5 h-3.5" />
        </div>
        <div class="text-2xl font-bold text-purple-600 dark:text-purple-400 font-mono">
          {{ loading ? '—' : (stats?.stats?.today_staff ?? 0).toLocaleString() }}
        </div>
        <div class="text-[10px] text-purple-700/70 dark:text-purple-300/70 font-mono">
          {{ stats?.stats?.staff_percent ?? 0 }}% {{ langStore.t('attendance.of_traffic', 'of total') }}
        </div>
      </div>

      <!-- Campus Branches -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-main-text/50 uppercase tracking-wider">
          <span>{{ langStore.t('attendance.branches', 'Branches') }}</span>
          <Building2 class="w-3.5 h-3.5 text-brand-blue" />
        </div>
        <div class="text-2xl font-bold text-main-text font-mono">
          {{ loading ? '—' : (stats?.stats?.total_branches ?? 0) }}
        </div>
        <div class="text-[10px] text-main-text/50 truncate">
          {{ langStore.isAmharic() ? 'ከፍተኛ:' : 'Top:' }} <span class="font-bold text-brand-blue truncate">{{ stats?.stats?.busiest_branch ?? 'Natural' }}</span>
        </div>
      </div>

      <!-- Active Staff On Shift Today -->
      <div class="bg-card-bg border border-card-border rounded-md p-4 shadow-2xs space-y-1">
        <div class="flex items-center justify-between text-[10px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
          <span>{{ langStore.t('duty.shifts_today', 'On Duty Staff') }}</span>
          <ShieldCheck class="w-3.5 h-3.5 text-emerald-500" />
        </div>
        <div class="text-2xl font-bold text-emerald-600 dark:text-emerald-400 font-mono">
          {{ loading ? '—' : (stats?.stats?.on_duty_staff_today?.length ?? 0) }}
        </div>
        <div class="text-[10px] text-emerald-700/70 dark:text-emerald-300/70 font-mono">
          {{ langStore.isAmharic() ? 'በስራ ላይ ያሉ' : 'Assigned active' }}
        </div>
      </div>
    </div>

    <!-- Mid-Tier Analytics: Demographic Split & Branch Distribution -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
      <!-- Demographic Composition & Gender Proportions -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between border-b border-card-border pb-3">
          <h3 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <BarChart3 class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.demographic_breakdown', 'Patron Demographic Segmentation') }}</span>
          </h3>
          <span class="text-[10px] font-mono text-main-text/50">
            {{ langStore.isAmharic() ? 'የዛሬ የቀጥታ ምጣኔ' : "Today's Live Proportions" }}
          </span>
        </div>

        <!-- Gender Proportional Split Bar -->
        <div class="space-y-1.5">
          <div class="flex justify-between text-[11px] font-bold">
            <span class="text-brand-blue">
              {{ langStore.localizeGender('male') }}: {{ stats?.stats?.today_male ?? 0 }} ({{ stats?.stats?.male_percent ?? 50 }}%)
            </span>
            <span class="text-amber-600 dark:text-brand-yellow">
              {{ langStore.localizeGender('female') }}: {{ stats?.stats?.today_female ?? 0 }} ({{ stats?.stats?.female_percent ?? 50 }}%)
            </span>
          </div>
          <div class="w-full h-2.5 rounded-full overflow-hidden flex bg-slate-100 dark:bg-slate-800 shadow-inner">
            <div
              class="bg-brand-blue h-full transition-all duration-700"
              :style="{ width: `${stats?.stats?.male_percent ?? 50}%` }"
            ></div>
            <div
              class="bg-brand-yellow h-full transition-all duration-700"
              :style="{ width: `${stats?.stats?.female_percent ?? 50}%` }"
            ></div>
          </div>
        </div>

        <!-- Patron Type Distribution Progress Bars -->
        <div class="space-y-3 pt-2">
          <!-- Students -->
          <div class="space-y-1">
            <div class="flex justify-between text-xs">
              <span class="font-bold text-main-text flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                {{ langStore.localizeType('student') }}
              </span>
              <span class="font-mono text-main-text/70">
                <strong>{{ (stats?.stats?.today_student ?? 0).toLocaleString() }}</strong> ({{ stats?.stats?.student_percent ?? 0 }}%)
              </span>
            </div>
            <div class="w-full h-1.5 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
              <div
                class="bg-emerald-500 h-full rounded-full transition-all duration-500"
                :style="{ width: `${stats?.stats?.student_percent ?? 0}%` }"
              ></div>
            </div>
          </div>

          <!-- Teachers / Faculty -->
          <div class="space-y-1">
            <div class="flex justify-between text-xs">
              <span class="font-bold text-main-text flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                {{ langStore.localizeType('teacher') }}
              </span>
              <span class="font-mono text-main-text/70">
                <strong>{{ (stats?.stats?.today_teacher ?? 0).toLocaleString() }}</strong> ({{ stats?.stats?.teacher_percent ?? 0 }}%)
              </span>
            </div>
            <div class="w-full h-1.5 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
              <div
                class="bg-blue-500 h-full rounded-full transition-all duration-500"
                :style="{ width: `${stats?.stats?.teacher_percent ?? 0}%` }"
              ></div>
            </div>
          </div>

          <!-- Staff -->
          <div class="space-y-1">
            <div class="flex justify-between text-xs">
              <span class="font-bold text-main-text flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                {{ langStore.localizeType('staff') }}
              </span>
              <span class="font-mono text-main-text/70">
                <strong>{{ (stats?.stats?.today_staff ?? 0).toLocaleString() }}</strong> ({{ stats?.stats?.staff_percent ?? 0 }}%)
              </span>
            </div>
            <div class="w-full h-1.5 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
              <div
                class="bg-purple-500 h-full rounded-full transition-all duration-500"
                :style="{ width: `${stats?.stats?.staff_percent ?? 0}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Campus Library Facilities Foot-Traffic Breakdown -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between border-b border-card-border pb-3">
          <h3 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <Building2 class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.branch_comparison', 'Campus Facilities Foot-Traffic') }}</span>
          </h3>
          <router-link
            to="/libraries"
            class="text-[11px] font-semibold text-brand-blue dark:text-brand-yellow hover:underline"
          >
            {{ langStore.isAmharic() ? 'ሁሉንም ቅርንጫፎች ይመልከቱ →' : 'Manage Branches →' }}
          </router-link>
        </div>

        <div v-if="loading" class="space-y-3">
          <div v-for="i in 3" :key="i" class="h-10 bg-slate-100 dark:bg-slate-800/60 rounded animate-pulse"></div>
        </div>

        <div v-else-if="!stats?.stats?.branches_traffic?.length" class="py-8 text-center text-xs text-main-text/40">
          {{ langStore.t('common.no_data', 'No branch traffic logged yet today.') }}
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="branch in stats?.stats?.branches_traffic"
            :key="branch.id"
            class="p-2.5 rounded border border-card-border/60 hover:bg-main-bg/50 transition space-y-1.5"
          >
            <div class="flex items-center justify-between text-xs">
              <div class="flex items-center gap-2 min-w-0">
                <span class="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-brand-blue/10 text-brand-blue border border-brand-blue/20 shrink-0">
                  {{ branch.code }}
                </span>
                <span class="font-bold text-main-text truncate">
                  {{ langStore.isAmharic() ? (branch.name_am || branch.name) : branch.name }}
                </span>
              </div>
              <div class="font-mono text-xs text-right shrink-0">
                <strong class="text-brand-blue dark:text-brand-yellow">{{ branch.total }}</strong>
                <span class="text-main-text/50 ml-1">({{ branch.percent }}%)</span>
              </div>
            </div>
            <div class="w-full h-1.5 rounded-full overflow-hidden bg-slate-200 dark:bg-slate-700">
              <div
                class="bg-brand-blue dark:bg-brand-yellow h-full rounded-full transition-all duration-500"
                :style="{ width: `${branch.percent}%` }"
              ></div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Tier: Live Gate Activity Feed & Today's Duty Deployment -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Recent Patron Usage Feed (2 Cols) -->
      <div class="lg:col-span-2 bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between border-b border-card-border pb-3">
          <h3 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <Activity class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('attendance.recent_entries', 'Recent Usage Entries') }}</span>
          </h3>
          <router-link
            to="/attendance"
            class="text-xs font-semibold text-brand-blue dark:text-brand-yellow hover:underline"
          >
            {{ langStore.t('common.view_all', 'Open Live Counter →') }}
          </router-link>
        </div>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 5" :key="i" class="h-12 bg-slate-100 dark:bg-slate-800/60 rounded animate-pulse" />
        </div>

        <div v-else-if="!stats?.recent_entries?.length" class="py-10 text-center text-xs text-main-text/40">
          {{ langStore.t('common.no_records', 'No attendance entries logged yet today.') }}
        </div>

        <div v-else class="space-y-2">
          <div
            v-for="entry in stats?.recent_entries"
            :key="entry.id"
            class="flex items-center justify-between gap-3 p-2.5 rounded border border-card-border/60 hover:bg-slate-50/60 dark:hover:bg-slate-800/30 transition text-xs"
          >
            <div class="flex items-center gap-3 min-w-0">
              <span
                class="w-8 h-8 rounded flex items-center justify-center font-bold text-xs shrink-0 font-mono shadow-2xs"
                :class="entry.gender === 'male'
                  ? 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20'
                  : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20'"
              >
                +{{ entry.visitor_count }}
              </span>

              <div class="min-w-0">
                <p class="font-bold text-main-text truncate flex items-center gap-2">
                  <span class="capitalize">{{ langStore.localizeGender(entry.gender) }}</span>
                  <span class="text-main-text/40 font-normal">·</span>
                  <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase bg-slate-100 dark:bg-slate-800 text-main-text/70 border border-card-border">
                    {{ langStore.localizeType(entry.user_type) }}
                  </span>
                  <span v-if="entry.library" class="text-[11px] text-main-text/60 truncate">
                    @ {{ langStore.localizeBranch(entry.library) }}
                  </span>
                </p>
                <p class="text-[10px] text-main-text/50 truncate mt-0.5">
                  {{ langStore.t('attendance.logged_by', 'Logged by') }} {{ entry.staff?.name ?? 'Desk Staff' }} · {{ formatTime12(entry.entry_at) }}
                </p>
              </div>
            </div>

            <span class="text-[11px] text-main-text/50 font-mono shrink-0">
              {{ formatDate(entry.entry_at) }}
            </span>
          </div>
        </div>
      </div>

      <!-- On-Duty Staff Deployment Today (1 Col) -->
      <div class="bg-card-bg border border-card-border rounded-md p-5 shadow-2xs space-y-4">
        <div class="flex items-center justify-between border-b border-card-border pb-3">
          <h3 class="text-xs font-bold text-main-text uppercase tracking-wider flex items-center gap-2">
            <Users class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
            <span>{{ langStore.t('duty.shifts_today', 'On Duty Staff Today') }}</span>
          </h3>
          <span class="text-[10px] font-mono text-emerald-600 dark:text-emerald-400 font-bold bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
            {{ stats?.stats?.on_duty_staff_today?.length ?? 0 }} Active
          </span>
        </div>

        <div v-if="loading" class="space-y-2">
          <div v-for="i in 3" :key="i" class="h-14 bg-slate-100 dark:bg-slate-800/60 rounded animate-pulse" />
        </div>

        <div v-else-if="!stats?.stats?.on_duty_staff_today?.length" class="py-10 text-center space-y-2">
          <CalendarOff class="w-8 h-8 text-main-text/30 mx-auto" />
          <p class="text-xs text-main-text/40">
            {{ langStore.isAmharic() ? 'ለዛሬ የተመደበ የቤተ-መጻሕፍት ተረኛ ሠራተኛ የለም።' : 'No librarians scheduled on duty today.' }}
          </p>
        </div>

        <div v-else class="space-y-2.5">
          <div
            v-for="entry in stats?.stats?.on_duty_staff_today"
            :key="`${entry.user_id}-${entry.task_key}`"
            class="p-2.5 rounded border border-emerald-500/25 bg-emerald-500/8 dark:bg-emerald-950/30 text-xs space-y-1"
          >
            <div class="flex items-center justify-between gap-2">
              <p class="font-bold text-emerald-800 dark:text-emerald-300 truncate">{{ entry.user_name }}</p>
              <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 uppercase flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                Active
              </span>
            </div>
            <p class="text-[11px] text-emerald-700 dark:text-emerald-400 font-medium">{{ entry.task_name }}</p>
            <p class="text-[10px] text-main-text/50 font-mono">
              {{ entry.library_name }} · {{ formatTimeStr12(entry.start_time) }} – {{ formatTimeStr12(entry.end_time) }}
            </p>
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
import { formatDT } from '@/utils/ethiopianDate';
import {
  LayoutDashboard,
  AlertTriangle,
  Activity,
  Building2,
  Users,
  ShieldCheck,
  Clock,
  CalendarOff,
  GraduationCap,
  BookOpen,
  Briefcase,
  TrendingUp,
  BarChart3,
} from 'lucide-vue-next';

const authStore = useAuthStore();
const langStore = useLanguageStore();

const loading = ref(true);
const stats = ref<any>(null);

const todayDate = computed(() => {
  return formatDT(new Date(), langStore.isAmharic()).full;
});

const formatDate = (d: string) => {
  if (!d) return '—';
  return formatDT(d, langStore.isAmharic()).date;
};

const formatTime12 = (d: string) => {
  if (!d) return '—';
  return formatDT(d, langStore.isAmharic()).time;
};

const formatTimeStr12 = (timeStr: string) => {
  if (!timeStr) return '—';
  const parts = timeStr.split(':');
  if (parts.length >= 2) {
    const today = new Date();
    today.setHours(parseInt(parts[0], 10), parseInt(parts[1], 10), 0);
    return formatDT(today, langStore.isAmharic()).time;
  }
  return timeStr;
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
