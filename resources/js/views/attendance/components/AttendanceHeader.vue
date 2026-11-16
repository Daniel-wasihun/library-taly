<script setup lang="ts">
import { computed } from 'vue';
import { Activity, MapPin } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';
import { useAuthStore } from '@/stores/authStore';

interface LibraryOption {
  value: number;
  label: string;
}

interface TabOption {
  id: string;
  label: string;
  icon: any;
}

const props = defineProps<{
  selectedLibrary: number | null;
  libraryOptions: LibraryOption[];
  activeTab: string;
  tabs: TabOption[];
}>();

const emit = defineEmits<{
  (e: 'update:selectedLibrary', value: number | null): void;
  (e: 'update:activeTab', value: string): void;
}>();

const langStore = useLanguageStore();
const authStore = useAuthStore();

const isLibraryDisabled = computed(() => {
  return !authStore.canManageAllLibraries;
});

const internalSelectedLibrary = computed({
  get: () => props.selectedLibrary,
  set: (val) => emit('update:selectedLibrary', val ? Number(val) : null),
});
</script>

<template>
  <div class="bg-card-bg border border-card-border rounded-sm sm:rounded p-2.5 sm:p-4 flex flex-col md:flex-row md:items-center justify-between gap-2.5 sm:gap-4 shadow-2xs">
    
    <!-- Branding Badge (Hidden on mobile as requested, shown on tablet/desktop) -->
    <div class="hidden md:flex items-center gap-3">
      <div class="w-10 h-10 rounded-sm bg-gradient-to-br from-brand-blue to-blue-700 text-white flex items-center justify-center font-bold shadow-xs border border-white/20 shrink-0">
        <Activity class="w-5 h-5" />
      </div>
      <div>
        <h2 class="text-sm sm:text-base font-bold text-main-text leading-tight tracking-tight">
          {{ langStore.t('attendance.title', 'Physical Library Attendance & Usage Counter') }}
        </h2>
        <p class="text-[11px] sm:text-xs text-main-text/50 mt-0.5">
          {{ langStore.t('attendance.subtitle', 'Live patron tracking, gender distribution, and automated periodic reporting') }}
        </p>
      </div>
    </div>

    <!-- Center / Right Controls (Branch Dropdown + Tab Switcher) -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 w-full md:w-auto">
      
      <!-- Library Branch Dropdown -->
      <div class="relative w-full sm:min-w-[240px] flex-1 md:flex-initial">
        <div class="absolute left-2.5 sm:left-3 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
          <MapPin class="w-3.5 h-3.5 sm:w-4 sm:h-4 text-brand-blue" />
        </div>
        <select
          v-model="internalSelectedLibrary"
          :disabled="isLibraryDisabled"
          class="w-full h-8 sm:h-10 pl-8 sm:pl-9 pr-7 sm:pr-8 text-[11px] sm:text-xs font-semibold rounded-sm bg-main-bg/50 border border-card-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs disabled:opacity-60 cursor-pointer"
        >
          <option v-for="lib in libraryOptions" :key="lib.value" :value="lib.value">
            {{ lib.label }}
          </option>
        </select>
      </div>

      <!-- Active Tab Switcher (Live Counter vs Analytics) -->
      <div class="grid grid-cols-2 sm:flex items-center p-0.5 sm:p-1 rounded-sm border border-card-border bg-main-bg/60 shadow-2xs">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          @click="emit('update:activeTab', tab.id)"
          class="flex items-center justify-center gap-1.5 px-2.5 py-1.5 sm:px-3.5 sm:py-1.5 rounded-xs sm:rounded-sm text-[11px] sm:text-xs font-bold transition cursor-pointer select-none"
          :class="activeTab === tab.id
            ? 'bg-brand-blue text-white shadow-2xs'
            : 'text-main-text/60 hover:text-main-text hover:bg-main-text/5'"
        >
          <component :is="tab.icon" class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
          <span class="truncate">{{ tab.label }}</span>
        </button>
      </div>

    </div>
  </div>
</template>
