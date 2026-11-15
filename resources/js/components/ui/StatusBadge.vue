<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  status: string;
}

const props = defineProps<Props>();

const config = computed(() => {
  const s = (props.status || '').toLowerCase();
  switch (s) {
    case 'active':
    case 'scheduled':
      return {
        label: props.status,
        classes: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border-emerald-500/25',
        dot: 'bg-emerald-500',
      };
    case 'borrowed':
      return {
        label: props.status,
        classes: 'bg-brand-blue/10 text-brand-blue dark:text-blue-400 border-brand-blue/25',
        dot: 'bg-brand-blue',
      };
    case 'returned':
    case 'completed':
      return {
        label: props.status,
        classes: 'bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-500/25',
        dot: 'bg-slate-400',
      };
    case 'overdue':
    case 'cancelled':
    case 'suspended':
      return {
        label: props.status,
        classes: 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/25',
        dot: 'bg-rose-500',
      };
    case 'inactive':
    default:
      return {
        label: props.status,
        classes: 'bg-amber-500/10 text-amber-700 dark:text-brand-yellow border-amber-500/25',
        dot: 'bg-amber-500',
      };
  }
});
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-md text-[11px] font-semibold border select-none shadow-2xs capitalize tracking-tight"
    :class="config.classes"
  >
    <span class="w-1.5 h-1.5 rounded-full" :class="config.dot"></span>
    <span>{{ config.label }}</span>
  </span>
</template>
