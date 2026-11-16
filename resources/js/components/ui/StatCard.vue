<script setup lang="ts">
/**
 * StatCard — Metric card for dashboards matching Senbet / Gibi-Gubae standards.
 */
import { computed } from 'vue';
import type { Component } from 'vue';

interface Props {
  label:       string;
  value:       string | number;
  icon?:       Component;
  variant?:    'blue' | 'yellow' | 'green' | 'red' | 'purple';
  trend?:      number;
  loading?:    boolean;
}

const props = withDefaults(defineProps<Props>(), {
  variant: 'blue',
  loading: false,
});

const VARIANTS = {
  blue:   { bg: 'bg-brand-blue/10 dark:bg-brand-blue/20 text-brand-blue dark:text-blue-400 border-brand-blue/20 dark:border-brand-blue/30' },
  yellow: { bg: 'bg-brand-yellow/15 dark:bg-brand-yellow/20 text-amber-700 dark:text-brand-yellow border-amber-500/20 dark:border-brand-yellow/30' },
  green:  { bg: 'bg-brand-green/10 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border-emerald-500/20 dark:border-emerald-800' },
  red:    { bg: 'bg-rose-500/10 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border-rose-500/20 dark:border-rose-800' },
  purple: { bg: 'bg-purple-500/10 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 border-purple-500/20 dark:border-purple-800' },
} as const;

const v = computed(() => VARIANTS[props.variant]);
</script>

<template>
  <div class="flex items-center gap-3.5 bg-card-bg border border-card-border/80 rounded-sm p-4 shadow-2xs transition-colors hover:border-card-border">
    <!-- Icon badge -->
    <div v-if="icon" class="w-10 h-10 rounded-xs flex items-center justify-center shrink-0 border" :class="v.bg">
      <component :is="icon" class="w-5 h-5 shrink-0 stroke-[2.2]" />
    </div>

    <!-- Value + label -->
    <div class="flex-1 min-w-0">
      <div v-if="loading" class="space-y-2">
        <div class="h-6 w-16 bg-slate-200/80 dark:bg-slate-700/60 rounded animate-pulse" />
        <div class="h-3 w-24 bg-slate-100 dark:bg-slate-800 rounded animate-pulse" />
      </div>

      <template v-else>
        <div class="flex items-baseline gap-2">
          <span class="text-2xl font-bold text-main-text leading-none tracking-tight">{{ value ?? 0 }}</span>
        </div>
        <p class="text-xs text-main-text/60 font-medium truncate mt-1">{{ label }}</p>
      </template>
    </div>
  </div>
</template>
