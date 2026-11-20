<template>
  <aside
    class="h-full bg-card-bg text-main-text flex flex-col shrink-0 border-r border-card-border transition-all duration-300 select-none z-30"
    :class="isCollapsed ? 'w-18' : 'w-60'"
  >
    <!-- Brand Header matching Senbet / Gibi-Gubae with Senebt Toggle Button -->
    <div
      class="h-16 flex items-center border-b border-card-border shrink-0 transition-all duration-300"
      :class="isCollapsed ? 'justify-center px-2' : 'justify-between px-4'"
    >
      <!-- Brand Logo & Title -->
      <router-link
        to="/dashboard"
        class="flex items-center gap-2.5 min-w-0 cursor-pointer group"
        :title="langStore.t('app.name', 'UniLibrary Portal')"
      >
        <!-- Crest Icon -->
        <div class="w-8 h-8 rounded-xs bg-gradient-to-br from-brand-blue to-blue-700 text-white flex items-center justify-center font-bold text-sm shadow-xs border border-white/20 shrink-0 group-hover:scale-105 transition-transform">
          📚
        </div>
        <div v-if="!isCollapsed" class="flex flex-col min-w-0">
          <span class="font-bold text-xs tracking-tight text-main-text leading-tight truncate">
            {{ langStore.t('app.name', 'UniLibrary Portal') }}
          </span>
          <span class="text-[9px] text-brand-blue dark:text-brand-yellow font-bold tracking-wider uppercase mt-0.5 truncate">
            {{ langStore.t('app.subtitle', 'University Library System') }}
          </span>
        </div>
      </router-link>

      <!-- Senebt System-style Sidebar Toggler (inside header) -->
      <button
        v-if="!isCollapsed"
        type="button"
        @click="emit('toggle')"
        class="hidden lg:flex p-1.5 rounded text-main-text/50 hover:text-brand-blue hover:bg-brand-blue/10 transition cursor-pointer shrink-0"
        :title="langStore.t('nav.collapse_sidebar', 'Collapse Sidebar')"
        aria-label="Toggle Sidebar"
      >
        <PanelLeftClose class="w-4 h-4" />
      </button>
    </div>

    <!-- Navigation Menu with Groups (Only options are visible) -->
    <nav class="flex-1 px-2.5 py-3 space-y-4 overflow-y-auto custom-scrollbar">
      <!-- Group 1: General -->
      <div class="space-y-1">
        <div
          v-if="!isCollapsed"
          class="px-2.5 text-[9.5px] font-bold text-main-text/40 uppercase tracking-wider mb-1"
        >
          {{ langStore.t('nav.group_general', 'General') }}
        </div>
        <div v-else class="h-px bg-card-border/60 mx-1 mb-2"></div>

        <!-- Dashboard -->
        <router-link
          v-if="authStore.canViewDashboard"
          to="/dashboard"
          class="flex items-center rounded-xs transition-all duration-200 cursor-pointer relative group"
          :class="[
            isCollapsed ? 'justify-center p-2.5' : 'gap-2.5 px-2.5 py-2 text-[11.5px]',
            isActive('/dashboard')
              ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
              : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'
          ]"
          :title="langStore.t('nav.dashboard', 'Dashboard')"
        >
          <LayoutDashboard
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/dashboard') ? 'text-nav-accent' : 'text-main-text/50 group-hover:text-nav-accent'"
          />
          <span v-if="!isCollapsed" class="truncate font-semibold">{{ langStore.t('nav.dashboard', 'Dashboard') }}</span>
          <div
            v-if="isActive('/dashboard')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>

        <!-- Library Branches -->
        <router-link
          v-if="authStore.canViewLibraries"
          to="/libraries"
          class="flex items-center rounded-xs transition-all duration-200 cursor-pointer relative group"
          :class="[
            isCollapsed ? 'justify-center p-2.5' : 'gap-2.5 px-2.5 py-2 text-[11.5px]',
            isActive('/libraries')
              ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
              : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'
          ]"
          :title="langStore.t('nav.libraries', 'Branches')"
        >
          <Landmark
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/libraries') ? 'text-nav-accent' : 'text-main-text/50 group-hover:text-nav-accent'"
          />
          <span v-if="!isCollapsed" class="truncate font-semibold">{{ langStore.t('nav.libraries', 'Branches') }}</span>
          <div
            v-if="isActive('/libraries')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>

        <!-- My Turns -->
        <router-link
          to="/my-turns"
          class="flex items-center rounded-xs transition-all duration-200 cursor-pointer relative group"
          :class="[
            isCollapsed ? 'justify-center p-2.5' : 'gap-2.5 px-2.5 py-2 text-[11.5px]',
            isActive('/my-turns')
              ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
              : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'
          ]"
          :title="langStore.t('nav.my_turns', 'My Shifts')"
        >
          <Clock
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/my-turns') ? 'text-nav-accent' : 'text-main-text/50 group-hover:text-nav-accent'"
          />
          <span v-if="!isCollapsed" class="truncate font-semibold">{{ langStore.t('nav.my_turns', 'My Shifts') }}</span>
          <div
            v-if="isActive('/my-turns')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>
      </div>

      <!-- Group 2: Library Operations -->
      <div class="space-y-1">
        <div
          v-if="!isCollapsed"
          class="px-2.5 text-[9.5px] font-bold text-main-text/40 uppercase tracking-wider mb-1"
        >
          {{ langStore.t('nav.group_library', 'Operations') }}
        </div>
        <div v-else class="h-px bg-card-border/60 mx-1 mb-2"></div>

        <!-- Attendance Counter (Semantic & Short) -->
        <router-link
          v-if="authStore.isAdmin || authStore.hasPermission('attendance.record') || authStore.hasPermission('attendance.view') || authStore.hasRole(['Super Admin', 'Admin', 'Manager', 'Librarian', 'Attendant'])"
          to="/attendance"
          class="flex items-center rounded-xs transition-all duration-200 cursor-pointer relative group"
          :class="[
            isCollapsed ? 'justify-center p-2.5' : 'gap-2.5 px-2.5 py-2 text-[11.5px]',
            isActive('/attendance')
              ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
              : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'
          ]"
          :title="langStore.t('nav.attendance', 'Attendance')"
        >
          <Activity
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/attendance') ? 'text-nav-accent' : 'text-main-text/50 group-hover:text-nav-accent'"
          />
          <span v-if="!isCollapsed" class="truncate font-semibold">{{ langStore.t('nav.attendance', 'Attendance') }}</span>
          <div
            v-if="isActive('/attendance')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>
      </div>

      <!-- Group 3: Administration & Security (Strict Permissions) -->
      <div v-if="authStore.isAdmin || authStore.canManageLibrary" class="space-y-1">
        <div
          v-if="!isCollapsed"
          class="px-2.5 text-[9.5px] font-bold text-main-text/40 uppercase tracking-wider mb-1"
        >
          {{ langStore.t('nav.group_admin', 'Administration') }}
        </div>
        <div v-else class="h-px bg-card-border/60 mx-1 mb-2"></div>

        <!-- Users Management -->
        <router-link
          v-if="authStore.isAdmin || authStore.hasPermission('users.view')"
          to="/users"
          class="flex items-center rounded-xs transition-all duration-200 cursor-pointer relative group"
          :class="[
            isCollapsed ? 'justify-center p-2.5' : 'gap-2.5 px-2.5 py-2 text-[11.5px]',
            isActive('/users')
              ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
              : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'
          ]"
          :title="langStore.t('nav.users', 'User Accounts')"
        >
          <Users
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/users') ? 'text-nav-accent' : 'text-main-text/50 group-hover:text-nav-accent'"
          />
          <span v-if="!isCollapsed" class="truncate font-semibold">{{ langStore.t('nav.users', 'User Accounts') }}</span>
          <div
            v-if="isActive('/users')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>

        <!-- Roles & Permissions -->
        <router-link
          v-if="authStore.isAdmin || authStore.hasPermission('roles.view')"
          to="/roles"
          class="flex items-center rounded-xs transition-all duration-200 cursor-pointer relative group"
          :class="[
            isCollapsed ? 'justify-center p-2.5' : 'gap-2.5 px-2.5 py-2 text-[11.5px]',
            isActive('/roles')
              ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
              : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'
          ]"
          :title="langStore.t('nav.roles', 'Roles & Permissions')"
        >
          <ShieldCheck
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/roles') ? 'text-nav-accent' : 'text-main-text/50 group-hover:text-nav-accent'"
          />
          <span v-if="!isCollapsed" class="truncate font-semibold">{{ langStore.t('nav.roles', 'Roles & Permissions') }}</span>
          <div
            v-if="isActive('/roles')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>

        <!-- Duty Shift Schedules -->
        <router-link
          v-if="authStore.isAdmin || authStore.canManageLibrary"
          to="/schedules"
          class="flex items-center rounded-xs transition-all duration-200 cursor-pointer relative group"
          :class="[
            isCollapsed ? 'justify-center p-2.5' : 'gap-2.5 px-2.5 py-2 text-[11.5px]',
            isActive('/schedules')
              ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
              : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'
          ]"
          :title="langStore.t('nav.schedules', 'Shift Schedules')"
        >
          <CalendarDays
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/schedules') ? 'text-nav-accent' : 'text-main-text/50 group-hover:text-nav-accent'"
          />
          <span v-if="!isCollapsed" class="truncate font-semibold">{{ langStore.t('nav.schedules', 'Shift Schedules') }}</span>
          <div
            v-if="isActive('/schedules')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>

        <!-- Academic Years (Academic Calendar CRUD) -->
        <router-link
          v-if="authStore.isAdmin || authStore.canManageLibrary"
          to="/academic-years"
          class="flex items-center rounded-xs transition-all duration-200 cursor-pointer relative group"
          :class="[
            isCollapsed ? 'justify-center p-2.5' : 'gap-2.5 px-2.5 py-2 text-[11.5px]',
            isActive('/academic-years')
              ? 'bg-nav-accent/10 text-nav-accent font-bold shadow-2xs'
              : 'text-main-text/75 hover:bg-nav-accent/5 hover:text-nav-accent'
          ]"
          :title="langStore.t('nav.academic_years', 'Academic Years')"
        >
          <CalendarRange
            class="w-4 h-4 shrink-0 transition-colors"
            :class="isActive('/academic-years') ? 'text-nav-accent' : 'text-main-text/50 group-hover:text-nav-accent'"
          />
          <span v-if="!isCollapsed" class="truncate font-semibold">{{ langStore.t('nav.academic_years', 'Academic Years') }}</span>
          <div
            v-if="isActive('/academic-years')"
            class="absolute left-0 top-1/2 -translate-y-1/2 w-1 h-5 bg-nav-accent rounded-r-full"
          ></div>
        </router-link>
      </div>
    </nav>

    <!-- Bottom Toggler button in collapsed state for easy re-expansion -->
    <div v-if="isCollapsed" class="p-2 border-t border-card-border shrink-0 flex justify-center">
      <button
        type="button"
        @click="emit('toggle')"
        class="p-2 rounded text-main-text/60 hover:text-brand-blue hover:bg-brand-blue/10 transition cursor-pointer"
        :title="langStore.t('nav.expand_sidebar', 'Expand Sidebar')"
        aria-label="Expand Sidebar"
      >
        <PanelLeftOpen class="w-4 h-4" />
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { useLanguageStore } from '@/stores/languageStore';
import {
  LayoutDashboard,
  Clock,
  Activity,
  Users,
  ShieldCheck,
  CalendarDays,
  CalendarRange,
  Landmark,
  PanelLeftClose,
  PanelLeftOpen,
} from 'lucide-vue-next';

withDefaults(
  defineProps<{
    isCollapsed?: boolean;
  }>(),
  {
    isCollapsed: false,
  }
);

const emit = defineEmits<{
  (e: 'toggle'): void;
}>();

const route = useRoute();
const authStore = useAuthStore();
const langStore = useLanguageStore();

const isActive = (path: string) => {
  return route.path === path || route.path.startsWith(path + '/');
};
</script>
