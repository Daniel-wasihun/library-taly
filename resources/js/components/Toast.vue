<template>
  <div class="fixed top-2.5 right-2.5 left-2.5 sm:left-auto sm:top-5 sm:right-5 z-50 flex flex-col space-y-1.5 pointer-events-none sm:max-w-sm w-auto sm:w-full">
    <transition-group
      enter-active-class="transform ease-out duration-250 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-center justify-between px-2.5 py-1.5 sm:px-4 sm:py-3 rounded-xs sm:rounded-sm shadow-md border text-[11px] sm:text-sm font-medium transition-all"
        :class="getToastClasses(toast.type)"
      >
        <div class="flex items-center space-x-2 sm:space-x-3 truncate">
          <span class="text-xs sm:text-base font-bold shrink-0">{{ getToastIcon(toast.type) }}</span>
          <p class="truncate leading-tight">{{ toast.message }}</p>
        </div>
        <button
          @click="toastStore.remove(toast.id)"
          class="ml-2 sm:ml-4 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer shrink-0 text-xs sm:text-sm p-0.5"
          aria-label="Close notification"
        >
          ✕
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup lang="ts">
import { useToastStore, ToastItem } from '@/stores/toastStore';

const toastStore = useToastStore();

const getToastClasses = (type: ToastItem['type']) => {
  switch (type) {
    case 'success':
      return 'bg-emerald-50 dark:bg-emerald-950/90 border-emerald-300 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200';
    case 'error':
      return 'bg-rose-50 dark:bg-rose-950/90 border-rose-300 dark:border-rose-800 text-rose-800 dark:text-rose-200';
    case 'warning':
      return 'bg-amber-50 dark:bg-amber-950/90 border-amber-300 dark:border-amber-800 text-amber-800 dark:text-amber-200';
    default:
      return 'bg-slate-50 dark:bg-slate-900 border-slate-300 dark:border-slate-800 text-slate-800 dark:text-slate-200';
  }
};

const getToastIcon = (type: ToastItem['type']) => {
  switch (type) {
    case 'success': return '✓';
    case 'error': return '⚠';
    case 'warning': return '⚡';
    default: return 'ℹ';
  }
};
</script>
