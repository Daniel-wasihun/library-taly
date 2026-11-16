<script setup lang="ts">
import { computed } from 'vue';
import { Users } from 'lucide-vue-next';
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
</script>

<template>
  <div class="lg:col-span-5 xl:col-span-4 bg-card-bg border border-card-border rounded-2xl p-6 flex flex-col items-center justify-start shadow-2xs relative">
    <div class="w-full space-y-6">
      <!-- Section Header -->
      <div class="text-center pb-2 border-b border-card-border/60">
        <span class="text-xs uppercase tracking-wider font-bold text-main-text/50">
          Usage Counter Desk · {{ langStore.t('attendance.' + selectedUserType, selectedUserType) }}
        </span>
      </div>

      <!-- Male Logging Column -->
      <div class="space-y-3">
        <button
          type="button"
          :disabled="!canLog"
          @click="emit('log', 'male', 1)"
          class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white font-bold flex items-center justify-between shadow-md transition-all active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed group cursor-pointer"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center group-hover:scale-110 transition-transform">
              <Users class="w-5 h-5 text-white" />
            </div>
            <div class="text-left">
              <div class="text-sm font-bold tracking-tight">
                +1 {{ langStore.t('attendance.male', 'Male') }}
              </div>
              <div class="text-[10px] text-white/70 uppercase tracking-wider">
                Single Click Log
              </div>
            </div>
          </div>
          <span class="px-2.5 py-1 rounded-lg bg-white/20 text-xs font-mono font-bold">+1</span>
        </button>

        <!-- Male Batch Grid -->
        <div class="grid grid-cols-7 gap-1.5 pt-1">
          <button
            v-for="n in batchOptions"
            :key="'male-' + n"
            type="button"
            :disabled="!canLog"
            @click="emit('log', 'male', n)"
            class="h-9 rounded-lg font-bold text-xs flex items-center justify-center transition-all bg-blue-500/10 text-blue-600 dark:text-blue-400 border border-blue-500/20 hover:bg-blue-600 hover:text-white dark:hover:text-white hover:border-transparent active:scale-95 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
          >
            +{{ n }}
          </button>
        </div>
      </div>

      <!-- Female Logging Column -->
      <div class="space-y-3 pt-2">
        <button
          type="button"
          :disabled="!canLog"
          @click="emit('log', 'female', 1)"
          class="w-full py-4 px-6 rounded-2xl bg-gradient-to-r from-amber-500 to-rose-500 hover:from-amber-600 hover:to-rose-600 text-white font-bold flex items-center justify-between shadow-md transition-all active:scale-[0.98] disabled:opacity-40 disabled:cursor-not-allowed group cursor-pointer"
        >
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-white/20 flex items-center justify-center group-hover:scale-110 transition-transform">
              <Users class="w-5 h-5 text-white" />
            </div>
            <div class="text-left">
              <div class="text-sm font-bold tracking-tight">
                +1 {{ langStore.t('attendance.female', 'Female') }}
              </div>
              <div class="text-[10px] text-white/70 uppercase tracking-wider">
                Single Click Log
              </div>
            </div>
          </div>
          <span class="px-2.5 py-1 rounded-lg bg-white/20 text-xs font-mono font-bold">+1</span>
        </button>

        <!-- Female Batch Grid -->
        <div class="grid grid-cols-7 gap-1.5 pt-1">
          <button
            v-for="n in batchOptions"
            :key="'female-' + n"
            type="button"
            :disabled="!canLog"
            @click="emit('log', 'female', n)"
            class="h-9 rounded-lg font-bold text-xs flex items-center justify-center transition-all bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/20 hover:bg-amber-600 hover:text-white dark:hover:text-white hover:border-transparent active:scale-95 disabled:opacity-30 disabled:cursor-not-allowed cursor-pointer"
          >
            +{{ n }}
          </button>
        </div>
      </div>

      <!-- Quick Guidance -->
      <p class="text-[11px] text-center text-main-text/45 pt-2">
        Tap the large button for single visitor entries or use quick increment numbers (+2 .. +15) for group visits.
      </p>
    </div>
  </div>
</template>
