<script setup lang="ts">
/**
 * Modal — Teleported dialog matching Gibi-Gubae UI standards.
 */
import { watch, onUnmounted } from 'vue';
import { X } from 'lucide-vue-next';
import type { Component } from 'vue';

interface Props {
  show: boolean;
  title?: string;
  description?: string;
  icon?: Component;
  size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
}

const props = withDefaults(defineProps<Props>(), {
  title: '',
  description: '',
  size: 'md',
});

const emit = defineEmits<{ (e: 'close'): void }>();

const SIZES: Record<NonNullable<Props['size']>, string> = {
  sm: 'max-w-lg',
  md: 'max-w-3xl',
  lg: 'max-w-5xl',
  xl: 'max-w-7xl',
  '2xl': 'max-w-[1600px] w-full',
};

watch(
  () => props.show,
  (open) => {
    if (typeof document !== 'undefined') {
      document.body.style.overflow = open ? 'hidden' : '';
    }
  },
  { immediate: true }
);

onUnmounted(() => {
  if (typeof document !== 'undefined') {
    document.body.style.overflow = '';
  }
});
</script>

<template>
  <teleport to="body">
    <Transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0 scale-95"
      enter-to-class="opacity-100 scale-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100 scale-100"
      leave-to-class="opacity-0 scale-95"
    >
      <div
        v-if="show"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs overflow-y-auto"
        @click.self="emit('close')"
      >
        <div
          class="w-full bg-card-bg border border-card-border rounded-sm sm:rounded shadow-2xl overflow-hidden my-6 sm:my-8 animate-in fade-in"
          :class="SIZES[size]"
          @click.stop
        >
          <!-- Modal Header -->
          <div class="px-6 py-4 border-b border-card-border flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
              <div v-if="icon" class="w-8 h-8 rounded-xs bg-nav-accent/10 text-nav-accent border border-nav-accent/25 flex items-center justify-center shrink-0">
                <component :is="icon" class="w-4 h-4 stroke-[2.2]" />
              </div>
              <div class="min-w-0">
                <h3 class="font-bold text-sm text-main-text truncate leading-tight">{{ title }}</h3>
                <p v-if="description" class="text-xs text-main-text/50 truncate mt-0.5">{{ description }}</p>
              </div>
            </div>
            <button
              type="button"
              @click="emit('close')"
              class="w-8 h-8 rounded-xs flex items-center justify-center text-main-text/40 hover:text-main-text hover:bg-main-text/5 transition cursor-pointer"
            >
              <X class="w-4 h-4 stroke-[2.2]" />
            </button>
          </div>

          <!-- Modal Body -->
          <div class="p-6 max-h-[75vh] overflow-y-auto">
            <slot />
          </div>

          <!-- Modal Footer (optional) -->
          <div v-if="$slots.footer" class="px-6 py-3.5 bg-card-hover/40 border-t border-card-border flex items-center justify-end gap-2.5">
            <slot name="footer" />
          </div>
        </div>
      </div>
    </Transition>
  </teleport>
</template>
