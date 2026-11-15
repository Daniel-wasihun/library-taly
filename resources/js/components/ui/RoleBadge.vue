<script setup lang="ts">
import { computed } from 'vue';

interface RoleObject {
  id?: number | string;
  name?: string;
  title?: string;
}

interface Props {
  role?: RoleObject | string | null;
  label?: string;
}

const props = withDefaults(defineProps<Props>(), {
  role: null,
  label: '',
});

const roleName = computed(() => {
  if (props.label) return props.label;
  if (typeof props.role === 'object' && props.role?.name) return props.role.name;
  if (typeof props.role === 'string') return props.role;
  return 'Role';
});

const badgeClasses = computed(() => {
  const name = roleName.value.toLowerCase();
  if (name.includes('super admin') || name.includes('admin')) {
    return 'bg-brand-blue/10 dark:bg-brand-blue/20 text-brand-blue dark:text-blue-300 border-brand-blue/25';
  }
  if (name.includes('head librarian') || name.includes('director')) {
    return 'bg-purple-500/10 dark:bg-purple-500/20 text-purple-700 dark:text-purple-300 border-purple-500/25';
  }
  if (name.includes('desk librarian') || name.includes('circulation')) {
    return 'bg-emerald-500/10 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border-emerald-500/25';
  }
  if (name.includes('catalog')) {
    return 'bg-indigo-500/10 dark:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border-indigo-500/25';
  }
  if (name.includes('archive')) {
    return 'bg-teal-500/10 dark:bg-teal-500/20 text-teal-700 dark:text-teal-300 border-teal-500/25';
  }
  if (name.includes('faculty') || name.includes('research')) {
    return 'bg-sky-500/10 dark:bg-sky-500/20 text-sky-700 dark:text-sky-300 border-sky-500/25';
  }
  if (name.includes('student') || name.includes('member')) {
    return 'bg-amber-500/10 dark:bg-amber-500/20 text-amber-700 dark:text-brand-yellow border-amber-500/25';
  }
  return 'bg-slate-500/10 text-slate-700 dark:text-slate-300 border-slate-500/25';
});
</script>

<template>
  <span
    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-md text-[11px] font-semibold border select-none shadow-2xs tracking-tight"
    :class="badgeClasses"
  >
    <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
    <span>{{ roleName }}</span>
  </span>
</template>
