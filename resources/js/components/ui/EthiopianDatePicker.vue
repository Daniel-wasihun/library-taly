<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Calendar, ChevronDown, ChevronUp, Check, X } from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';
import { toEthiopian, toGregorian, formatDT } from '@/utils/ethiopianDate';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    label?: string;
    disabled?: boolean;
    required?: boolean;
    compact?: boolean;
  }>(),
  {
    label: '',
    disabled: false,
    required: false,
    compact: false,
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'change', value: string): void;
}>();

const langStore = useLanguageStore();

const ethiopianMonths = [
  { number: 1, name_am: 'መስከረም', name_en: 'Meskerem' },
  { number: 2, name_am: 'ጥቅምት', name_en: 'Tikimt' },
  { number: 3, name_am: 'ኅዳር', name_en: 'Hidar' },
  { number: 4, name_am: 'ታኅሣሥ', name_en: 'Tahisas' },
  { number: 5, name_am: 'ጥር', name_en: 'Tir' },
  { number: 6, name_am: 'የካቲት', name_en: 'Yekatit' },
  { number: 7, name_am: 'መጋቢት', name_en: 'Megabit' },
  { number: 8, name_am: 'ሚያዝያ', name_en: 'Miazia' },
  { number: 9, name_am: 'ግንቦት', name_en: 'Ginbot' },
  { number: 10, name_am: 'ሰኔ', name_en: 'Sene' },
  { number: 11, name_am: 'ሐምሌ', name_en: 'Hamle' },
  { number: 12, name_am: 'ነሐሴ', name_en: 'Nehase' },
  { number: 13, name_am: 'ጳጉሜን', name_en: 'Pagume' },
];

const availableEthiopianYears = computed(() => {
  const currentEthYear = toEthiopian(new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate())[0];
  const list: number[] = [];
  for (let y = currentEthYear - 4; y <= currentEthYear + 2; y++) {
    list.push(y);
  }
  return list;
});

const isPickerOpen = ref(false);
const pickerRef = ref<HTMLElement | null>(null);

const ethDate = ref({ year: 2019, month: 3, day: 10 });

const syncFromModel = () => {
  if (props.modelValue) {
    const parts = props.modelValue.split('-').map(Number);
    if (parts.length === 3) {
      const [ey, em, ed] = toEthiopian(parts[0], parts[1], parts[2]);
      ethDate.value = { year: ey, month: em, day: ed };
    }
  }
};

watch(() => props.modelValue, syncFromModel, { immediate: true });

const getDaysInEthMonth = (year: number, month: number) => {
  if (month === 13) {
    return (year % 4 === 3) ? 6 : 5;
  }
  return 30;
};

const ethToIso = (y: number, m: number, d: number): string => {
  const [gy, gm, gd] = toGregorian(y, m, d);
  const mm = String(gm).padStart(2, '0');
  const dd = String(gd).padStart(2, '0');
  return `${gy}-${mm}-${dd}`;
};

const toLocalIso = (d: Date = new Date()): string => {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const updateFromEth = () => {
  const maxDay = getDaysInEthMonth(ethDate.value.year, ethDate.value.month);
  if (ethDate.value.day > maxDay) ethDate.value.day = maxDay;
  const iso = ethToIso(ethDate.value.year, ethDate.value.month, ethDate.value.day);
  emit('update:modelValue', iso);
  emit('change', iso);
};

const onGregorianInput = (e: Event) => {
  const val = (e.target as HTMLInputElement).value;
  if (val) {
    emit('update:modelValue', val);
    emit('change', val);
  }
};

const setQuickDate = (offsetDays: number) => {
  const d = new Date();
  d.setDate(d.getDate() + offsetDays);
  const iso = toLocalIso(d);
  emit('update:modelValue', iso);
  emit('change', iso);
};

const displayLabel = computed(() => {
  if (!props.modelValue) return '—';
  const isAmharic = langStore.isAmharic();
  const res = formatDT(props.modelValue, isAmharic);
  return res.full || res.date;
});

const secondaryLabel = computed(() => {
  if (!props.modelValue) return '—';
  const isAmharic = langStore.isAmharic();
  const res = formatDT(props.modelValue, !isAmharic);
  return res.date;
});

const handleClickOutside = (e: MouseEvent) => {
  if (pickerRef.value && !pickerRef.value.contains(e.target as Node)) {
    isPickerOpen.value = false;
  }
};

onMounted(() => {
  document.addEventListener('mousedown', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('mousedown', handleClickOutside);
});
</script>

<template>
  <div ref="pickerRef" class="relative inline-block w-full text-xs">
    <label v-if="label" class="block text-xs font-semibold text-main-text mb-1">
      {{ label }} <span v-if="required" class="text-rose-500">*</span>
    </label>

    <!-- Trigger -->
    <div
      class="h-9 px-3 rounded border border-card-border bg-card-bg text-main-text flex items-center justify-between gap-2 shadow-2xs hover:border-nav-accent/50 transition cursor-pointer select-none"
      :class="{ 'opacity-50 pointer-events-none': disabled }"
      @click="isPickerOpen = !isPickerOpen"
    >
      <div class="flex items-center gap-2 min-w-0">
        <Calendar class="w-4 h-4 text-brand-blue dark:text-brand-yellow shrink-0" />
        <span class="font-mono font-bold text-xs truncate">{{ displayLabel }}</span>
        <span class="text-[9px] font-mono text-main-text/40 hidden sm:inline truncate">({{ secondaryLabel }})</span>
      </div>
      <component :is="isPickerOpen ? ChevronUp : ChevronDown" class="w-3.5 h-3.5 text-main-text/40 shrink-0" />
    </div>

    <!-- Dropdown Popover -->
    <div
      v-if="isPickerOpen"
      class="absolute left-0 right-0 sm:right-auto sm:w-[320px] top-full mt-1.5 bg-card-bg border border-card-border rounded-md shadow-xl p-3 z-50 space-y-3 animate-in fade-in duration-150"
    >
      <div class="flex items-center justify-between border-b border-card-border pb-1.5">
        <span class="text-[11px] font-bold uppercase tracking-wider text-main-text">
          {{ langStore.isAmharic() ? 'ቀን ይምረጡ (የኢትዮጵያ ዘመን)' : 'Select Date (Gregorian)' }}
        </span>
        <button type="button" @click="isPickerOpen = false" class="text-main-text/50 hover:text-main-text">
          <X class="w-3.5 h-3.5" />
        </button>
      </div>

      <!-- Quick choices -->
      <div class="flex items-center gap-1.5 flex-wrap">
        <button
          type="button"
          @click="setQuickDate(0)"
          class="px-2 py-0.5 rounded text-[10px] font-semibold border border-card-border bg-main-bg/50 hover:bg-brand-blue/10 hover:text-brand-blue"
        >
          {{ langStore.isAmharic() ? 'ዛሬ' : 'Today' }}
        </button>
        <button
          type="button"
          @click="setQuickDate(1)"
          class="px-2 py-0.5 rounded text-[10px] font-semibold border border-card-border bg-main-bg/50 hover:bg-brand-blue/10 hover:text-brand-blue"
        >
          {{ langStore.isAmharic() ? 'ነገ' : 'Tomorrow' }}
        </button>
        <button
          type="button"
          @click="setQuickDate(-1)"
          class="px-2 py-0.5 rounded text-[10px] font-semibold border border-card-border bg-main-bg/50 hover:bg-brand-blue/10 hover:text-brand-blue"
        >
          {{ langStore.isAmharic() ? 'ትናንት' : 'Yesterday' }}
        </button>
      </div>

      <!-- Amharic Ethiopian Pickers -->
      <div v-if="langStore.isAmharic()" class="grid grid-cols-3 gap-1.5">
        <div>
          <label class="text-[9px] text-main-text/60 font-semibold block mb-0.5">ዓመት</label>
          <select
            v-model.number="ethDate.year"
            @change="updateFromEth"
            class="w-full h-8 px-1.5 text-xs rounded border border-card-border bg-card-bg text-main-text font-mono"
          >
            <option v-for="y in availableEthiopianYears" :key="y" :value="y">{{ y }} ዓ.ም</option>
          </select>
        </div>
        <div>
          <label class="text-[9px] text-main-text/60 font-semibold block mb-0.5">ወር</label>
          <select
            v-model.number="ethDate.month"
            @change="updateFromEth"
            class="w-full h-8 px-1 text-xs rounded border border-card-border bg-card-bg text-main-text font-semibold"
          >
            <option v-for="m in ethiopianMonths" :key="m.number" :value="m.number">
              {{ m.number }}. {{ m.name_am }}
            </option>
          </select>
        </div>
        <div>
          <label class="text-[9px] text-main-text/60 font-semibold block mb-0.5">ቀን</label>
          <select
            v-model.number="ethDate.day"
            @change="updateFromEth"
            class="w-full h-8 px-1 text-xs rounded border border-card-border bg-card-bg text-main-text font-mono"
          >
            <option v-for="d in getDaysInEthMonth(ethDate.year, ethDate.month)" :key="d" :value="d">
              {{ d }}
            </option>
          </select>
        </div>
      </div>

      <!-- English Gregorian Picker -->
      <div v-else>
        <input
          type="date"
          :value="modelValue"
          @input="onGregorianInput"
          class="w-full h-9 px-2 text-xs font-mono font-bold rounded border border-card-border bg-card-bg text-main-text focus:outline-none focus:ring-1 focus:ring-brand-blue"
        />
      </div>

      <!-- Close Action -->
      <div class="flex items-center justify-between pt-1.5 border-t border-card-border">
        <span class="text-[9px] font-mono text-main-text/50">{{ modelValue }}</span>
        <button
          type="button"
          @click="isPickerOpen = false"
          class="px-3 py-1 rounded text-xs font-bold bg-brand-blue text-white shadow-2xs hover:bg-brand-blue/90"
        >
          {{ langStore.isAmharic() ? 'እሺ' : 'Done' }}
        </button>
      </div>
    </div>
  </div>
</template>
