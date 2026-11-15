<template>
    <div class="flex h-screen overflow-hidden" style="background: var(--bg-page); color: var(--text-primary);">
        <!-- Sidebar -->
        <AppSidebar />

        <!-- Main Content -->
        <div class="flex flex-col flex-1 min-w-0 overflow-hidden">
            <!-- Topbar -->
            <AppNavbar />

            <!-- Page Content -->
            <main class="flex-1 overflow-y-auto p-6">
                <router-view v-slot="{ Component }">
                    <transition name="fade" mode="out-in">
                        <component :is="Component" />
                    </transition>
                </router-view>
            </main>
        </div>

        <!-- Toast Notifications -->
        <AppToast />
    </div>
</template>

<script setup lang="ts">
import AppSidebar from '@/components/AppSidebar.vue';
import AppNavbar from '@/components/AppNavbar.vue';
import AppToast from '@/components/AppToast.vue';
</script>
