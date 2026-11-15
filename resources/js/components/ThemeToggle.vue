<template>
  <button
    type="button"
    @click="themeStore.toggleTheme"
    class="relative p-2 rounded-lg text-nav-accent hover:bg-nav-accent/10 border border-card-border/60 hover:border-nav-accent/30 cursor-pointer overflow-hidden shadow-2xs group active:scale-95 select-none"
    :title="themeStore.isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
    aria-label="Toggle Theme"
  >
    <div class="relative w-5 h-5 flex items-center justify-center">
      <Sun v-if="themeStore.isDark" key="sun" class="w-5 h-5 text-nav-accent stroke-[2.25]" />
      <Moon v-else key="moon" class="w-5 h-5 text-nav-accent stroke-[2.25]" />
    </div>
  </button>
</template>

<script setup lang="ts">
import { Moon, Sun } from 'lucide-vue-next';
import { useThemeStore } from '@/stores/themeStore';

const themeStore = useThemeStore();
</script>
