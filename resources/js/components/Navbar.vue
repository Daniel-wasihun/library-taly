<template>
  <header class="h-16 bg-card-bg/95 backdrop-blur-md border-b border-card-border px-3 sm:px-6 flex items-center justify-between shrink-0 shadow-2xs z-20 sticky top-0 transition-colors">
    <!-- Left: Sidebar Toggler + Mobile Menu Toggle + Context Title & Active Subtitle -->
    <div class="flex items-center gap-2 sm:gap-3">
      <!-- Mobile Drawer Trigger Button -->
      <button
        type="button"
        @click="emit('toggleMobileMenu')"
        class="lg:hidden p-2 -ml-1 rounded-xs text-main-text/70 hover:text-main-text hover:bg-card-hover/40 transition active:scale-95 cursor-pointer"
        aria-label="Toggle mobile menu"
      >
        <Menu class="w-5 h-5" />
      </button>

      <!-- Desktop Sidebar Collapse/Expand Toggler (Senebt System Style) -->
      <button
        type="button"
        @click="emit('toggleSidebar')"
        class="hidden lg:flex p-1.5 -ml-1 rounded text-main-text/60 hover:text-brand-blue hover:bg-brand-blue/10 transition active:scale-95 cursor-pointer"
        :title="isCollapsed ? langStore.t('nav.expand_sidebar', 'Expand Sidebar') : langStore.t('nav.collapse_sidebar', 'Collapse Sidebar')"
        aria-label="Toggle Sidebar"
      >
        <PanelLeftOpen v-if="isCollapsed" class="w-5 h-5 text-brand-blue dark:text-brand-yellow" />
        <PanelLeftClose v-else class="w-5 h-5" />
      </button>

      <h1 class="text-xs sm:text-sm font-bold text-main-text tracking-tight truncate max-w-[150px] sm:max-w-none">
        {{ title }}
      </h1>
      <span class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-xs text-[10px] font-semibold bg-brand-blue/10 text-brand-blue dark:bg-brand-yellow/15 dark:text-brand-yellow border border-brand-blue/20 dark:border-brand-yellow/30">
        <span class="w-1.5 h-1.5 rounded-full bg-brand-blue dark:bg-brand-yellow animate-pulse"></span>
        {{ langStore.isAmharic() ? 'ወልድያ ዩኒቨርሲቲ ቤተ-መጻሕፍት' : 'Woldia University Library' }}
      </span>
    </div>

    <!-- Center / Right: Live Clock + Theme Toggler + Language Switcher + User Profile Menu -->
    <div class="flex items-center gap-1.5 sm:gap-2.5">
      <!-- Standardized Synchronized Ethiopian & Gregorian Date/Time Clock -->
      <UnifiedDateTimeClock />

      <!-- Install App Button (Visible only when in browser, not standalone) -->
      <button
        v-if="!isStandalone"
        type="button"
        @click="triggerPwaInstall"
        class="inline-flex items-center gap-1 sm:gap-1.5 px-2 sm:px-2.5 py-1 rounded-sm text-[11px] font-bold bg-brand-blue/10 hover:bg-brand-blue/20 text-brand-blue dark:bg-brand-blue/20 dark:hover:bg-brand-blue/30 dark:text-blue-200 border border-brand-blue/20 transition active:scale-95 cursor-pointer"
        :title="langStore.isAmharic() ? 'የቤተ-መጻሕፍት አፑን ይጫኑ' : 'Install PWA App'"
      >
        <Download class="w-3.5 h-3.5 shrink-0" />
        <span class="hidden sm:inline">{{ langStore.isAmharic() ? 'አፑን ጫን' : 'Install App' }}</span>
      </button>

      <!-- Dark/Light Mode Toggler -->
      <ThemeToggle />

      <!-- Language Selector Dropdown -->
      <LanguageSwitcher />

      <div class="h-5 w-px bg-card-border mx-0.5"></div>

      <!-- User Profile Dropdown with internal Sign Out -->
      <HeaderProfileDropdown />
    </div>
  </header>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import { Menu, PanelLeftClose, PanelLeftOpen, Download } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';
import LanguageSwitcher from './LanguageSwitcher.vue';
import ThemeToggle from './ThemeToggle.vue';
import HeaderProfileDropdown from './HeaderProfileDropdown.vue';
import UnifiedDateTimeClock from './UnifiedDateTimeClock.vue';

withDefaults(
  defineProps<{
    isCollapsed?: boolean;
  }>(),
  {
    isCollapsed: false,
  }
);

const emit = defineEmits<{
  (e: 'toggleMobileMenu'): void;
  (e: 'toggleSidebar'): void;
}>();

const route = useRoute();
const langStore = useLanguageStore();

const isStandalone = computed(() => {
  if (typeof window === 'undefined') return false;
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    (window.navigator as any).standalone === true ||
    document.referrer.includes('android-app://')
  );
});

const triggerPwaInstall = () => {
  if ((window as any).__wduTriggerPwaInstall) {
    (window as any).__wduTriggerPwaInstall();
  }
};

const title = computed(() => {
  const path = route.path;
  if (path.startsWith('/attendance')) return langStore.t('nav.attendance', 'Attendance');
  if (path.startsWith('/academic-years')) return langStore.t('nav.academic_years', 'Academic Years');
  if (path.startsWith('/users')) return langStore.t('nav.users', 'User Accounts');
  if (path.startsWith('/roles')) return langStore.t('nav.roles', 'Roles & Permissions');
  if (path.startsWith('/schedules')) return langStore.t('nav.schedules', 'Shift Schedules');
  if (path.startsWith('/my-turns')) return langStore.t('nav.my_turns', 'My Duty Turns');
  if (path.startsWith('/libraries')) return langStore.t('nav.libraries', 'Campus Library Branches');
  return langStore.t('nav.dashboard', 'Dashboard');
});
</script>
