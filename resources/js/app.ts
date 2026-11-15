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
