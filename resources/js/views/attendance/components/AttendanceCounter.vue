<script setup lang="ts">
import { computed } from 'vue';
import { Users2, GraduationCap, School, Briefcase, Sparkles } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';
import { useAuthStore } from '@/stores/authStore';

const props = defineProps<{
  selectedLibrary: number | null;
  selectedUserType: 'student' | 'teacher' | 'staff' | string;
}>();

const emit = defineEmits<{
  (e: 'log', gender: 'male' | 'female', count: number): void;
  (e: 'update:selectedUserType', val: 'student' | 'teacher' | 'staff'): void;
}>();

const langStore = useLanguageStore();
const authStore = useAuthStore();

const canLog = computed(() => {
  return Boolean(props.selectedLibrary);
});

const userTypes = computed(() => [
  {
    id: 'student' as const,
    label: langStore.localizeType('student'),
    icon: GraduationCap,
  },
  {
    id: 'teacher' as const,
    label: langStore.localizeType('teacher'),
    icon: School,
  },
  {
    id: 'staff' as const,
    label: langStore.localizeType('staff'),
    icon: Briefcase,
  },
]);

const batchOptions = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
</script>

<template>
  <div class="lg:col-span-4 xl:col-span-4 2xl:col-span-3 bg-card-bg border border-card-border rounded-xl sm:rounded-2xl p-2.5 sm:p-5 flex flex-col items-center justify-start relative transition-all duration-300 shadow-2xs">
    
    <!-- TOP: Patron Type Toggle (Mobile Standard Scaled) -->
    <div class="w-full mb-2.5 sm:mb-4">
      <div class="flex items-center justify-between mb-1.5 sm:mb-2 px-1">
        <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-main-text/60 flex items-center gap-1">
          <Sparkles class="w-3 h-3 text-brand-blue" />
          {{ langStore.t('attendance.select_patron_type', 'Patron Type') }}
        </span>
        <span class="text-[9px] sm:text-[10px] font-semibold px-2 py-0.5 rounded-full bg-main-bg border border-card-border/80 text-main-text/70 capitalize">
          {{ langStore.localizeType(selectedUserType) }}
        </span>
      </div>

      <!-- Segmented Pill Selector -->
      <div class="grid grid-cols-3 gap-1 p-0.5 sm:p-1 rounded-lg sm:rounded-xl bg-main-bg/80 border border-card-border shadow-inner">
        <button
          v-for="ut in userTypes"
          :key="ut.id"
          type="button"
          @click="emit('update:selectedUserType', ut.id)"
          class="flex items-center justify-center gap-1 sm:gap-1.5 py-1.5 sm:py-2.5 px-1.5 sm:px-2 rounded-md sm:rounded-lg text-[10px] sm:text-xs font-bold transition-all duration-150 select-none cursor-pointer"
          :class="selectedUserType === ut.id
            ? 'bg-brand-blue text-white shadow-xs scale-[1.01]'
            : 'text-main-text/70 hover:text-main-text hover:bg-card-bg/60 active:scale-95'"
        >
          <component :is="ut.icon" class="w-3 h-3 sm:w-3.5 sm:h-3.5 shrink-0" />
          <span class="truncate">{{ ut.label }}</span>
        </button>
      </div>
    </div>

    <!-- GENDER COUNTER PADS: Responsive 2-Column Grid on Mobile/Tablet -->
    <div class="grid grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 gap-2 sm:gap-4 w-full flex-1 items-start">
      
      <!-- Male Counter Column -->
      <div class="flex flex-col items-center gap-2 sm:gap-3 w-full bg-blue-50/30 dark:bg-blue-950/20 border border-brand-blue/15 rounded-xl sm:rounded-2xl p-2 sm:p-3.5 transition-all">
        <button
          type="button"
          @click="emit('log', 'male', 1)"
          :disabled="!canLog"
          class="w-full max-w-[100px] sm:max-w-[140px] aspect-square rounded-xl sm:rounded-3xl flex flex-col items-center justify-center border border-white/20 group/btn bg-brand-blue text-white shadow-sm sm:shadow-md hover:bg-brand-blue/90 hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
        >
          <div class="w-7 h-7 sm:w-10 sm:h-10 rounded-lg sm:rounded-2xl bg-white/15 flex items-center justify-center mb-0.5 sm:mb-1 group-hover/btn:scale-110 transition-transform">
            <Users2 class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
          </div>
          <span class="text-[11px] sm:text-sm font-bold capitalize tracking-wide sm:tracking-widest">
            {{ langStore.localizeGender('male') }}
          </span>
          <span class="text-[9px] sm:text-[10px] text-white/80 font-mono font-bold">+1</span>
        </button>

        <!-- Quick Batch Buttons (Male) -->
        <div class="w-full">
          <div class="text-[9px] sm:text-[10px] font-bold text-brand-blue/70 text-center uppercase tracking-wider mb-1">
            {{ langStore.t('common.batch', 'Batch') }} (+2 .. +15)
          </div>
          <div class="grid grid-cols-4 sm:grid-cols-7 lg:grid-cols-4 xl:grid-cols-4 gap-1 sm:gap-1.5 justify-items-center w-full">
            <button
              v-for="n in batchOptions"
              :key="'male-' + n"
              type="button"
              @click="emit('log', 'male', n)"
              :disabled="!canLog"
              class="w-6 h-6 sm:w-8 sm:h-8 rounded sm:rounded-lg border font-bold text-[10px] sm:text-xs flex items-center justify-center transition-all duration-150 active:scale-90 disabled:opacity-25 cursor-pointer bg-white/80 dark:bg-slate-900/60 text-brand-blue border-brand-blue/20 hover:bg-brand-blue hover:text-white dark:hover:bg-brand-blue dark:hover:text-white"
            >
              +{{ n }}
            </button>
          </div>
        </div>
      </div>

      <!-- Female Counter Column -->
      <div class="flex flex-col items-center gap-2 sm:gap-3 w-full bg-amber-50/30 dark:bg-amber-950/20 border border-brand-yellow/25 rounded-xl sm:rounded-2xl p-2 sm:p-3.5 transition-all">
        <button
          type="button"
          @click="emit('log', 'female', 1)"
          :disabled="!canLog"
          class="w-full max-w-[100px] sm:max-w-[140px] aspect-square rounded-xl sm:rounded-3xl flex flex-col items-center justify-center border border-white/20 group/btn bg-brand-yellow text-white shadow-sm sm:shadow-md hover:bg-brand-yellow/90 hover:scale-[1.02] active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
        >
          <div class="w-7 h-7 sm:w-10 sm:h-10 rounded-lg sm:rounded-2xl bg-white/15 flex items-center justify-center mb-0.5 sm:mb-1 group-hover/btn:scale-110 transition-transform">
            <Users2 class="w-4 h-4 sm:w-5 sm:h-5 text-white" />
          </div>
          <span class="text-[11px] sm:text-sm font-bold capitalize tracking-wide sm:tracking-widest">
            {{ langStore.localizeGender('female') }}
          </span>
          <span class="text-[9px] sm:text-[10px] text-white/80 font-mono font-bold">+1</span>
        </button>

        <!-- Quick Batch Buttons (Female) -->
        <div class="w-full">
          <div class="text-[9px] sm:text-[10px] font-bold text-amber-700/80 dark:text-amber-400 text-center uppercase tracking-wider mb-1">
            {{ langStore.t('common.batch', 'Batch') }} (+2 .. +15)
          </div>
          <div class="grid grid-cols-4 sm:grid-cols-7 lg:grid-cols-4 xl:grid-cols-4 gap-1 sm:gap-1.5 justify-items-center w-full">
            <button
              v-for="n in batchOptions"
              :key="'female-' + n"
              type="button"
              @click="emit('log', 'female', n)"
              :disabled="!canLog"
              class="w-6 h-6 sm:w-8 sm:h-8 rounded sm:rounded-lg border font-bold text-[10px] sm:text-xs flex items-center justify-center transition-all duration-150 active:scale-90 disabled:opacity-25 cursor-pointer bg-white/80 dark:bg-slate-900/60 text-amber-600 dark:text-amber-400 border-amber-500/25 hover:bg-brand-yellow hover:text-white dark:hover:bg-brand-yellow dark:hover:text-white"
            >
              +{{ n }}
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Micro Instruction -->
    <div class="w-full pt-2 sm:pt-3 mt-2 sm:mt-3 border-t border-card-border/60 text-center">
      <p class="text-[9px] sm:text-[11px] text-main-text/45 leading-normal">
        {{ langStore.t('attendance.quick_log_hint', 'Tap the large button for single visitors or use quick batch numbers (+2 .. +15) for group visits.') }}
      </p>
    </div>

  </div>
</template>
