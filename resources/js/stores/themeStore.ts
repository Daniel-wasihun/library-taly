import { defineStore } from 'pinia';
import { ref } from 'vue';

export const useThemeStore = defineStore('theme', () => {
    const saved = typeof window !== 'undefined' ? localStorage.getItem('theme') : null;
    const initialDark = saved ? saved === 'dark' : false;
    const isDark = ref(initialDark);

    if (typeof document !== 'undefined') {
        if (initialDark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }

    const applyThemeSynchronously = (dark: boolean) => {
        if (typeof document === 'undefined') return;

        const style = document.createElement('style');
        style.appendChild(
            document.createTextNode(
                `*, *::before, *::after {
                    -webkit-transition: none !important;
                    -moz-transition: none !important;
                    -o-transition: none !important;
                    -ms-transition: none !important;
                    transition: none !important;
                }`
            )
        );
        document.head.appendChild(style);

        isDark.value = dark;
        if (dark) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
        localStorage.setItem('theme', dark ? 'dark' : 'light');

        void window.getComputedStyle(style).opacity;

        requestAnimationFrame(() => {
            requestAnimationFrame(() => {
                if (document.head.contains(style)) {
                    document.head.removeChild(style);
                }
            });
        });
    };

    const toggleTheme = () => {
        applyThemeSynchronously(!isDark.value);
    };

    return {
        isDark,
        toggleTheme,
    };
});
