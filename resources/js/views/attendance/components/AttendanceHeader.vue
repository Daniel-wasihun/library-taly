<script setup lang="ts">
import { computed } from 'vue';
import { Activity, MapPin, Wifi, WifiOff, RefreshCw } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';
import { useAuthStore } from '@/stores/authStore';
import AppDropdown from '@/components/ui/AppDropdown.vue';
import { isOnline, isSyncing, pendingSyncCount, syncOfflineAttendance } from '@/services/syncService';

interface LibraryOption {
  value: number | null;
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
  set: (val) => emit('update:selectedLibrary', val !== null && val !== '' && val !== undefined ? Number(val) : null),
});

const handleManualSync = async () => {
  if (isSyncing.value || !isOnline.value) return;
  await syncOfflineAttendance(true);
};
</script>

<template>
  <div class="bg-card-bg border border-card-border rounded-sm sm:rounded p-2.5 sm:p-4 flex flex-col md:flex-row md:items-center justify-between gap-2.5 sm:gap-4 shadow-2xs">
    
    <!-- Branding Badge (Hidden on mobile as requested, shown on tablet/desktop) -->
    <div class="hidden md:flex items-center gap-3">
      <div class="w-10 h-10 rounded-sm bg-gradient-to-br from-brand-blue to-blue-700 text-white flex items-center justify-center font-bold shadow-xs border border-white/20 shrink-0">
        <Activity class="w-5 h-5" />
      </div>
      <div>
        <h2 class="text-sm sm:text-base font-bold text-main-text leading-tight tracking-tight flex items-center gap-2">
          <span>{{ langStore.t('attendance.title', 'Physical Library Attendance & Usage Counter') }}</span>
          <!-- Offline / Online Status Pill -->
          <span
            class="text-[10px] font-bold px-2 py-0.5 rounded-full flex items-center gap-1 border"
            :class="isOnline ? 'bg-emerald-500/10 border-emerald-500/20 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/15 border-amber-500/30 text-amber-600 dark:text-amber-400 animate-pulse'"
          >
            <component :is="isOnline ? Wifi : WifiOff" class="w-2.5 h-2.5" />
            {{ isOnline ? 'Online' : 'Offline Mode' }}
          </span>
        </h2>
        <p class="text-[11px] sm:text-xs text-main-text/50 mt-0.5">
          {{ langStore.t('attendance.subtitle', 'Live patron tracking, gender distribution, and automated periodic reporting') }}
        </p>
      </div>
    </div>

    <!-- Center / Right Controls (Branch Dropdown + Tab Switcher + Offline Sync Badge) -->
    <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3 w-full md:w-auto">
      
      <!-- Pending Offline Sync Badge & Trigger -->
      <div v-if="pendingSyncCount > 0" class="flex items-center">
        <button
          type="button"
          @click="handleManualSync"
          :disabled="isSyncing || !isOnline"
          class="w-full sm:w-auto flex items-center justify-center gap-1.5 px-2.5 py-1.5 rounded-xs sm:rounded-sm text-[11px] font-bold border transition cursor-pointer select-none bg-amber-500/10 border-amber-500/30 text-amber-700 dark:text-amber-300 hover:bg-amber-500/20 disabled:opacity-50"
          :title="isOnline ? 'Click to sync pending records' : 'Records saved locally, will sync when connected'"
        >
          <RefreshCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncing }" />
          <span>{{ pendingSyncCount }} Offline Pending</span>
        </button>
      </div>

      <!-- Library Branch Dropdown using attractive reusable AppDropdown -->
      <div class="w-full sm:min-w-[240px] flex-1 md:flex-initial">
        <AppDropdown
          v-model="internalSelectedLibrary"
          :options="libraryOptions"
          :icon="MapPin"
          :disabled="isLibraryDisabled"
          block
          size="md"
          variant="button"
          menuWidth="w-full sm:min-w-[280px]"
        />
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
