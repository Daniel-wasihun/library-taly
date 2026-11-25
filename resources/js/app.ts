import { createApp } from 'vue';
import { createPinia } from 'pinia';
import { createPersistedState } from 'pinia-plugin-persistedstate';
import router from '@/router';
import App from '@/App.vue';
import '../css/app.css';

const pinia = createPinia();
pinia.use(createPersistedState({ storage: localStorage }));

const app = createApp(App);
app.use(pinia);
app.use(router);
app.mount('#app');

// Register PWA Service Worker for Offline Attendance Caching
if (typeof window !== 'undefined' && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').then((reg) => {
            console.log('[UniLibrary PWA] Service Worker registered:', reg.scope);
            
            // Check for service worker updates
            reg.addEventListener('updatefound', () => {
                const newWorker = reg.installing;
                if (newWorker) {
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('[UniLibrary PWA] New version installed, reloading to apply fresh bundles...');
                            window.location.reload();
                        }
                    });
                }
            });
        }).catch((err) => {
            console.warn('[UniLibrary PWA] Service Worker registration failed:', err);
        });

        // Ensure single reload when service worker takes control
        let refreshing = false;
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            if (!refreshing) {
                refreshing = true;
                window.location.reload();
            }
        });
    });
}
