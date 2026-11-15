<template>
    <!-- Modal backdrop -->
    <teleport to="body">
        <transition name="fade">
            <div v-if="modelValue"
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
                @click.self="$emit('update:modelValue', false)">
                <div class="w-full rounded-xl shadow-2xl overflow-hidden"
                    :class="sizeClass"
                    style="background: var(--bg-card);"
                    @click.stop>
                    <!-- Header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color: var(--border);">
                        <h2 class="font-semibold text-sm" style="color: var(--text-primary);">{{ title }}</h2>
                        <button
                            @click="$emit('update:modelValue', false)"
                            class="text-slate-400 hover:text-slate-600 rounded-md p-1 hover:bg-slate-100 transition-colors">
                            <X :size="16" />
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="px-6 py-4 overflow-y-auto max-h-[70vh]">
                        <slot />
                    </div>

                    <!-- Footer (optional) -->
                    <div v-if="$slots.footer" class="flex items-center justify-end gap-2 px-6 py-4 border-t" style="border-color: var(--border);">
                        <slot name="footer" />
                    </div>
                </div>
            </div>
        </transition>
    </teleport>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { X } from '@lucide/vue';

interface Props {
    modelValue: boolean;
    title?: string;
    size?: 'sm' | 'md' | 'lg' | 'xl';
}

const props = withDefaults(defineProps<Props>(), {
    title: '',
    size: 'md',
});

defineEmits<{ 'update:modelValue': [value: boolean] }>();

const sizeClass = computed(() => ({
    sm: 'max-w-sm',
    md: 'max-w-lg',
    lg: 'max-w-2xl',
    xl: 'max-w-4xl',
}[props.size]));
</script>
