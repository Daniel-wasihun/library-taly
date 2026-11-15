<template>
    <teleport to="body">
        <div class="fixed top-4 right-4 z-50 flex flex-col gap-2 w-80 max-w-full pointer-events-none">
            <transition-group name="slide-up" tag="div" class="flex flex-col gap-2">
                <div
                    v-for="toast in toastStore.toasts"
                    :key="toast.id"
                    class="pointer-events-auto flex items-start gap-3 px-4 py-3 rounded-lg shadow-lg text-sm"
                    :style="getToastStyle(toast.type)">
                    <component :is="getIcon(toast.type)" :size="16" class="mt-0.5 shrink-0" />
                    <span class="flex-1 leading-snug">{{ toast.message }}</span>
                    <button @click="toastStore.remove(toast.id)" class="text-current opacity-60 hover:opacity-100 shrink-0">
                        <X :size="14" />
                    </button>
                </div>
            </transition-group>
        </div>
    </teleport>
</template>

<script setup lang="ts">
import { useToastStore } from '@/stores/toastStore';
import { CheckCircle, XCircle, AlertTriangle, Info, X } from '@lucide/vue';
import type { ToastType } from '@/types';

const toastStore = useToastStore();

function getIcon(type: ToastType) {
    return { success: CheckCircle, error: XCircle, warning: AlertTriangle, info: Info }[type];
}

function getToastStyle(type: ToastType) {
    const styles: Record<ToastType, string> = {
        success: 'background: #065f46; color: #d1fae5;',
        error: 'background: #7f1d1d; color: #fee2e2;',
        warning: 'background: #78350f; color: #fef3c7;',
        info: 'background: #1e3a8a; color: #dbeafe;',
    };
    return styles[type];
}
</script>
