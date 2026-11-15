import { defineStore } from 'pinia';
import { ref } from 'vue';

export interface ToastItem {
    id: string;
    type: 'success' | 'error' | 'warning' | 'info';
    message: string;
    duration?: number;
}

export const useToastStore = defineStore('toast', () => {
    const toasts = ref<ToastItem[]>([]);

    const add = (message: string, type: ToastItem['type'] = 'info', duration = 4000) => {
        const id = Math.random().toString(36).substring(2, 9);
        toasts.value.push({ id, type, message, duration });

        setTimeout(() => {
            remove(id);
        }, duration);
    };

    const success = (message: string, duration?: number) => add(message, 'success', duration);
    const error = (message: string, duration?: number) => add(message, 'error', duration);
    const warning = (message: string, duration?: number) => add(message, 'warning', duration);
    const info = (message: string, duration?: number) => add(message, 'info', duration);

    const remove = (id: string) => {
        toasts.value = toasts.value.filter((t) => t.id !== id);
    };

    return {
        toasts,
        add,
        success,
        error,
        warning,
        info,
        remove,
    };
});
