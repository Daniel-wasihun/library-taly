<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import {
  Calendar,
  CalendarDays,
  Clock,
  ArrowRight,
  ChevronDown,
  ChevronUp,
  Check,
  X,
  Sparkles,
} from 'lucide-vue-next';
import { useLanguageStore } from '@/stores/languageStore';
import { toEthiopian, toGregorian, formatDT } from '@/utils/ethiopianDate';

interface DateRange {
  from: string;
  to: string;
}

const props = withDefaults(
  defineProps<{
    modelValue: DateRange;
    loading?: boolean;
    compact?: boolean;
  }>(),
  {
    loading: false,
    compact: false,
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', value: DateRange): void;
  (e: 'change', value: DateRange): void;
}>();

const langStore = useLanguageStore();

// Ethiopian Months Definition
const ethiopianMonths = [
  { number: 1, name_am: 'መስከረም', name_en: 'Meskerem', days: 30 },
  { number: 2, name_am: 'ጥቅምት', name_en: 'Tikimt', days: 30 },
  { number: 3, name_am: 'ኅዳር', name_en: 'Hidar', days: 30 },
  { number: 4, name_am: 'ታኅሣሥ', name_en: 'Tahisas', days: 30 },
  { number: 5, name_am: 'ጥር', name_en: 'Tir', days: 30 },
  { number: 6, name_am: 'የካቲት', name_en: 'Yekatit', days: 30 },
  { number: 7, name_am: 'መጋቢት', name_en: 'Megabit', days: 30 },
  { number: 8, name_am: 'ሚያዝያ', name_en: 'Miazia', days: 30 },
  { number: 9, name_am: 'ግንቦት', name_en: 'Ginbot', days: 30 },
  { number: 10, name_am: 'ሰኔ', name_en: 'Sene', days: 30 },
  { number: 11, name_am: 'ሐምሌ', name_en: 'Hamle', days: 30 },
  { number: 12, name_am: 'ነሐሴ', name_en: 'Nehase', days: 30 },
  { number: 13, name_am: 'ጳጉሜን', name_en: 'Pagume', days: 6 },
];

const availableEthiopianYears = computed(() => {
  const currentEthYear = toEthiopian(new Date().getFullYear(), new Date().getMonth() + 1, new Date().getDate())[0];
  const list: number[] = [];
  for (let y = currentEthYear - 4; y <= currentEthYear + 2; y++) {
    list.push(y);
  }
  return list;
});

// Dropdown / Popover State
const isPickerOpen = ref(false);
const pickerRef = ref<HTMLElement | null>(null);

// Ethiopian State for Custom Picking
const fromEth = ref({ year: 2019, month: 3, day: 1 });
const toEth = ref({ year: 2019, month: 3, day: 10 });

// Sync Ethiopian state whenever modelValue changes
const syncFromModel = () => {
  if (props.modelValue.from) {
    const parts = props.modelValue.from.split('-').map(Number);
    if (parts.length === 3) {
      const [ey, em, ed] = toEthiopian(parts[0], parts[1], parts[2]);
      fromEth.value = { year: ey, month: em, day: ed };
    }
  }
  if (props.modelValue.to) {
    const parts = props.modelValue.to.split('-').map(Number);
    if (parts.length === 3) {
      const [ey, em, ed] = toEthiopian(parts[0], parts[1], parts[2]);
      toEth.value = { year: ey, month: em, day: ed };
    }
  }
};

watch(() => props.modelValue, syncFromModel, { immediate: true, deep: true });

// Convert Ethiopian [y, m, d] to ISO 'YYYY-MM-DD'
const ethToIso = (y: number, m: number, d: number): string => {
  const [gy, gm, gd] = toGregorian(y, m, d);
  const mm = String(gm).padStart(2, '0');
  const dd = String(gd).padStart(2, '0');
  return `${gy}-${mm}-${dd}`;
};

// Days count for selected Ethiopian month & year
const getDaysInEthMonth = (year: number, month: number) => {
  if (month === 13) {
    return (year % 4 === 3) ? 6 : 5;
  }
  return 30;
};

// Update from Ethiopian pickers
const updateFromEth = () => {
  const maxDay = getDaysInEthMonth(fromEth.value.year, fromEth.value.month);
  if (fromEth.value.day > maxDay) fromEth.value.day = maxDay;
  const iso = ethToIso(fromEth.value.year, fromEth.value.month, fromEth.value.day);
  emitRange({ from: iso, to: props.modelValue.to });
};

const updateToEth = () => {
  const maxDay = getDaysInEthMonth(toEth.value.year, toEth.value.month);
  if (toEth.value.day > maxDay) toEth.value.day = maxDay;
  const iso = ethToIso(toEth.value.year, toEth.value.month, toEth.value.day);
  emitRange({ from: props.modelValue.from, to: iso });
};

// Gregorian Date Input handlers
const onGregorianFromInput = (e: Event) => {
  const val = (e.target as HTMLInputElement).value;
  if (val) emitRange({ from: val, to: props.modelValue.to });
};

const onGregorianToInput = (e: Event) => {
  const val = (e.target as HTMLInputElement).value;
  if (val) emitRange({ from: props.modelValue.from, to: val });
};

const emitRange = (range: DateRange) => {
  emit('update:modelValue', range);
  emit('change', range);
};

// Local ISO date formatter avoiding UTC midnight offset bugs
const toLocalIso = (d: Date = new Date()): string => {
  const year = d.getFullYear();
  const month = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

// Active preset tracker
const activePreset = ref<'today' | 'week' | 'month' | 'academic_year' | 'custom'>('today');

// Preset ranges
const applyPreset = (preset: 'today' | 'week' | 'month' | 'academic_year') => {
  activePreset.value = preset;
  const now = new Date();
  const todayIso = toLocalIso(now);
  const isAmharic = langStore.isAmharic();

  if (preset === 'today') {
    emitRange({ from: todayIso, to: todayIso });
    return;
  }

  if (preset === 'week') {
    const past7 = new Date(now.getTime() - 7 * 24 * 60 * 60 * 1000);
    emitRange({ from: toLocalIso(past7), to: todayIso });
    return;
  }

  if (preset === 'month') {
    if (isAmharic) {
      // Exact 1st day of current Ethiopian month (e.g. ኅዳር 1)
      const [ey, em] = toEthiopian(now.getFullYear(), now.getMonth() + 1, now.getDate());
      const fromIso = ethToIso(ey, em, 1);
      emitRange({ from: fromIso, to: todayIso });
    } else {
      // Exact 1st day of current Gregorian month (e.g. Nov 1)
      const fromIso = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`;
      emitRange({ from: fromIso, to: todayIso });
    }
    return;
  }

  if (preset === 'academic_year') {
    if (isAmharic) {
      // Meskerem 1 of current Ethiopian Year
      const [ey] = toEthiopian(now.getFullYear(), now.getMonth() + 1, now.getDate());
      const fromIso = ethToIso(ey, 1, 1);
      emitRange({ from: fromIso, to: todayIso });
    } else {
      // Sep 1 of academic year
      const acadYear = now.getMonth() >= 8 ? now.getFullYear() : now.getFullYear() - 1;
      emitRange({ from: `${acadYear}-09-01`, to: todayIso });
    }
    return;
  }
};

// Listen for language changes and automatically realign calendar intervals (e.g. month preset)
watch(
  () => langStore.currentLanguage,
  () => {
    if (activePreset.value === 'month') {
      applyPreset('month');
    } else if (activePreset.value === 'academic_year') {
      applyPreset('academic_year');
    } else if (activePreset.value === 'today') {
      applyPreset('today');
    } else {
      syncFromModel();
    }
  }
);

// Formatted active display strings
const activeRangeLabel = computed(() => {
  const isAmharic = langStore.isAmharic();
  const f = formatDT(props.modelValue.from, isAmharic);
  const t = formatDT(props.modelValue.to, isAmharic);

  if (props.modelValue.from === props.modelValue.to) {
    return f.date;
  }
  return `${f.date} – ${t.date}`;
});

const secondaryRangeLabel = computed(() => {
  // Show opposite calendar for cross-verification
  const isAmharic = langStore.isAmharic();
  const f = formatDT(props.modelValue.from, !isAmharic);
  const t = formatDT(props.modelValue.to, !isAmharic);

  if (props.modelValue.from === props.modelValue.to) {
    return f.date;
  }
  return `${f.date} – ${t.date}`;
});

// Close popover when clicking outside
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
    <!-- Active Range Display Card / Trigger Button -->
    <div
      class="bg-card-bg border border-card-border rounded-md p-2.5 sm:p-3 shadow-2xs hover:border-nav-accent/50 transition cursor-pointer select-none space-y-2"
      @click="isPickerOpen = !isPickerOpen"
    >
      <div class="flex items-center justify-between gap-2">
        <div class="flex items-center gap-2">
          <div class="p-1.5 rounded-sm bg-brand-blue/10 text-brand-blue dark:text-brand-yellow shrink-0">
            <Calendar class="w-4 h-4" />
          </div>
          <div>
            <div class="flex items-center gap-1.5 font-bold text-main-text text-xs leading-none">
              <span>{{ activeRangeLabel }}</span>
              <span class="text-[9px] px-1.5 py-0.5 rounded-full font-mono uppercase bg-slate-100 dark:bg-slate-800 text-main-text/60 border border-card-border">
                {{ langStore.isAmharic() ? 'የኢ.ዘ.አ (E.C.)' : 'G.C.' }}
              </span>
            </div>
            <div class="text-[10px] text-main-text/50 mt-1 font-mono flex items-center gap-1">
              <span>{{ langStore.isAmharic() ? 'የፈረንጆች ዘመን (G.C.)፡' : 'Ethiopian (E.C.):' }}</span>
              <span class="font-semibold">{{ secondaryRangeLabel }}</span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-1.5 text-main-text/50">
          <span class="text-[10px] font-semibold hidden sm:inline">
            {{ isPickerOpen ? (langStore.isAmharic() ? 'ዝጋ' : 'Close') : (langStore.isAmharic() ? 'ቀን ቀይር' : 'Change') }}
          </span>
          <component :is="isPickerOpen ? ChevronUp : ChevronDown" class="w-4 h-4" />
        </div>
      </div>

      <!-- Quick Preset Filter Buttons Bar -->
      <div class="flex items-center gap-1.5 pt-1.5 border-t border-card-border/60 flex-wrap" @click.stop>
        <button
          type="button"
          @click="applyPreset('today')"
          class="px-2.5 py-1 rounded text-[10px] font-bold transition border"
          :class="activePreset === 'today'
            ? 'bg-brand-blue text-white border-brand-blue shadow-2xs'
            : 'border-card-border/60 hover:bg-brand-blue/10 hover:text-brand-blue text-main-text/70'"
        >
          {{ langStore.isAmharic() ? 'ዛሬ' : 'Today' }}
        </button>
        <button
          type="button"
          @click="applyPreset('week')"
          class="px-2.5 py-1 rounded text-[10px] font-bold transition border"
          :class="activePreset === 'week'
            ? 'bg-brand-blue text-white border-brand-blue shadow-2xs'
            : 'border-card-border/60 hover:bg-brand-blue/10 hover:text-brand-blue text-main-text/70'"
        >
          {{ langStore.isAmharic() ? '7 ቀናት' : '7 Days' }}
        </button>
        <button
          type="button"
          @click="applyPreset('month')"
          class="px-2.5 py-1 rounded text-[10px] font-bold transition border"
          :class="activePreset === 'month'
            ? 'bg-brand-blue text-white border-brand-blue shadow-2xs'
            : 'border-card-border/60 hover:bg-brand-blue/10 hover:text-brand-blue text-main-text/70'"
        >
          {{ langStore.isAmharic() ? 'ይህ ወር (ወርኃዊ)' : 'This Month' }}
        </button>
        <button
          type="button"
          @click="applyPreset('academic_year')"
          class="px-2.5 py-1 rounded text-[10px] font-bold transition border"
          :class="activePreset === 'academic_year'
            ? 'bg-brand-blue text-white border-brand-blue shadow-2xs'
            : 'border-card-border/60 hover:bg-brand-blue/10 hover:text-brand-blue text-main-text/70'"
        >
          {{ langStore.isAmharic() ? 'አካዳሚክ ዓመት' : 'Academic Year' }}
        </button>
      </div>
    </div>

    <!-- Dropdown / Popover Panel for Custom Date Range Selection -->
    <div
      v-if="isPickerOpen"
      class="absolute left-0 right-0 sm:right-auto sm:w-[460px] top-full mt-2 bg-card-bg border border-card-border rounded-md shadow-xl p-4 z-50 space-y-4 animate-in fade-in slide-in-from-top-2 duration-150"
    >
      <div class="flex items-center justify-between border-b border-card-border pb-2.5">
        <div class="flex items-center gap-1.5 font-bold text-main-text text-xs uppercase tracking-wider">
          <CalendarDays class="w-4 h-4 text-brand-blue dark:text-brand-yellow" />
          <span>{{ langStore.isAmharic() ? 'የቀን ወሰን መምረጫ (የኢትዮጵያ ዘመን አቆጣጠር)' : 'Select Date Range Interval' }}</span>
        </div>
        <button
          type="button"
          @click="isPickerOpen = false"
          class="p-1 rounded text-main-text/50 hover:text-main-text hover:bg-main-text/5"
        >
          <X class="w-4 h-4" />
        </button>
      </div>

      <!-- Mode A: Ethiopian Calendar Selectors (Active when Amharic) -->
      <div v-if="langStore.isAmharic()" class="space-y-4">
        <!-- 1. From Date (ከ) -->
        <div class="space-y-1.5 p-3 rounded-md bg-main-bg/50 border border-card-border">
          <label class="block text-[11px] font-bold text-brand-blue uppercase tracking-wider flex items-center justify-between">
            <span>ከ (የመጀመሪያ ቀን)</span>
            <span class="font-mono text-[10px] text-main-text/60">{{ formatDT(modelValue.from, true).full }}</span>
          </label>
          <div class="grid grid-cols-3 gap-2">
            <!-- Year -->
            <div>
              <label class="text-[9px] text-main-text/60 font-semibold block mb-0.5">ዓመት</label>
              <select
                v-model.number="fromEth.year"
                @change="updateFromEth"
                class="w-full h-8 px-2 text-xs rounded border border-card-border bg-card-bg text-main-text font-mono"
              >
                <option v-for="y in availableEthiopianYears" :key="y" :value="y">{{ y }} ዓ.ም</option>
              </select>
            </div>
            <!-- Month -->
            <div>
              <label class="text-[9px] text-main-text/60 font-semibold block mb-0.5">ወር</label>
              <select
                v-model.number="fromEth.month"
                @change="updateFromEth"
                class="w-full h-8 px-2 text-xs rounded border border-card-border bg-card-bg text-main-text font-semibold"
              >
                <option v-for="m in ethiopianMonths" :key="m.number" :value="m.number">
                  {{ m.number }}. {{ m.name_am }}
                </option>
              </select>
            </div>
            <!-- Day -->
            <div>
              <label class="text-[9px] text-main-text/60 font-semibold block mb-0.5">ቀን</label>
              <select
                v-model.number="fromEth.day"
                @change="updateFromEth"
                class="w-full h-8 px-2 text-xs rounded border border-card-border bg-card-bg text-main-text font-mono"
              >
                <option v-for="d in getDaysInEthMonth(fromEth.year, fromEth.month)" :key="d" :value="d">
                  {{ d }} ቀን
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- 2. To Date (እስከ) -->
        <div class="space-y-1.5 p-3 rounded-md bg-main-bg/50 border border-card-border">
          <label class="block text-[11px] font-bold text-amber-600 dark:text-brand-yellow uppercase tracking-wider flex items-center justify-between">
            <span>እስከ (የመጨረሻ ቀን)</span>
            <span class="font-mono text-[10px] text-main-text/60">{{ formatDT(modelValue.to, true).full }}</span>
          </label>
          <div class="grid grid-cols-3 gap-2">
            <!-- Year -->
            <div>
              <label class="text-[9px] text-main-text/60 font-semibold block mb-0.5">ዓመት</label>
              <select
                v-model.number="toEth.year"
                @change="updateToEth"
                class="w-full h-8 px-2 text-xs rounded border border-card-border bg-card-bg text-main-text font-mono"
              >
                <option v-for="y in availableEthiopianYears" :key="y" :value="y">{{ y }} ዓ.ም</option>
              </select>
            </div>
            <!-- Month -->
            <div>
              <label class="text-[9px] text-main-text/60 font-semibold block mb-0.5">ወር</label>
              <select
                v-model.number="toEth.month"
                @change="updateToEth"
                class="w-full h-8 px-2 text-xs rounded border border-card-border bg-card-bg text-main-text font-semibold"
              >
                <option v-for="m in ethiopianMonths" :key="m.number" :value="m.number">
                  {{ m.number }}. {{ m.name_am }}
                </option>
              </select>
            </div>
            <!-- Day -->
            <div>
              <label class="text-[9px] text-main-text/60 font-semibold block mb-0.5">ቀን</label>
              <select
                v-model.number="toEth.day"
                @change="updateToEth"
                class="w-full h-8 px-2 text-xs rounded border border-card-border bg-card-bg text-main-text font-mono"
              >
                <option v-for="d in getDaysInEthMonth(toEth.year, toEth.month)" :key="d" :value="d">
                  {{ d }} ቀን
                </option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- Mode B: Gregorian Calendar Date Pickers (Active when English) -->
      <div v-else class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div class="p-3 rounded-md bg-main-bg/50 border border-card-border space-y-1.5">
            <label class="block text-[11px] font-bold text-main-text uppercase tracking-wider">
              From (Start Date)
            </label>
            <input
              type="date"
              :value="modelValue.from"
              @input="onGregorianFromInput"
              class="w-full h-9 px-2 text-xs font-mono font-bold rounded border border-card-border bg-card-bg text-main-text focus:outline-none focus:ring-1 focus:ring-brand-blue"
            />
            <div class="text-[10px] text-main-text/50 font-mono">
              {{ formatDT(modelValue.from, false).full }}
            </div>
          </div>

          <div class="p-3 rounded-md bg-main-bg/50 border border-card-border space-y-1.5">
            <label class="block text-[11px] font-bold text-main-text uppercase tracking-wider">
              To (End Date)
            </label>
            <input
              type="date"
              :value="modelValue.to"
              @input="onGregorianToInput"
              class="w-full h-9 px-2 text-xs font-mono font-bold rounded border border-card-border bg-card-bg text-main-text focus:outline-none focus:ring-1 focus:ring-brand-blue"
            />
            <div class="text-[10px] text-main-text/50 font-mono">
              {{ formatDT(modelValue.to, false).full }}
            </div>
          </div>
        </div>
      </div>

      <!-- Footer Buttons -->
      <div class="flex items-center justify-between pt-2 border-t border-card-border">
        <span class="text-[10px] text-main-text/60 font-mono">
          {{ modelValue.from }} → {{ modelValue.to }}
        </span>
        <button
          type="button"
          @click="isPickerOpen = false"
          class="px-4 py-1.5 rounded-sm bg-brand-blue text-white font-bold text-xs hover:bg-brand-blue/90 transition shadow-2xs flex items-center gap-1.5"
        >
          <Check class="w-3.5 h-3.5" />
          <span>{{ langStore.isAmharic() ? 'ተግብር' : 'Apply Window' }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
