<template>
    <router-view />
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { useAuthStore } from '@/stores/authStore';

const authStore = useAuthStore();

onMounted(async () => {
    if (authStore.token && !authStore.user) {
        await authStore.fetchMe();
    }
});
</script>
