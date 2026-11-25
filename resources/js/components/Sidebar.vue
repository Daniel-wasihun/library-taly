<template>
  <aside
    class="w-full h-full bg-card-bg text-main-text flex flex-col shrink-0 border-r border-card-border select-none z-30"
  >
    <!-- Brand Header matching Senbet / Gibi-Gubae standard -->
    <div
      class="h-14 flex items-center border-b border-card-border shrink-0 transition-all duration-300"
      :class="isCollapsed ? 'justify-center px-2' : 'justify-between px-3.5'"
    >
      <!-- Brand Logo & Title -->
      <router-link
        :to="authStore.canViewDashboard ? '/dashboard' : (authStore.canViewAttendance ? '/attendance' : (authStore.canViewLibraries ? '/libraries' : '/my-turns'))"
        class="flex items-center gap-2.5 min-w-0 cursor-pointer group"
        :title="langStore.isAmharic() ? 'ወልድያ ዩኒቨርሲቲ ቤተ-መጻሕፍት' : 'Woldia University Library'"
        @click="isMobile && emit('closeMobile')"
      >
        <!-- Clean WDU Logo -->
        <WduLogo :size="28" class="group-hover:scale-105 transition-transform shrink-0" />
        <div v-if="!isCollapsed" class="flex flex-col min-w-0">
          <span class="font-bold text-xs tracking-tight text-main-text leading-tight truncate">
            {{ langStore.isAmharic() ? 'ወልድያ ዩኒቨርሲቲ' : 'Woldia University' }}
          </span>
          <span class="text-[9px] text-brand-blue dark:text-brand-yellow font-bold tracking-wider uppercase mt-0.5 truncate">
            {{ langStore.isAmharic() ? 'ቤተ-መጻሕፍት ፖርታል' : 'Library Portal' }}
          </span>
        </div>
      </router-link>

      <!-- Mobile Close (X) Button -->
      <button
        v-if="isMobile"
        @click="emit('closeMobile')"
        type="button"
        class="p-1 rounded-md text-main-text/50 hover:text-main-text hover:bg-main-bg transition cursor-pointer"
        aria-label="Close menu"
      >
        <X class="w-4 h-4" />
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
          @click="isMobile && emit('closeMobile')"
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
          @click="isMobile && emit('closeMobile')"
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

        <!-- Academic Years (Directly next to Library Branches) -->
        <router-link
          v-if="authStore.canViewAcademicYears"
          to="/academic-years"
          @click="isMobile && emit('closeMobile')"
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

        <!-- My Turns -->
        <router-link
          to="/my-turns"
          @click="isMobile && emit('closeMobile')"
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
      <div v-if="authStore.canViewAttendance" class="space-y-1">
        <div
          v-if="!isCollapsed"
          class="px-2.5 text-[9.5px] font-bold text-main-text/40 uppercase tracking-wider mb-1"
        >
          {{ langStore.t('nav.group_library', 'Operations') }}
        </div>
        <div v-else class="h-px bg-card-border/60 mx-1 mb-2"></div>

        <!-- Attendance Counter (Semantic & Short) -->
        <router-link
          to="/attendance"
          @click="isMobile && emit('closeMobile')"
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
      <div v-if="authStore.canViewUsers || authStore.canViewRoles || authStore.canViewSchedules" class="space-y-1">
        <div
          v-if="!isCollapsed"
          class="px-2.5 text-[9.5px] font-bold text-main-text/40 uppercase tracking-wider mb-1"
        >
          {{ langStore.t('nav.group_admin', 'Administration') }}
        </div>
        <div v-else class="h-px bg-card-border/60 mx-1 mb-2"></div>

        <!-- Users Management -->
        <router-link
          v-if="authStore.canViewUsers"
          to="/users"
          @click="isMobile && emit('closeMobile')"
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
          v-if="authStore.canViewRoles"
          to="/roles"
          @click="isMobile && emit('closeMobile')"
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
          v-if="authStore.canViewSchedules"
          to="/schedules"
          @click="isMobile && emit('closeMobile')"
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
      </div>
    </nav>

    <!-- Mobile Drawer Footer with Current User Profile -->
    <div v-if="isMobile" class="p-3 border-t border-card-border/80 shrink-0 bg-main-bg/50">
      <div class="flex items-center gap-2.5 min-w-0">
        <div class="w-7 h-7 rounded-full bg-brand-blue/15 text-brand-blue font-bold flex items-center justify-center text-xs shrink-0 font-mono shadow-2xs border border-brand-blue/20">
          {{ authStore.user?.name?.charAt(0).toUpperCase() || 'U' }}
        </div>
        <div class="min-w-0 flex-1">
          <p class="text-xs font-bold text-main-text truncate leading-tight">{{ authStore.user?.name || 'Staff' }}</p>
          <p class="text-[10px] text-main-text/50 truncate font-mono">@{{ authStore.user?.username || 'user' }}</p>
        </div>
      </div>
    </div>

    <!-- Bottom Toggler button in collapsed state for easy re-expansion (Desktop only) -->
    <div v-if="isCollapsed && !isMobile" class="p-2 border-t border-card-border shrink-0 flex justify-center">
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
import WduLogo from '@/components/WduLogo.vue';
import {
  LayoutDashboard,
  Clock,
  Activity,
  Users,
  ShieldCheck,
  CalendarDays,
  CalendarRange,
  Landmark,
  PanelLeftOpen,
  X,
} from 'lucide-vue-next';

withDefaults(
  defineProps<{
    isCollapsed?: boolean;
    isMobile?: boolean;
  }>(),
  {
    isCollapsed: false,
    isMobile: false,
  }
);

const emit = defineEmits<{
  (e: 'toggle'): void;
  (e: 'closeMobile'): void;
}>();

const route = useRoute();
const authStore = useAuthStore();
const langStore = useLanguageStore();

const isActive = (path: string) => {
  return route.path === path || route.path.startsWith(path + '/');
};
</script>
