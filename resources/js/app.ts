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
            
            // Check for service worker updates silently in background
            reg.addEventListener('updatefound', () => {
                const newWorker = reg.installing;
                if (newWorker) {
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('[UniLibrary PWA] Fresh Service Worker installed in background.');
                            // Silent background update: Never forcefully reload while user is actively working
                        }
                    });
                }
            });
        }).catch((err) => {
            console.warn('[UniLibrary PWA] Service Worker registration failed:', err);
        });

        // Controller change listener (silent adoption without disruptive page reload)
        navigator.serviceWorker.addEventListener('controllerchange', () => {
            console.log('[UniLibrary PWA] Service Worker active controller updated.');
        });
    });
}
