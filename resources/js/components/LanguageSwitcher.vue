<template>
  <div class="relative inline-block text-left" ref="dropdownRef">
    <button
      @click="isOpen = !isOpen"
      type="button"
      class="inline-flex items-center gap-x-2 px-3 py-1.5 text-xs font-semibold rounded-xl border border-card-border bg-card-bg hover:bg-slate-100 dark:hover:bg-slate-800 text-main-text shadow-2xs transition cursor-pointer select-none"
    >
      <span>{{ currentOption?.icon }}</span>
      <span class="tracking-tight">{{ currentOption?.label || currentOption?.name }}</span>
      <ChevronDown class="w-3.5 h-3.5 text-main-text/40 transition-transform duration-200" :class="{ 'rotate-180': isOpen }" />
    </button>

    <div
      v-if="isOpen"
      class="absolute right-0 mt-2 w-48 rounded-2xl shadow-xl bg-card-bg border border-card-border ring-1 ring-black/5 z-(--z-dropdown) py-1.5 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-150"
    >
      <button
        v-for="lang in languageStore.availableLanguages"
        :key="lang.key"
        @click="selectLanguage(lang.key)"
        class="w-full text-left px-4 py-2.5 text-xs flex items-center justify-between text-main-text/80 hover:bg-nav-accent/10 hover:text-nav-accent transition cursor-pointer select-none"
        :class="{ 'font-bold bg-nav-accent/10 text-nav-accent': languageStore.currentLanguage === lang.key }"
      >
        <span class="flex items-center gap-x-2.5">
          <span class="text-base">{{ lang.icon }}</span>
          <span class="font-medium" :class="{ 'font-bold text-nav-accent': languageStore.currentLanguage === lang.key }">{{ lang.label }}</span>
        </span>
        <Check v-if="languageStore.currentLanguage === lang.key" class="w-4 h-4 text-nav-accent" />
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useLanguageStore } from '@/stores/languageStore';
import { ChevronDown, Check } from 'lucide-vue-next';

const languageStore = useLanguageStore();
const isOpen = ref(false);
const dropdownRef = ref<HTMLElement | null>(null);

const currentOption = computed(() => {
  return languageStore.availableLanguages.find(
    (l) => l.key === languageStore.currentLanguage
  );
});

const selectLanguage = async (key: string) => {
  isOpen.value = false;
  await languageStore.setLanguage(key);
};

const handleClickOutside = (e: MouseEvent) => {
  if (dropdownRef.value && !dropdownRef.value.contains(e.target as Node)) {
    isOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>
