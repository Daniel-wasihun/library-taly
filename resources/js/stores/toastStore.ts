import { defineStore } from 'pinia';
import { ref } from 'vue';
import type { Toast, ToastType } from '@/types';

export const useToastStore = defineStore('toast', () => {
    const toasts = ref<Toast[]>([]);

    function add(type: ToastType, message: string, duration = 4000) {
        const id = `toast-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        toasts.value.push({ id, type, message });
        setTimeout(() => remove(id), duration);
    }

    function remove(id: string) {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    }

    const success = (msg: string) => add('success', msg);
    const error = (msg: string) => add('error', msg);
    const warning = (msg: string) => add('warning', msg);
    const info = (msg: string) => add('info', msg);

    return { toasts, add, remove, success, error, warning, info };
});
