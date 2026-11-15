<template>
  <div class="fixed top-5 right-5 z-50 flex flex-col space-y-2 pointer-events-none max-w-sm w-full">
    <transition-group
      enter-active-class="transform ease-out duration-300 transition"
      enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
      enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
      leave-active-class="transition ease-in duration-100"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-for="toast in toastStore.toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-center justify-between p-4 rounded-xl shadow-lg border text-sm font-medium"
        :class="getToastClasses(toast.type)"
      >
        <div class="flex items-center space-x-3">
          <span class="text-base font-bold">{{ getToastIcon(toast.type) }}</span>
          <p>{{ toast.message }}</p>
        </div>
        <button
          @click="toastStore.remove(toast.id)"
          class="ml-4 text-slate-400 hover:text-slate-600 focus:outline-none cursor-pointer"
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
      return 'bg-emerald-50 dark:bg-emerald-950/80 border-emerald-200 dark:border-emerald-800 text-emerald-800 dark:text-emerald-200';
    case 'error':
      return 'bg-rose-50 dark:bg-rose-950/80 border-rose-200 dark:border-rose-800 text-rose-800 dark:text-rose-200';
    case 'warning':
      return 'bg-amber-50 dark:bg-amber-950/80 border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-200';
    default:
      return 'bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200';
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
