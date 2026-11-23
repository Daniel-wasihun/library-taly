<script setup lang="ts">
import { computed } from 'vue';
import { Users2, GraduationCap, School, Briefcase, Sparkles, Lock } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';
import { useAuthStore } from '@/stores/authStore';

const props = defineProps<{
  selectedLibrary: number | null;
  selectedUserType: 'student' | 'teacher' | 'staff' | string;
  dutyStatus?: {
    allowed: boolean;
    reason: string;
    code?: string;
    shift?: any;
    next_turn?: string | null;
  } | null;
  loading?: boolean;
}>();

const emit = defineEmits<{
  (e: 'log', gender: 'male' | 'female', count: number): void;
  (e: 'update:selectedUserType', val: 'student' | 'teacher' | 'staff'): void;
}>();

const langStore = useLanguageStore();
const authStore = useAuthStore();

const canRecord = computed(() => {
  return authStore.isSuperAdmin || authStore.isAdmin || authStore.hasPermission('attendance.record');
});

const isDutyAllowed = computed(() => {
  if (authStore.isSuperAdmin || authStore.isAdmin) return true;
  if (!props.dutyStatus) return false;
  return Boolean(props.dutyStatus.allowed);
});

const canLog = computed(() => {
  return Boolean(props.selectedLibrary) && canRecord.value && isDutyAllowed.value;
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
  <div class="lg:col-span-4 xl:col-span-4 2xl:col-span-3 bg-card-bg border border-card-border rounded-sm sm:rounded p-2.5 sm:p-5 flex flex-col items-center justify-start relative transition-all duration-300 shadow-2xs">
    
    <!-- Skeleton Loading State -->
    <div v-if="loading" class="w-full space-y-4 animate-pulse">
      <div class="h-3.5 bg-slate-200 dark:bg-slate-700/60 rounded w-1/3"></div>
      <div class="h-9 bg-slate-200 dark:bg-slate-700/60 rounded-sm w-full"></div>
      <div class="grid grid-cols-2 gap-3 pt-2">
        <div class="h-36 bg-slate-200 dark:bg-slate-700/60 rounded-md"></div>
        <div class="h-36 bg-slate-200 dark:bg-slate-700/60 rounded-md"></div>
      </div>
      <div class="h-20 bg-slate-200 dark:bg-slate-700/40 rounded-sm w-full"></div>
    </div>

    <template v-else>
      <!-- Notice if user cannot record (e.g. Manager view-only) -->
      <div v-if="!canRecord" class="w-full mb-2.5 p-2 rounded-xs bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-300 text-[10px] sm:text-[11px] text-center font-medium leading-tight">
        {{ langStore.t('attendance.attendant_only_notice', 'Live visitor counting is assigned to desk attendants.') }}
      </div>

      <!-- Duty Shift Inactive Banner (Lock counter when outside scheduled shift date & time) -->
      <div v-if="!isDutyAllowed" class="w-full mb-3 p-3 rounded-xs sm:rounded-sm bg-rose-500/10 border border-rose-500/30 text-rose-800 dark:text-rose-200 text-left">
        <div class="flex items-center gap-1.5 font-bold text-xs text-rose-700 dark:text-rose-400 mb-1">
          <Lock class="w-4 h-4 shrink-0" />
          <span>{{ langStore.t('attendance.counter_locked_title', 'Usage Counter Inactive (Outside Assigned Shift)') }}</span>
        </div>
        <p class="text-[11px] leading-relaxed opacity-90">
          {{ props.dutyStatus?.reason || langStore.t('attendance.counter_locked_desc', 'You can only record patron usage counter data during your active assigned shift date and time window.') }}
        </p>
        <div v-if="props.dutyStatus?.next_turn" class="mt-1.5 text-[10px] font-mono font-medium text-rose-700 dark:text-rose-300">
          {{ langStore.t('attendance.next_turn', 'Next turn') }}: {{ props.dutyStatus.next_turn }}
        </div>
      </div>

      <!-- Active Shift Status Badge -->
      <div v-else-if="props.dutyStatus?.allowed && !authStore.isAdmin" class="w-full mb-3 p-2 rounded-xs sm:rounded-sm bg-emerald-500/10 border border-emerald-500/30 text-emerald-800 dark:text-emerald-300 flex items-center gap-2 text-xs font-semibold">
        <Sparkles class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400 shrink-0" />
        <span class="truncate">🟢 {{ langStore.t('attendance.shift_active', 'Active Shift Duty: Counter Ready') }}</span>
      </div>

      <!-- TOP: Patron Type Toggle (Mobile Standard Scaled) -->
      <div class="w-full mb-3 sm:mb-4">
        <div class="flex items-center justify-between mb-1.5 sm:mb-2 px-1">
          <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-main-text/60 flex items-center gap-1">
            <Sparkles class="w-3 h-3 text-brand-blue" />
            {{ langStore.t('attendance.select_patron_type', 'Patron Type') }}
          </span>
          <span class="text-[9px] sm:text-[10px] font-semibold px-2 py-0.5 rounded-xs bg-main-bg border border-card-border/80 text-main-text/70 capitalize">
            {{ langStore.localizeType(selectedUserType) }}
          </span>
        </div>

        <!-- Segmented Pill Selector -->
        <div class="grid grid-cols-3 gap-1 p-0.5 sm:p-1 rounded-sm bg-main-bg/80 border border-card-border shadow-inner">
          <button
            v-for="ut in userTypes"
            :key="ut.id"
            type="button"
            @click="emit('update:selectedUserType', ut.id)"
            class="flex items-center justify-center gap-1 sm:gap-1.5 py-1.5 sm:py-2.5 px-1.5 sm:px-2 rounded-xs sm:rounded-sm text-[10px] sm:text-xs font-bold transition-all duration-150 select-none cursor-pointer"
            :class="selectedUserType === ut.id
              ? 'bg-brand-blue text-white shadow-xs scale-[1.01]'
              : 'text-main-text/70 hover:text-main-text hover:bg-card-bg/60 active:scale-95'"
          >
            <component :is="ut.icon" class="w-3 h-3 sm:w-3.5 sm:h-3.5 shrink-0" />
            <span class="truncate">{{ ut.label }}</span>
          </button>
        </div>
      </div>

      <!-- GENDER COUNTER PADS: Responsive 2-Column Grid (Zero Overlap, Stacked One Above the Other) -->
      <div class="grid grid-cols-2 lg:grid-cols-1 xl:grid-cols-2 gap-2.5 sm:gap-4 w-full flex-1 items-start">
        
        <!-- Male Counter Column -->
        <div class="flex flex-col items-center gap-2.5 sm:gap-3 w-full bg-blue-50/30 dark:bg-blue-950/20 border border-brand-blue/15 rounded-md p-2.5 sm:p-3.5 transition-all">
          <button
            type="button"
            @click="emit('log', 'male', 1)"
            :disabled="!canLog"
            class="w-full min-h-[110px] sm:min-h-[130px] py-3.5 sm:py-4 px-2 rounded-md flex flex-col items-center justify-between gap-2 border border-white/20 group/btn bg-brand-blue text-white shadow-sm sm:shadow-md hover:bg-brand-blue/90 hover:scale-[1.01] active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:pointer-events-none cursor-pointer"
          >
            <!-- Top: Icon in generous circle -->
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-white/20 flex items-center justify-center shrink-0 shadow-inner group-hover/btn:scale-105 transition-transform">
              <Users2 class="w-5 h-5 sm:w-6 sm:h-6 text-white" />
            </div>

            <!-- Middle: Gender Label -->
            <span class="text-xs sm:text-sm font-extrabold capitalize tracking-wider text-white text-center leading-tight px-1">
              {{ langStore.localizeGender('male') }}
            </span>

            <!-- Bottom: Count Badge stacked cleanly below -->
            <span class="inline-flex items-center justify-center px-3 py-0.5 rounded-full bg-white/25 text-white font-mono font-bold text-[11px] sm:text-xs shadow-2xs">
              +1
            </span>
          </button>

          <!-- Quick Batch Buttons (Male) -->
          <div class="w-full">
            <div class="text-[9px] sm:text-[10px] font-bold text-brand-blue/70 text-center uppercase tracking-wider mb-1.5">
              {{ langStore.t('common.batch', 'Batch') }} (+2 .. +15)
            </div>
            <div class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-4 gap-1 sm:gap-1.5 w-full">
              <button
                v-for="n in batchOptions"
                :key="'male-' + n"
                type="button"
                @click="emit('log', 'male', n)"
                :disabled="!canLog"
                class="h-7 sm:h-8 px-1 rounded-sm border font-bold text-[10px] sm:text-xs font-mono flex items-center justify-center transition-all duration-150 active:scale-90 disabled:opacity-25 disabled:pointer-events-none cursor-pointer bg-white/80 dark:bg-slate-900/60 text-brand-blue border-brand-blue/20 hover:bg-brand-blue hover:text-white dark:hover:bg-brand-blue dark:hover:text-white shadow-2xs"
              >
                +{{ n }}
              </button>
            </div>
          </div>
        </div>

        <!-- Female Counter Column -->
        <div class="flex flex-col items-center gap-2.5 sm:gap-3 w-full bg-amber-50/30 dark:bg-amber-950/20 border border-brand-yellow/25 rounded-md p-2.5 sm:p-3.5 transition-all">
          <button
            type="button"
            @click="emit('log', 'female', 1)"
            :disabled="!canLog"
            class="w-full min-h-[110px] sm:min-h-[130px] py-3.5 sm:py-4 px-2 rounded-md flex flex-col items-center justify-between gap-2 border border-white/20 group/btn bg-brand-yellow text-white shadow-sm sm:shadow-md hover:bg-brand-yellow/90 hover:scale-[1.01] active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed disabled:pointer-events-none cursor-pointer"
          >
            <!-- Top: Icon in generous circle -->
            <div class="w-10 h-10 sm:w-11 sm:h-11 rounded-full bg-white/20 flex items-center justify-center shrink-0 shadow-inner group-hover/btn:scale-105 transition-transform">
              <Users2 class="w-5 h-5 sm:w-6 sm:h-6 text-white" />
            </div>

            <!-- Middle: Gender Label -->
            <span class="text-xs sm:text-sm font-extrabold capitalize tracking-wider text-white text-center leading-tight px-1">
              {{ langStore.localizeGender('female') }}
            </span>

            <!-- Bottom: Count Badge stacked cleanly below -->
            <span class="inline-flex items-center justify-center px-3 py-0.5 rounded-full bg-white/25 text-white font-mono font-bold text-[11px] sm:text-xs shadow-2xs">
              +1
            </span>
          </button>

          <!-- Quick Batch Buttons (Female) -->
          <div class="w-full">
            <div class="text-[9px] sm:text-[10px] font-bold text-amber-700/80 dark:text-amber-400 text-center uppercase tracking-wider mb-1.5">
              {{ langStore.t('common.batch', 'Batch') }} (+2 .. +15)
            </div>
            <div class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-7 lg:grid-cols-4 gap-1 sm:gap-1.5 w-full">
              <button
                v-for="n in batchOptions"
                :key="'female-' + n"
                type="button"
                @click="emit('log', 'female', n)"
                :disabled="!canLog"
                class="h-7 sm:h-8 px-1 rounded-sm border font-bold text-[10px] sm:text-xs font-mono flex items-center justify-center transition-all duration-150 active:scale-90 disabled:opacity-25 disabled:pointer-events-none cursor-pointer bg-white/80 dark:bg-slate-900/60 text-amber-600 dark:text-amber-400 border-amber-500/25 hover:bg-brand-yellow hover:text-white dark:hover:bg-brand-yellow dark:hover:text-white shadow-2xs"
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
    </template>

  </div>
</template>
