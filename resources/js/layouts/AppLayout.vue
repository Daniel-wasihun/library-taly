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

    <!-- Sidebar Container: Resizable on desktop, off-canvas on mobile -->
    <div
      class="fixed inset-y-0 left-0 z-50 transform lg:static lg:translate-x-0 shrink-0 flex"
      :class="[
        isMobileMenuOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:translate-x-0',
        isResizing ? '' : 'transition-all duration-300 ease-in-out',
        'w-56 sm:w-60 lg:w-auto'
      ]"
      :style="sidebarContainerStyle"
    >
      <Sidebar
        :is-collapsed="isDesktop ? isSidebarCollapsed : false"
        :is-mobile="!isDesktop"
        @toggle="toggleSidebar"
        @close-mobile="isMobileMenuOpen = false"
      />

      <!-- Drag Handle for Resizing Sidebar (Desktop only, when expanded) -->
      <div
        v-if="!isSidebarCollapsed"
        @mousedown="startResize"
        class="hidden lg:block absolute right-0 top-0 bottom-0 w-2 cursor-col-resize hover:bg-brand-blue/30 active:bg-brand-blue/50 transition-colors z-40 select-none group"
        title="Drag to resize sidebar"
      >
        <div class="h-8 w-1 bg-card-border group-hover:bg-brand-blue/80 rounded-full absolute top-1/2 -translate-y-1/2 right-0.5 transition-colors"></div>
      </div>
    </div>

    <!-- Main Content Area -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden bg-main-bg">
      <!-- Navbar with mobile drawer toggle and desktop sidebar toggle (Toggle only in Navbar) -->
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
import { ref, watch, computed, onMounted, onUnmounted } from 'vue';
import { useRoute } from 'vue-router';
import Sidebar from '@/components/Sidebar.vue';
import Navbar from '@/components/Navbar.vue';
import Toast from '@/components/Toast.vue';

const isMobileMenuOpen = ref(false);
const isSidebarCollapsed = ref(localStorage.getItem('sidebar_collapsed') === 'true');
const storedWidth = Number(localStorage.getItem('sidebar_width'));
const sidebarWidth = ref(storedWidth && storedWidth >= 200 && storedWidth <= 420 ? storedWidth : 240);
const isResizing = ref(false);
const route = useRoute();

const isDesktop = ref(typeof window !== 'undefined' ? window.innerWidth >= 1024 : true);

const onWindowResize = () => {
  if (typeof window !== 'undefined') {
    isDesktop.value = window.innerWidth >= 1024;
  }
};

onMounted(() => {
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', onWindowResize);
    isDesktop.value = window.innerWidth >= 1024;
  }
});

onUnmounted(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', onWindowResize);
  }
});

const sidebarContainerStyle = computed(() => {
  if (!isDesktop.value) {
    return {};
  }
  return { width: isSidebarCollapsed.value ? '4.5rem' : `${sidebarWidth.value}px` };
});

const toggleSidebar = () => {
  isSidebarCollapsed.value = !isSidebarCollapsed.value;
  localStorage.setItem('sidebar_collapsed', isSidebarCollapsed.value.toString());
};

const startResize = (e: MouseEvent) => {
  e.preventDefault();
  isResizing.value = true;
  document.body.style.cursor = 'col-resize';
  document.body.style.userSelect = 'none';

  const onMouseMove = (moveEvent: MouseEvent) => {
    if (!isResizing.value) return;
    const newWidth = Math.max(200, Math.min(420, moveEvent.clientX));
    sidebarWidth.value = newWidth;
  };

  const onMouseUp = () => {
    isResizing.value = false;
    document.body.style.cursor = '';
    document.body.style.userSelect = '';
    localStorage.setItem('sidebar_width', sidebarWidth.value.toString());
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
  };

  window.addEventListener('mousemove', onMouseMove);
  window.addEventListener('mouseup', onMouseUp);
};

// Auto-close mobile drawer when route changes
watch(() => route.path, () => {
  isMobileMenuOpen.value = false;
});
</script>
