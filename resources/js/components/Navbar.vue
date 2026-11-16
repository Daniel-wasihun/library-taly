<template>
  <header class="h-16 bg-card-bg/95 backdrop-blur-md border-b border-card-border px-3 sm:px-6 flex items-center justify-between shrink-0 shadow-2xs z-20 sticky top-0 transition-colors">
    <!-- Left: Mobile Menu Toggle + Context Title & Active Subtitle -->
    <div class="flex items-center gap-2 sm:gap-3">
      <!-- Mobile Drawer Trigger Button -->
      <button
        type="button"
        @click="emit('toggleMobileMenu')"
        class="lg:hidden p-2 -ml-1 rounded-xl text-main-text/70 hover:text-main-text hover:bg-card-hover/40 transition active:scale-95 cursor-pointer"
        aria-label="Toggle navigation menu"
      >
        <Menu class="w-5 h-5" />
      </button>

      <h1 class="text-xs sm:text-sm font-bold text-main-text tracking-tight truncate max-w-[150px] sm:max-w-none">
        {{ title }}
      </h1>
      <span class="hidden md:inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-brand-blue/10 text-brand-blue dark:bg-brand-yellow/15 dark:text-brand-yellow border border-brand-blue/20 dark:border-brand-yellow/30">
        <span class="w-1.5 h-1.5 rounded-full bg-brand-blue dark:bg-brand-yellow animate-pulse"></span>
        UniLibrary Portal
      </span>
    </div>

    <!-- Right: Theme Toggler + Language Switcher + User Profile Menu with Sign Out -->
    <div class="flex items-center gap-1.5 sm:gap-3">
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
import { Menu } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';
import LanguageSwitcher from './LanguageSwitcher.vue';
import ThemeToggle from './ThemeToggle.vue';
import HeaderProfileDropdown from './HeaderProfileDropdown.vue';

const emit = defineEmits<{
  (e: 'toggleMobileMenu'): void;
}>();

const route = useRoute();
const langStore = useLanguageStore();

const title = computed(() => {
  const path = route.path;
  if (path.startsWith('/attendance')) return langStore.t('nav.attendance', 'Physical Attendance');
  if (path.startsWith('/users')) return langStore.t('nav.users', 'User Accounts & Access');
  if (path.startsWith('/roles')) return langStore.t('nav.roles', 'Roles & RBAC Matrix');
  if (path.startsWith('/schedules')) return langStore.t('nav.schedules', 'Duty Shift Schedules');
  if (path.startsWith('/my-turns')) return langStore.t('nav.my_turns', 'My Duty Turns');
  if (path.startsWith('/libraries')) return langStore.t('nav.libraries', 'Campus Library Branches');
  return langStore.t('nav.dashboard', 'Overview Dashboard');
});
</script>
