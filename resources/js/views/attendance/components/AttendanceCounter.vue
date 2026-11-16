<script setup lang="ts">
import { computed } from 'vue';
import { Users2 } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';
import { useAuthStore } from '@/stores/authStore';

const props = defineProps<{
  selectedLibrary: number | null;
  selectedUserType: string;
}>();

const emit = defineEmits<{
  (e: 'log', gender: 'male' | 'female', count: number): void;
}>();

const langStore = useLanguageStore();
const authStore = useAuthStore();

const canLog = computed(() => {
  return Boolean(props.selectedLibrary);
});

const batchOptions = [2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];

const getBatchClass = (n: number) => {
  return 'w-9 h-9 sm:w-10 sm:h-10 rounded-lg border font-semibold text-xs flex items-center justify-center transition-all duration-200 active:scale-95 disabled:opacity-25 cursor-pointer shadow-none';
};
</script>

<template>
  <div class="lg:col-span-4 bg-card-bg border border-card-border rounded-2xl p-0 flex flex-col items-center justify-start relative transition-all duration-300 min-h-[580px] shadow-2xs">
    <div class="flex-1 w-full p-6 md:p-8 flex flex-col justify-center items-center">
      <!-- Section Header -->
      <div class="text-center pb-3 mb-4 w-full border-b border-card-border/60">
        <span class="text-xs uppercase tracking-widest font-bold text-main-text/50">
          {{ langStore.t('attendance.live_desk', 'Usage Counter Desk') }} · {{ langStore.localizeType(selectedUserType) }}
        </span>
      </div>

      <div class="flex flex-col sm:flex-row lg:flex-col gap-8 md:gap-10 flex-1 w-full py-4 justify-center items-center">
        <!-- Male Section matching LMS -->
        <div class="flex flex-col items-center gap-4 w-full">
          <button
            type="button"
            @click="emit('log', 'male', 1)"
            :disabled="!canLog"
            class="w-32 h-32 rounded-[32px] flex flex-col items-center justify-center border border-white/10 group/btn bg-brand-blue text-white shadow-md hover:bg-brand-blue/90 hover:scale-105 active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
          >
            <div class="w-10 h-10 rounded-2xl bg-white/15 flex items-center justify-center mb-1 group-hover/btn:scale-110 transition-transform">
              <Users2 :size="20" class="text-white" />
            </div>
            <span class="text-sm font-bold capitalize tracking-widest">
              {{ langStore.localizeGender('male') }}
            </span>
            <span class="text-[10px] text-white/75 font-mono font-bold mt-0.5">+1</span>
          </button>

          <!-- Male Batch Grid -->
          <div class="grid grid-cols-4 sm:grid-cols-7 lg:grid-cols-4 xl:grid-cols-7 gap-1.5 justify-items-center">
            <button
              v-for="n in batchOptions"
              :key="'male-' + n"
              type="button"
              @click="emit('log', 'male', n)"
              :disabled="!canLog"
              :class="[
                getBatchClass(n),
                'bg-brand-blue/[0.04] dark:bg-brand-blue/15 text-brand-blue dark:text-blue-300 border-brand-blue/20 hover:bg-brand-blue hover:text-white dark:hover:bg-brand-blue dark:hover:text-white',
              ]"
            >
              +{{ n }}
            </button>
          </div>
        </div>

        <!-- Female Section matching LMS -->
        <div class="flex flex-col items-center gap-4 w-full">
          <button
            type="button"
            @click="emit('log', 'female', 1)"
            :disabled="!canLog"
            class="w-32 h-32 rounded-[32px] flex flex-col items-center justify-center border border-white/10 group/btn bg-brand-yellow text-white shadow-md hover:bg-brand-yellow/90 hover:scale-105 active:scale-95 transition-all disabled:opacity-40 disabled:cursor-not-allowed cursor-pointer"
          >
            <div class="w-10 h-10 rounded-2xl bg-white/15 flex items-center justify-center mb-1 group-hover/btn:scale-110 transition-transform">
              <Users2 :size="20" class="text-white" />
            </div>
            <span class="text-sm font-bold capitalize tracking-widest">
              {{ langStore.localizeGender('female') }}
            </span>
            <span class="text-[10px] text-white/75 font-mono font-bold mt-0.5">+1</span>
          </button>

          <!-- Female Batch Grid -->
          <div class="grid grid-cols-4 sm:grid-cols-7 lg:grid-cols-4 xl:grid-cols-7 gap-1.5 justify-items-center">
            <button
              v-for="n in batchOptions"
              :key="'female-' + n"
              type="button"
              @click="emit('log', 'female', n)"
              :disabled="!canLog"
              :class="[
                getBatchClass(n),
                'bg-amber-500/[0.04] dark:bg-amber-500/15 text-amber-600 dark:text-brand-yellow border-amber-500/20 hover:bg-brand-yellow hover:text-white dark:hover:bg-brand-yellow dark:hover:text-white',
              ]"
            >
              +{{ n }}
            </button>
          </div>
        </div>
      </div>

      <!-- Quick Guidance -->
      <p class="text-[11px] text-center text-main-text/40 pt-4 leading-relaxed">
        {{ langStore.t('attendance.quick_log_hint', 'Tap the large button for single visitors or use quick batch numbers (+2 .. +15) for group visits.') }}
      </p>
    </div>
  </div>
</template>
