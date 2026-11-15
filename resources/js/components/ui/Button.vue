<script setup lang="ts">
/**
 * Button — Core interactive button component matching Gibi-Gubae / Senbet System styling.
 */
import { computed, useSlots } from 'vue';
import type { Component } from 'vue';

interface Props {
  variant?:
    | 'primary' | 'secondary' | 'danger' | 'warning' | 'ghost'
    | 'success' | 'info' | 'accent' | 'outline'
    | 'soft-primary' | 'soft-secondary' | 'soft-success'
    | 'soft-warning' | 'soft-danger' | 'soft-blue';
  size?:      'sm' | 'md' | 'lg';
  type?:      'button' | 'submit' | 'reset';
  loading?:   boolean;
  disabled?:  boolean;
  block?:     boolean;
  icon?:      Component;
  ariaLabel?: string;
  title?:     string;
  label?:     string;
}

const props = withDefaults(defineProps<Props>(), {
  variant:   'primary',
  size:      'md',
  type:      'button',
  loading:   false,
  disabled:  false,
  block:     false,
  ariaLabel: '',
  title:     '',
  label:     '',
});

const slots = useSlots();

const isIconOnly = computed(() => {
  return !props.label && !slots.default && (!!props.icon || !!props.ariaLabel || !!props.title);
});

const SIZES: Record<NonNullable<Props['size']>, string> = {
  sm: 'h-8 px-3 text-xs font-medium rounded-lg gap-1.5',
  md: 'h-9 px-3.5 text-xs font-semibold rounded-lg gap-2',
  lg: 'h-10 px-4 text-sm font-semibold rounded-lg gap-2',
};

const ICON_ONLY_SIZES: Record<NonNullable<Props['size']>, string> = {
  sm: 'h-8 w-8 p-0 rounded-lg',
  md: 'h-9 w-9 p-0 rounded-lg',
  lg: 'h-10 w-10 p-0 rounded-lg',
};

const VARIANTS: Record<NonNullable<Props['variant']>, string> = {
  primary:        'bg-brand-blue text-white border border-transparent hover:bg-brand-blue-dark shadow-2xs',
  secondary:      'bg-card-bg text-main-text border border-card-border/80 hover:bg-main-text/5 hover:border-card-border shadow-2xs',
  danger:         'bg-rose-600 text-white border border-transparent hover:bg-rose-700 shadow-2xs',
  warning:        'bg-brand-yellow text-slate-900 border border-transparent hover:bg-amber-500 font-bold shadow-2xs',
  ghost:          'bg-transparent text-main-text/70 border border-transparent hover:bg-main-text/5 hover:text-main-text',
  success:        'bg-emerald-600 text-white border border-transparent hover:bg-emerald-700 shadow-2xs',
  info:           'bg-cyan-600 text-white border border-transparent hover:bg-cyan-700 shadow-2xs',
  accent:         'bg-accent text-white border border-transparent hover:bg-accent-hover shadow-2xs',
  outline:        'bg-card-bg text-main-text border border-card-border hover:bg-main-text/5 shadow-2xs',
  'soft-primary': 'bg-nav-accent/10 text-nav-accent border border-nav-accent/20 hover:bg-nav-accent hover:text-white dark:hover:text-slate-900 shadow-2xs',
  'soft-secondary': 'bg-main-text/5 text-main-text/70 border border-card-border/60 hover:bg-main-text/10 hover:text-main-text',
  'soft-success': 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/20 hover:bg-emerald-600 hover:text-white shadow-2xs',
  'soft-warning': 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-500 hover:text-white shadow-2xs',
  'soft-danger':  'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/20 hover:bg-rose-600 hover:text-white shadow-2xs',
  'soft-blue':    'bg-brand-blue/10 dark:bg-brand-yellow/15 text-brand-blue dark:text-brand-yellow border border-brand-blue/20 dark:border-brand-yellow/30 hover:bg-brand-blue hover:text-white shadow-2xs',
};

const classes = computed(() => [
  'inline-flex flex-row items-center justify-center transition-all duration-200 outline-none select-none cursor-pointer active:scale-98 shrink-0',
  isIconOnly.value ? ICON_ONLY_SIZES[props.size] : SIZES[props.size],
  VARIANTS[props.variant],
  props.block ? 'w-full' : '',
  props.disabled || props.loading
    ? 'opacity-50 cursor-not-allowed pointer-events-none'
    : '',
]);
</script>

<template>
  <button
    :type="type"
    :disabled="disabled || loading"
    :class="classes"
    :aria-label="ariaLabel || undefined"
    :title="title || undefined"
  >
    <svg v-if="loading" :class="[size === 'sm' ? 'w-4 h-4' : 'w-5 h-5', 'animate-spin shrink-0']" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
    </svg>
    <component v-else-if="icon" :is="icon" :class="[size === 'sm' ? 'w-4 h-4' : 'w-5 h-5', 'shrink-0 stroke-[2.25]']" />
    <slot>{{ label }}</slot>
  </button>
</template>
