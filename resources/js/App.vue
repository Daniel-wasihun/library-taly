<template>
    <router-view />
    <Toast />
    <PwaInstallPrompt />
    <PdfPreviewModal />
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/authStore';
import { useThemeStore } from '@/stores/themeStore';
import { useLanguageStore } from '@/stores/languageStore';
import Toast from '@/components/Toast.vue';
import PwaInstallPrompt from '@/components/pwa/PwaInstallPrompt.vue';
import PdfPreviewModal from '@/components/pwa/PdfPreviewModal.vue';

const authStore = useAuthStore();
const themeStore = useThemeStore();
const langStore = useLanguageStore();

onMounted(async () => {
    // Warm up translations and language synchronization
    await langStore.loadTranslations();

    // Rehydrate authenticated user if token exists
    if (authStore.token && !authStore.user) {
        await authStore.fetchMe();
    }
});
</script>
