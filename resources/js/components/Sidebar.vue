<template>
  <aside
    class="w-64 bg-card-bg text-main-text flex flex-col shrink-0 border-r border-card-border transition-colors duration-200 select-none z-30"
  >
    <!-- Brand Header matching Senbet / Gibi-Gubae -->
    <div class="h-16 flex items-center px-5 gap-3 border-b border-card-border shrink-0">
      <!-- Crest Icon -->
      <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-brand-blue to-blue-700 text-white flex items-center justify-center font-bold text-base shadow-xs border border-white/20 shrink-0">
        📚
      </div>
      <div class="flex flex-col min-w-0">
        <span class="font-bold text-sm tracking-tight text-main-text leading-tight truncate">
          {{ langStore.t('app.name', 'UniLibrary Portal') }}
        </span>
        <span class="text-[10px] text-brand-blue dark:text-brand-yellow font-bold tracking-wider uppercase mt-0.5 truncate">
          {{ langStore.t('app.subtitle', 'University Library System') }}
        </span>
      </div>
    </div>

    <!-- Active Duty Turn Status Widget in Sidebar -->
    <div class="px-3 pt-3">
      <div
        class="p-2.5 rounded-xl border text-xs transition-colors"
        :class="authStore.isAdmin
          ? 'bg-brand-blue/10 dark:bg-brand-blue/20 border-brand-blue/25 text-brand-blue dark:text-blue-300'
          : (authStore.isOnDutyToday
              ? 'bg-emerald-500/10 dark:bg-emerald-950/40 border-emerald-500/25 text-emerald-700 dark:text-emerald-300'
              : 'bg-amber-500/10 dark:bg-amber-950/40 border-amber-500/25 text-amber-700 dark:text-brand-yellow')"
      >
        <div class="flex items-center gap-2">
          <span
            class="w-2 h-2 rounded-full"
            :class="authStore.isAdmin ? 'bg-brand-blue' : (authStore.isOnDutyToday ? 'bg-emerald-500 animate-pulse' : 'bg-amber-500')"
          ></span>
          <span class="font-bold text-[11px] leading-tight">
            {{ authStore.isAdmin
              ? langStore.t('duty.admin_override', 'Admin Override Active')
              : (authStore.isOnDutyToday ? langStore.t('duty.on_duty', 'On Duty Today') : langStore.t('duty.off_duty', 'Off Duty')) }}
          </span>
        </div>
        <p v-if="!authStore.isAdmin && authStore.user?.duty_status?.next_turn" class="text-[10px] mt-1 opacity-80 pl-4 font-mono">
          {{ langStore.t('duty.next_turn', 'Next turn') }}: {{ authStore.user.duty_status.next_turn }}
        </p>
      </div>
    </div>

    <!-- Navigation Menu with Groups -->
    <nav class="flex-1 px-3 py-4 space-y-5 overflow-y-auto custom-scrollbar">
      <!-- Group 1: General -->
      <div class="space-y-1">
        <div class="px-3 text-[10px] font-bold text-main-text/45 uppercase tracking-wider mb-1.5">
          {{ langStore.t('nav.group_general', 'General') }}
        </div>

        <!-- Dashboard -->
        <router-link
          to="/dashboard"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all duration-200 cursor-pointer relative group"
          :class="isActive('/dashboard')
            ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
            : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'"
        >
          <LayoutDashboard
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/dashboard') ? 'text-nav-accent' : 'text-main-text/45 group-hover:text-nav-accent'"
          />
          <span class="truncate">{{ langStore.t('nav.dashboard', 'Overview Dashboard') }}</span>
          <div
            v-if="isActive('/dashboard')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>

        <!-- My Turns -->
        <router-link
          to="/my-turns"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all duration-200 cursor-pointer relative group"
          :class="isActive('/my-turns')
            ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
            : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'"
        >
          <Clock
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/my-turns') ? 'text-nav-accent' : 'text-main-text/45 group-hover:text-nav-accent'"
          />
          <span class="truncate">{{ langStore.t('nav.my_turns', 'My Duty Turns') }}</span>
          <div
            v-if="isActive('/my-turns')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>
      </div>

      <!-- Group 2: Library Operations -->
      <div class="space-y-1">
        <div class="px-3 text-[10px] font-bold text-main-text/45 uppercase tracking-wider mb-1.5">
          {{ langStore.t('nav.group_library', 'Library Operations') }}
        </div>

        <!-- Book Catalog -->
        <router-link
          to="/books"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all duration-200 cursor-pointer relative group"
          :class="isActive('/books')
            ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
            : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'"
        >
          <BookOpen
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/books') ? 'text-nav-accent' : 'text-main-text/45 group-hover:text-nav-accent'"
          />
          <span class="truncate">{{ langStore.t('nav.books', 'Book Catalog') }}</span>
          <div
            v-if="isActive('/books')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>

        <!-- Circulation & Loans -->
        <router-link
          to="/circulation"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all duration-200 cursor-pointer relative group"
          :class="isActive('/circulation')
            ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
            : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'"
        >
          <BookMarked
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/circulation') ? 'text-nav-accent' : 'text-main-text/45 group-hover:text-nav-accent'"
          />
          <span class="truncate">{{ langStore.t('nav.circulation', 'Circulation & Loans') }}</span>
          <div
            v-if="isActive('/circulation')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>
      </div>

      <!-- Group 3: Administration & Security -->
      <div v-if="authStore.isAdmin || authStore.hasRole('Head Librarian')" class="space-y-1">
        <div class="px-3 text-[10px] font-bold text-main-text/45 uppercase tracking-wider mb-1.5">
          {{ langStore.t('nav.group_admin', 'Administration & Security') }}
        </div>

        <!-- Users Management -->
        <router-link
          to="/users"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all duration-200 cursor-pointer relative group"
          :class="isActive('/users')
            ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
            : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'"
        >
          <Users
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/users') ? 'text-nav-accent' : 'text-main-text/45 group-hover:text-nav-accent'"
          />
          <span class="truncate">{{ langStore.t('nav.users', 'User Accounts & Access') }}</span>
          <div
            v-if="isActive('/users')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>

        <!-- Roles & RBAC Matrix -->
        <router-link
          to="/roles"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all duration-200 cursor-pointer relative group"
          :class="isActive('/roles')
            ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
            : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'"
        >
          <ShieldCheck
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/roles') ? 'text-nav-accent' : 'text-main-text/45 group-hover:text-nav-accent'"
          />
          <span class="truncate">{{ langStore.t('nav.roles', 'Roles & RBAC Matrix') }}</span>
          <div
            v-if="isActive('/roles')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>

        <!-- Duty Schedules Management -->
        <router-link
          to="/schedules"
          class="flex items-center gap-3 px-3 py-2 rounded-lg text-xs transition-all duration-200 cursor-pointer relative group"
          :class="isActive('/schedules')
            ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
            : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'"
        >
          <CalendarDays
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/schedules') ? 'text-nav-accent' : 'text-main-text/45 group-hover:text-nav-accent'"
          />
          <span class="truncate">{{ langStore.t('nav.schedules', 'Duty Shift Schedules') }}</span>
          <div
            v-if="isActive('/schedules')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-6 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>
      </div>
    </nav>
  </aside>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useLanguageStore } from '@/stores/languageStore';
import {
  LayoutDashboard,
  Clock,
  BookOpen,
  BookMarked,
  Users,
  ShieldCheck,
  CalendarDays,
} from 'lucide-vue-next';

const route = useRoute();
const authStore = useAuthStore();
const langStore = useLanguageStore();

const isActive = (path: string) => {
  return route.path === path || route.path.startsWith(path + '/');
};
</script>
