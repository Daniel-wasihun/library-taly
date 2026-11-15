<template>
  <div class="flex h-screen overflow-hidden bg-main-bg text-main-text font-sans">
    <!-- Sidebar -->
    <Sidebar />

    <!-- Main Content Area -->
    <div class="flex flex-col flex-1 min-w-0 overflow-hidden bg-main-bg">
      <!-- Navbar -->
      <Navbar />

      <!-- Scrollable Main Content -->
      <main class="flex-1 overflow-y-auto p-4 sm:p-6 md:p-8 bg-main-bg">
        <slot></slot>
      </main>
    </div>

    <!-- Toast Notifications -->
    <Toast />
  </div>
</template>

<script setup lang="ts">
import Sidebar from '@/components/Sidebar.vue';
import Navbar from '@/components/Navbar.vue';
import Toast from '@/components/Toast.vue';
</script>
