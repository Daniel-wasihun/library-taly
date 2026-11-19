<template>
  <div class="flex h-screen overflow-hidden bg-main-bg text-main-text font-sans relative">
    <!-- Mobile Backdrop Overlay -->
    <transition
      enter-active-class="transition-opacity duration-200"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isMobileMenuOpen"
        @click="isMobileMenuOpen = false"
        class="fixed inset-0 bg-black/50 backdrop-blur-xs z-40 lg:hidden"
      ></div>
    </transition>

    <!-- Sidebar: off-canvas drawer on mobile (< lg), standard static column on desktop (lg+) with collapse support -->
    <div
      class="fixed inset-y-0 left-0 z-50 transform lg:static lg:translate-x-0 transition-all duration-300 ease-in-out shrink-0"
      :class="[
        isMobileMenuOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0',
        isSidebarCollapsed ? 'w-18' : 'w-60'
      ]"
    >
      <Sidebar
        :is-collapsed="isSidebarCollapsed"
        @toggle="toggleSidebar"
      />
    </div>

    <!-- Main Content Area -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden bg-main-bg">
      <!-- Navbar with mobile drawer toggle and desktop sidebar toggle -->
      <Navbar
        :is-collapsed="isSidebarCollapsed"
        @toggleSidebar="toggleSidebar"
        @toggleMobileMenu="isMobileMenuOpen = !isMobileMenuOpen"
      />

      <!-- Scrollable Main Content -->
      <main class="flex-1 overflow-y-auto p-3 sm:p-5 md:p-8 bg-main-bg">
        <router-view />
        <slot></slot>
      </main>
    </div>

    <!-- Toast Notifications -->
    <Toast />
  </div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '@/components/Sidebar.vue';
import Navbar from '@/components/Navbar.vue';
import Toast from '@/components/Toast.vue';

const isMobileMenuOpen = ref(false);
const isSidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');
const route = useRoute();

const toggleSidebar = () => {
  isSidebarCollapsed.value = !isSidebarCollapsed.value;
  localStorage.setItem('sidebar_collapsed', isSidebarCollapsed.value.toString());
};

// Auto-close mobile drawer when route changes
watch(() => route.path, () => {
  isMobileMenuOpen.value = false;
});
</script>
