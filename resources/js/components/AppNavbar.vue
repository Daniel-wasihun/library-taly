<template>
    <header class="flex items-center justify-between px-6 py-3 border-b shrink-0"
        style="background: var(--bg-card); border-color: var(--border);">
        <!-- Page Title -->
        <div>
            <h1 class="font-semibold text-sm" style="color: var(--text-primary);">{{ pageTitle }}</h1>
            <p class="text-xs mt-0.5" style="color: var(--text-muted);">{{ breadcrumb }}</p>
        </div>

        <!-- Right: Duty Badge + User Info -->
        <div class="flex items-center gap-4">
            <!-- Duty Turn Indicator -->
            <div v-if="!authStore.isAdmin" class="hidden sm:flex items-center gap-2 text-xs px-3 py-1.5 rounded-full"
                :style="authStore.canCirculateBooks
                    ? 'background: var(--lib-success-light); color: var(--lib-success);'
                    : 'background: #fef3c7; color: #92400e;'">
                <div class="w-1.5 h-1.5 rounded-full"
                    :style="authStore.canCirculateBooks ? 'background: var(--lib-success);' : 'background: #d97706;'"></div>
                {{ authStore.canCirculateBooks ? 'Circulation Active' : 'Circulation Off-Turn' }}
            </div>
            <div v-else class="hidden sm:flex items-center gap-2 text-xs px-3 py-1.5 rounded-full"
                style="background: var(--lib-primary-muted); color: var(--lib-primary);">
                <Shield :size="12" />
                Admin Override
            </div>

            <!-- Current Time -->
            <span class="hidden md:block text-xs font-mono" style="color: var(--text-muted);">{{ currentTime }}</span>

            <!-- User Avatar -->
            <div class="flex items-center gap-2">
                <div class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold text-white"
                    style="background: var(--lib-primary-light);">
                    {{ initials }}
                </div>
                <span class="hidden lg:block text-xs font-medium" style="color: var(--text-secondary);">
                    {{ authStore.user?.username }}
                </span>
            </div>
        </div>
    </header>
</template>

<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '@/stores/authStore';
import { Shield } from '@lucide/vue';

const authStore = useAuthStore();
const route = useRoute();

const initials = computed(() => {
    const name = authStore.user?.name ?? '';
    return name.split(' ').slice(0, 2).map((w: string) => w[0]).join('').toUpperCase();
});

const pageTitle = computed(() => (route.meta?.title as string) ?? 'Library Portal');
const breadcrumb = computed(() => `UniLibrary / ${pageTitle.value}`);

const currentTime = ref('');
let timer: ReturnType<typeof setInterval>;

function updateTime() {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
}

onMounted(() => {
    updateTime();
    timer = setInterval(updateTime, 30000);
});

onUnmounted(() => clearInterval(timer));
</script>
