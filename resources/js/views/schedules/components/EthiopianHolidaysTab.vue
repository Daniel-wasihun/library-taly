<template>
  <div class="space-y-5 w-full">
    <!-- Header with Year & Month Filter and Add Holiday Button -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-card-bg border border-card-border rounded-sm p-4 shadow-2xs">
      <div>
        <h3 class="text-base font-bold text-main-text flex items-center gap-2">
          <CalendarOff class="w-5 h-5 text-purple-600 dark:text-purple-400" />
          <span>{{ langStore.t('schedules.holidays_tab_title', 'Ethiopian Public & University Holidays') }}</span>
        </h3>
        <p class="text-xs text-main-text/60 mt-0.5">
          {{ langStore.t('schedules.holidays_tab_desc', 'Manually register official Ethiopian holidays for any month. Shifts on these dates are canceled automatically across all library branches.') }}
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <!-- Year Filter -->
        <div class="flex items-center gap-1.5">
          <label class="text-xs font-semibold text-main-text whitespace-nowrap">{{ langStore.t('schedules.ethiopian_year', 'Year') }}:</label>
          <div class="relative">
            <select
              v-model.number="selectedYear"
              class="h-8 appearance-none pl-2.5 pr-7 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text font-mono hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
              @change="fetchHolidays"
            >
              <option v-for="y in [2017, 2018, 2019, 2020, 2021, 2022]" :key="y" :value="y">
                {{ langStore.isAmharic() ? `${y} ዓ.ም` : `${y} E.C.` }}
              </option>
            </select>
            <div class="absolute right-2 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
              <ChevronDown class="w-3.5 h-3.5" />
            </div>
          </div>
        </div>

        <!-- Month Filter -->
        <div class="flex items-center gap-1.5">
          <label class="text-xs font-semibold text-main-text whitespace-nowrap">{{ langStore.t('schedules.ethiopian_month', 'Month') }}:</label>
          <div class="relative">
            <select
              v-model="selectedMonth"
              class="h-8 appearance-none pl-2.5 pr-7 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer font-medium"
              @change="fetchHolidays"
            >
              <option value="">{{ langStore.isAmharic() ? 'ሁሉም ወራት (1–13)' : 'All Months (1–13)' }}</option>
              <option v-for="m in ethiopianMonths" :key="m.number" :value="m.number">
                {{ m.number }}. {{ langStore.isAmharic() ? m.name_am : m.name_en }}
              </option>
            </select>
            <div class="absolute right-2 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
              <ChevronDown class="w-3.5 h-3.5" />
            </div>
          </div>
        </div>

        <Button
          v-if="canManageHolidays"
          variant="primary"
          size="sm"
          :icon="Plus"
          @click="openAddModal"
        >
          {{ langStore.t('schedules.add_holiday', 'Add Holiday') }}
        </Button>
      </div>
    </div>

    <!-- Active Filter Notice Banner -->
    <div class="rounded-sm bg-purple-500/10 border border-purple-500/25 p-3 flex items-center justify-between text-xs text-main-text">
      <div class="flex items-center gap-2">
        <span class="text-base">🇪🇹</span>
        <span>
          {{ langStore.isAmharic() ? 'የተመዘገቡ በዓላት ለ' : 'Showing manual holidays for' }}
          <strong class="font-bold text-nav-accent">{{ selectedMonth ? getMonthName(Number(selectedMonth)) : (langStore.isAmharic() ? 'ሁሉም ወራት' : 'All Months') }} {{ langStore.isAmharic() ? `${selectedYear} ዓ.ም` : `${selectedYear} E.C.` }}</strong>:
          <strong class="text-purple-600 dark:text-purple-300 ml-1">{{ holidaysList.length }} {{ langStore.t('schedules.holidays_detected_count', 'holidays recorded') }}</strong>.
        </span>
      </div>
      <span class="text-[11px] font-medium text-main-text/60 hidden sm:inline-block">
        {{ langStore.isAmharic() ? 'በእነዚህ በዓላት ቀናት በሁሉም ቅርንጫፎች የተረኝነት ፈረቃዎች ተሰርዘዋል።' : 'All duty shifts on recorded holiday days are canceled across all library branches.' }}
      </span>
    </div>

    <!-- Holidays Table / Grid -->
    <div v-if="loading" class="p-8 text-center text-xs text-main-text/50">
      <div class="w-6 h-6 border-2 border-brand-blue border-t-transparent rounded-full animate-spin mx-auto mb-2"></div>
      {{ langStore.t('common.loading', 'Loading holidays...') }}
    </div>

    <div v-else-if="holidaysList.length === 0" class="p-8 rounded-sm bg-card-bg border border-card-border text-center text-xs text-main-text/60">
      <CalendarOff class="w-10 h-10 stroke-[1.5] text-main-text/30 mx-auto mb-2" />
      <p class="font-bold text-main-text">{{ langStore.t('schedules.no_holidays_in_month', 'No Holidays Recorded for this Period') }}</p>
      <p class="mt-1">
        {{ langStore.isAmharic() ? 'ለዚህ ወርና ዓ.ም ይፋዊ በዓላትን ለማስገባት ከላይ የሚገኘውን "+ በዓል ጨምር" ይጫኑ።' : 'Click "+ Add Holiday" above to manually enter holidays for this Ethiopian month and year.' }}
      </p>
    </div>

    <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
      <div
        v-for="h in holidaysList"
        :key="h.id"
        class="bg-card-bg border border-card-border rounded-sm p-4 shadow-2xs space-y-3 flex flex-col justify-between"
      >
        <div class="space-y-2.5">
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-2.5">
              <div class="w-10 h-10 rounded-sm bg-purple-500/15 border border-purple-500/30 flex flex-col items-center justify-center shrink-0">
                <span class="text-[10px] uppercase font-bold text-purple-700 dark:text-purple-300 leading-none">{{ langStore.isAmharic() ? 'ቀን' : 'Day' }}</span>
                <span class="text-base font-black text-purple-800 dark:text-purple-200 font-mono leading-none mt-0.5">{{ h.day }}</span>
              </div>
              <div>
                <h4 class="font-bold text-sm text-main-text">
                  <span>{{ langStore.isAmharic() ? (h.name_am || h.name) : (h.name || h.name_am) }}</span>
                </h4>
                <p v-if="h.description" class="text-xs text-main-text/60 font-medium">
                  {{ h.description }}
                </p>
                <span class="text-[10px] font-mono text-nav-accent font-semibold">
                  {{ getMonthName(h.month || Number(selectedMonth) || 1) }} {{ langStore.isAmharic() ? `${selectedYear} ዓ.ም` : `${selectedYear} E.C.` }}
                </span>
              </div>
            </div>

            <div v-if="canManageHolidays" class="flex items-center gap-1">
              <button
                type="button"
                class="p-1.5 rounded-sm hover:bg-rose-500/10 text-rose-600 transition cursor-pointer"
                title="Delete Holiday"
                @click="confirmDelete(h)"
              >
                <Trash2 class="w-3.5 h-3.5" />
              </button>
            </div>
          </div>

          <p v-if="h.description" class="text-xs text-main-text/70 line-clamp-2">
            {{ h.description }}
          </p>
        </div>

        <div class="pt-2 border-t border-card-border flex items-center justify-between text-[11px]">
          <span class="text-main-text/50">{{ langStore.t('schedules.shift_status', 'Duty Status') }}:</span>
          <span class="px-2 py-0.5 rounded-sm font-bold text-[10px] bg-purple-500/15 text-purple-700 dark:text-purple-300 border border-purple-500/25 flex items-center gap-1">
            <span>🚫</span>
            <span>{{ langStore.t('schedules.canceled', 'Canceled') }}</span>
          </span>
        </div>
      </div>
    </div>

    <!-- Add / Edit Holiday Modal -->
    <Modal
      :show="showModal"
      :title="langStore.t('schedules.add_holiday_title', 'Add Manual Ethiopian Holiday')"
      :description="langStore.t('schedules.add_holiday_subtitle', 'Manually enter a public or university holiday in the Ethiopian calendar. The selected day will be cleanly canceled from shift schedules.')"
      :icon="CalendarOff"
      size="md"
      @close="showModal = false"
    >
      <form @submit.prevent="handleSave" class="space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <!-- Ethiopian Year -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('schedules.ethiopian_year', 'Year') }} *
            </label>
            <div class="relative">
              <select
                v-model.number="form.ethiopian_year"
                class="w-full h-9 appearance-none pl-2.5 pr-7 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text font-mono hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
                required
              >
                <option v-for="y in [2017, 2018, 2019, 2020, 2021, 2022]" :key="y" :value="y">
                  {{ langStore.isAmharic() ? `${y} ዓ.ም` : `${y} E.C.` }}
                </option>
              </select>
              <div class="absolute right-2 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
                <ChevronDown class="w-3.5 h-3.5" />
              </div>
            </div>
          </div>

          <!-- Ethiopian Month -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('schedules.ethiopian_month', 'Month') }} *
            </label>
            <div class="relative">
              <select
                v-model.number="form.ethiopian_month"
                class="w-full h-9 appearance-none pl-2.5 pr-7 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer font-medium"
                required
              >
                <option v-for="m in ethiopianMonths" :key="m.number" :value="m.number">
                  {{ m.number }}. {{ langStore.isAmharic() ? m.name_am : m.name_en }}
                </option>
              </select>
              <div class="absolute right-2 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
                <ChevronDown class="w-3.5 h-3.5" />
              </div>
            </div>
          </div>

          <!-- Ethiopian Day (1 to 30) -->
          <div>
            <label class="block text-xs font-semibold text-main-text mb-1">
              {{ langStore.t('schedules.day_of_month', 'Day') }} *
            </label>
            <div class="relative">
              <select
                v-model.number="form.ethiopian_day"
                class="w-full h-9 appearance-none pl-2.5 pr-7 text-xs rounded-md bg-card-bg border border-card-border/80 text-main-text font-mono hover:border-nav-accent/50 focus:outline-none focus:ring-2 focus:ring-brand-blue/20 focus:border-nav-accent shadow-2xs transition cursor-pointer"
                required
              >
                <option v-for="d in formDaysCount" :key="d" :value="d">
                  {{ langStore.isAmharic() ? `ቀን ${d}` : `Day ${d}` }}
                </option>
              </select>
              <div class="absolute right-2 top-1/2 -translate-y-1/2 text-main-text/40 pointer-events-none">
                <ChevronDown class="w-3.5 h-3.5" />
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            {{ langStore.t('schedules.holiday_name_en', 'Holiday Name (English)') }} *
          </label>
          <input
            v-model="form.name"
            type="text"
            required
            placeholder="e.g. Hidar Zion, Adwa Victory Day, Epiphany..."
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:border-nav-accent shadow-2xs font-semibold"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            {{ langStore.t('schedules.holiday_name_am', 'Amharic Title') }}
          </label>
          <input
            v-model="form.name_am"
            type="text"
            placeholder="e.g. ኅዳር ጽዮን፣ የዐድዋ ድል በዓል፣ ጥምቀት..."
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:border-nav-accent shadow-2xs font-medium"
          />
        </div>

        <div>
          <label class="block text-xs font-semibold text-main-text mb-1">
            {{ langStore.t('schedules.description', 'Description (optional)') }}
          </label>
          <textarea
            v-model="form.description"
            rows="2"
            placeholder="Operational notes, celebration details..."
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:border-nav-accent shadow-2xs resize-none"
          ></textarea>
        </div>

        <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-card-border">
          <Button
            variant="outline"
            size="sm"
            type="button"
            @click="showModal = false"
          >
            {{ langStore.t('common.cancel', 'Cancel') }}
          </Button>
          <Button
            variant="primary"
            size="sm"
            type="submit"
            :loading="shiftStore.saving"
          >
            {{ langStore.t('schedules.save_holiday', 'Save Holiday') }}
          </Button>
        </div>
      </form>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue';
import { useShiftStore } from '@/stores/shiftStore';
import { useAuthStore } from '@/stores/authStore';
import { useLanguageStore } from '@/stores/languageStore';
import { useToastStore } from '@/stores/toastStore';
import Button from '@/components/ui/Button.vue';
import Modal from '@/components/ui/Modal.vue';
import { CalendarOff, Plus, Trash2, ChevronDown } from 'lucide-vue-next';
import api from '@/api/client';

const shiftStore = useShiftStore();
const authStore = useAuthStore();
const langStore = useLanguageStore();
const toast = useToastStore();

const loading = ref(false);
const showModal = ref(false);

const selectedYear = ref<number>(2019);
const selectedMonth = ref<number | ''>(3); // Hidar default

const holidaysList = ref<any[]>([]);

const ethiopianMonths = [
  { number: 1, name_am: 'መስከረም', name_en: 'Meskerem', days_count: 30 },
  { number: 2, name_am: 'ጥቅምት', name_en: 'Tikimt', days_count: 30 },
  { number: 3, name_am: 'ኅዳር', name_en: 'Hidar', days_count: 30 },
  { number: 4, name_am: 'ታኅሣሥ', name_en: 'Tahsas', days_count: 30 },
  { number: 5, name_am: 'ጥር', name_en: 'Tir', days_count: 30 },
  { number: 6, name_am: 'የካቲት', name_en: 'Yakatit', days_count: 30 },
  { number: 7, name_am: 'መጋቢት', name_en: 'Megabit', days_count: 30 },
  { number: 8, name_am: 'ሚያዝያ', name_en: 'Miazia', days_count: 30 },
  { number: 9, name_am: 'ግንቦት', name_en: 'Ginbot', days_count: 30 },
  { number: 10, name_am: 'ሰኔ', name_en: 'Sene', days_count: 30 },
  { number: 11, name_am: 'ሐምሌ', name_en: 'Hamle', days_count: 30 },
  { number: 12, name_am: 'ነሐሴ', name_en: 'Nehase', days_count: 30 },
  { number: 13, name_am: 'ጳጉሜን', name_en: 'Pagume', days_count: 5 },
];

const form = reactive({
  ethiopian_year: 2019,
  ethiopian_month: 3,
  ethiopian_day: 1,
  name: '',
  name_am: '',
  description: '',
});

const canManageHolidays = computed(() => {
  return authStore.isAdmin || authStore.canManageLibrary;
});

const formDaysCount = computed(() => {
  return form.ethiopian_month === 13 ? 5 : 30;
});

const getMonthName = (monthNum: number): string => {
  const m = ethiopianMonths.find((item) => item.number === monthNum);
  if (!m) return `Month ${monthNum}`;
  return langStore.isAmharic() ? m.name_am : m.name_en;
};

const fetchHolidays = async () => {
  try {
    loading.value = true;
    const res = await api.get('/shift-schedules/holidays', {
      params: {
        ethiopian_year: selectedYear.value,
        ethiopian_month: selectedMonth.value || undefined,
      },
    });

    const data = res.data?.holidays || {};
    // holidays is an object indexed by day { "12": {...} }
    holidaysList.value = Object.values(data).map((h: any) => ({
      ...h,
      month: h.month || Number(selectedMonth.value) || 3,
    }));
  } catch (err: any) {
    toast.error('Failed to load Ethiopian holidays');
  } finally {
    loading.value = false;
  }
};

const openAddModal = () => {
  form.ethiopian_year = selectedYear.value;
  form.ethiopian_month = Number(selectedMonth.value) || 3;
  form.ethiopian_day = 1;
  form.name = '';
  form.name_am = '';
  form.description = '';
  showModal.value = true;
};

const handleSave = async () => {
  if (!form.name) {
    toast.error('Please enter a holiday name');
    return;
  }

  const success = await shiftStore.addHoliday({
    ethiopian_year: form.ethiopian_year,
    ethiopian_month: form.ethiopian_month,
    ethiopian_day: form.ethiopian_day,
    name: form.name,
    name_am: form.name_am || form.name,
    description: form.description,
    library_id: null, // applies to all branches
  });

  if (success) {
    showModal.value = false;
    await fetchHolidays();
  }
};

const confirmDelete = async (holiday: any) => {
  if (!confirm(`Are you sure you want to delete the holiday "${holiday.name_am || holiday.name}"?`)) {
    return;
  }
  const success = await shiftStore.deleteHoliday(holiday.id);
  if (success) {
    await fetchHolidays();
  }
};

onMounted(() => {
  fetchHolidays();
});
</script>
