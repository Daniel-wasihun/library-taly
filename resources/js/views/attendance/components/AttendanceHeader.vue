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
  selectedUserType: 'student' | 'teacher' | 'staff';
  activeTab: string;
  tabs: TabOption[];
}>();

const emit = defineEmits<{
  (e: 'update:selectedLibrary', value: number | null): void;
  (e: 'update:selectedUserType', value: 'student' | 'teacher' | 'staff'): void;
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

const userTypes: { id: 'student' | 'teacher' | 'staff'; label: string }[] = [
  { id: 'student', label: langStore.t('attendance.student', 'Student') },
  { id: 'teacher', label: langStore.t('attendance.teacher', 'Teacher') },
  { id: 'staff', label: langStore.t('attendance.staff', 'Staff') },
];
</script>

<template>
  <div class="bg-card-bg border border-card-border rounded-2xl p-4 flex flex-wrap items-center justify-between gap-4 shadow-2xs">
    <!-- Branding Badge -->
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-brand-blue to-blue-700 text-white flex items-center justify-center font-bold shadow-xs border border-white/20 shrink-0">
        <Activity class="w-5 h-5" />
      </div>
      <div>
        <h2 class="text-base font-bold text-main-text leading-tight tracking-tight">
          {{ langStore.t('attendance.title', 'Physical Library Attendance & Usage Counter') }}
        </h2>
        <p class="text-xs text-main-text/50 mt-0.5">
          {{ langStore.t('attendance.subtitle', 'Live patron tracking, gender distribution, and automated periodic reporting') }}
        </p>
      </div>
    </div>

    <!-- Center / Right Controls -->
    <div class="flex flex-wrap items-center gap-3 w-full lg:w-auto">
      <!-- Library Branch Dropdown -->
      <div class="relative min-w-[220px] flex-1 lg:flex-initial">
        <div class="absolute left-3 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
          <MapPin class="w-4 h-4 text-brand-blue" />
        </div>
        <select
          v-model="internalSelectedLibrary"
          :disabled="isLibraryDisabled"
          class="w-full h-10 pl-9 pr-8 text-xs font-semibold rounded-xl bg-main-bg/50 border border-card-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs disabled:opacity-60 cursor-pointer"
        >
          <option v-for="lib in libraryOptions" :key="lib.value" :value="lib.value">
            {{ lib.label }}
          </option>
        </select>
      </div>

      <!-- User Type Selector Pill Group -->
      <div class="flex items-center p-1 rounded-xl border border-card-border bg-main-bg/60 shadow-2xs">
        <button
          v-for="ut in userTypes"
          :key="ut.id"
          type="button"
          @click="emit('update:selectedUserType', ut.id)"
          class="px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer select-none"
          :class="selectedUserType === ut.id
            ? 'bg-nav-accent text-white dark:text-slate-900 shadow-2xs'
            : 'text-main-text/60 hover:text-main-text hover:bg-main-text/5'"
        >
          {{ ut.label }}
        </button>
      </div>

      <!-- Active Tab Switcher (Live Counter vs Analytics) -->
      <div class="flex items-center p-1 rounded-xl border border-card-border bg-main-bg/60 shadow-2xs">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          type="button"
          @click="emit('update:activeTab', tab.id)"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold transition cursor-pointer select-none"
          :class="activeTab === tab.id
            ? 'bg-nav-accent text-white dark:text-slate-900 shadow-2xs'
            : 'text-main-text/60 hover:text-main-text hover:bg-main-text/5'"
        >
          <component :is="tab.icon" class="w-3.5 h-3.5" />
          <span>{{ tab.label }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
