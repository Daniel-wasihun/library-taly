<template>
  <div class="space-y-5 w-full">
    <!-- Top Selector Bar: Branch, Ethiopian Year, Month, Group -->
    <div class="bg-card-bg border border-card-border rounded-sm p-4 shadow-2xs space-y-4">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 items-end">
        <!-- 1. Library Branch Selector -->
        <div>
          <label class="block text-xs font-bold text-main-text mb-1.5 flex items-center gap-1.5">
            <Building2 class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('schedules.library_branch', 'Library Branch') }}</span>
          </label>
          <select
            v-model="selectedLibraryId"
            :disabled="isBranchScoped"
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs disabled:opacity-60 cursor-pointer"
            @change="handleLibraryChange"
          >
            <option v-for="lib in libraryStore.activeLibraries" :key="lib.id" :value="lib.id">
              {{ lib.name }} ({{ lib.code }})
            </option>
          </select>
        </div>

        <!-- 2. Ethiopian Year -->
        <div>
          <label class="block text-xs font-bold text-main-text mb-1.5 flex items-center gap-1.5">
            <Calendar class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('schedules.ethiopian_year', 'Ethiopian Year (ዓ.ም)') }}</span>
          </label>
          <select
            v-model.number="selectedYear"
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-mono cursor-pointer"
            @change="loadMatrix"
          >
            <option v-for="y in availableYears" :key="y" :value="y">
              {{ y }} ዓ.ም ({{ y + 7 }}/{{ y + 8 }} G.C.)
            </option>
          </select>
        </div>

        <!-- 3. Ethiopian Month (Meskerem to Pagume, Days 1-30) -->
        <div>
          <label class="block text-xs font-bold text-main-text mb-1.5 flex items-center gap-1.5">
            <CalendarDays class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('schedules.ethiopian_month', 'Ethiopian Month (ወር)') }}</span>
          </label>
          <select
            v-model.number="selectedMonth"
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-medium cursor-pointer"
            @change="loadMatrix"
          >
            <option
              v-for="m in shiftStore.matrixData?.ethiopian_months || defaultMonths"
              :key="m.number"
              :value="m.number"
            >
              {{ m.number }}. {{ m.name_am }} — {{ m.name_en }} (1–{{ m.days_count }} ቀን)
            </option>
          </select>
        </div>

        <!-- 4. Shift Group Selector (Group A, Group B, Group C...) -->
        <div>
          <label class="block text-xs font-bold text-main-text mb-1.5 flex items-center gap-1.5">
            <Users class="w-3.5 h-3.5 text-brand-blue dark:text-brand-yellow" />
            <span>{{ langStore.t('schedules.duty_group', 'Duty Group') }}</span>
          </label>
          <select
            v-model="selectedGroupId"
            class="w-full px-3 py-2 text-xs rounded-sm bg-input-bg border border-input-border text-main-text focus:outline-none focus:border-nav-accent shadow-2xs font-semibold cursor-pointer"
            @change="syncGroupDaysFromMatrix"
          >
            <option v-if="filteredGroups.length === 0" value="">No groups for this branch</option>
            <option v-for="g in filteredGroups" :key="g.id" :value="g.id">
              {{ g.name }} ({{ g.users?.length || 0 }} members)
            </option>
          </select>
        </div>
      </div>

      <!-- Live Context Banner -->
      <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-card-border text-xs text-main-text/75">
        <div class="flex items-center gap-3">
          <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-sm bg-brand-blue/10 text-brand-blue dark:text-brand-yellow font-bold text-[11px] border border-brand-blue/20">
            🇪🇹 {{ currentMonthNameAm }} {{ selectedYear }} ዓ.ም
          </span>
          <span class="text-xs">
            Assigned Library: <strong class="text-main-text">{{ currentLibraryName }}</strong>
          </span>
          <span v-if="selectedGroup" class="hidden sm:inline-block text-main-text/40">•</span>
          <span v-if="selectedGroup" class="text-xs">
            Group: <strong class="text-nav-accent">{{ selectedGroup.name }}</strong>
            <span class="text-[11px] text-main-text/50 ml-1">
              ({{ selectedGroup.users?.map(u => u.name).join(', ') || 'No members' }})
            </span>
          </span>
        </div>

        <!-- Legend -->
        <div class="flex flex-wrap items-center gap-3 text-[11px]">
          <div class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-sm bg-emerald-600 border border-emerald-700 text-white flex items-center justify-center text-[9px] font-bold">✓</span>
            <span>Selected</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-sm bg-card-bg border border-card-border"></span>
            <span>Available (1–30)</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-sm bg-amber-500/15 border border-amber-500/30 text-amber-600 dark:text-amber-400 flex items-center justify-center text-[9px] font-bold">⊘</span>
            <span>In Another Shift (Group)</span>
          </div>
          <div class="flex items-center gap-1.5">
            <span class="w-3.5 h-3.5 rounded-sm bg-rose-500/15 border border-rose-500/30 text-rose-600 dark:text-rose-400 flex items-center justify-center text-[9px] font-bold">🔒</span>
            <span>Assigned to Other Group</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Active Loading Spinner -->
    <div v-if="shiftStore.loading" class="p-12 text-center text-xs text-main-text/50 space-y-2">
      <div class="w-6 h-6 border-2 border-brand-blue border-t-transparent rounded-full animate-spin mx-auto"></div>
      <p>Loading Ethiopian calendar shift matrix...</p>
    </div>

    <!-- No Group Selected Prompt -->
    <div v-else-if="!selectedGroupId" class="bg-card-bg border border-card-border rounded-sm p-8 text-center text-xs text-main-text/60 space-y-2">
      <Users class="w-10 h-10 stroke-[1.5] text-main-text/30 mx-auto" />
      <p class="font-semibold text-main-text text-sm">No Duty Group Selected</p>
      <p>Please select a duty group above or create one in the Shift Groups tab to assign monthly shifts.</p>
    </div>

    <!-- Shift Calendar Sections (Day Shift, Night Shift, etc.) -->
    <div v-else class="space-y-6">
      <div
        v-for="shift in activeShifts"
        :key="shift.id"
        class="bg-card-bg border border-card-border rounded-sm p-4 sm:p-5 shadow-2xs space-y-4 transition-all duration-200"
      >
        <!-- Shift Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-card-border">
          <div class="flex items-center gap-3">
            <div
              class="w-10 h-10 rounded-sm flex items-center justify-center text-white font-bold text-sm shadow-xs"
              :class="shift.code === 'night' ? 'bg-indigo-600' : 'bg-amber-600'"
            >
              {{ shift.code === 'night' ? '🌙' : '☀️' }}
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-bold text-main-text">
                  {{ shift.name }}
                  <span v-if="shift.name_am" class="text-main-text/50 font-normal ml-1">({{ shift.name_am }})</span>
                </h3>
                <span
                  class="px-2 py-0.5 rounded-sm text-[10px] font-bold uppercase tracking-wider border"
                  :class="shift.code === 'night'
                    ? 'bg-indigo-500/10 text-indigo-700 dark:text-indigo-300 border-indigo-500/20'
                    : 'bg-amber-500/10 text-amber-700 dark:text-brand-yellow border-amber-500/20'"
                >
                  {{ shift.code }}
                </span>
              </div>
              <p class="text-xs text-main-text/60 mt-0.5 flex items-center gap-2">
                <span>Ethiopian Time: <strong class="text-main-text">{{ shift.ethiopian_start_time }} – {{ shift.ethiopian_end_time }}</strong></span>
                <span class="text-main-text/30">•</span>
                <span class="font-mono text-[11px]">24h: {{ shift.start_time }} – {{ shift.end_time }}</span>
              </p>
            </div>
          </div>

          <!-- Quick Day Selection Helpers -->
          <div class="flex items-center gap-1.5 self-start sm:self-auto">
            <button
              type="button"
              class="px-2.5 py-1 text-[11px] font-medium rounded-sm border border-card-border bg-input-bg hover:bg-nav-accent/10 hover:text-nav-accent transition cursor-pointer text-main-text"
              @click="selectOddDays(shift.id)"
            >
              Odd Days (1, 3, 5...)
            </button>
            <button
              type="button"
              class="px-2.5 py-1 text-[11px] font-medium rounded-sm border border-card-border bg-input-bg hover:bg-nav-accent/10 hover:text-nav-accent transition cursor-pointer text-main-text"
              @click="selectEvenDays(shift.id)"
            >
              Even Days (2, 4, 6...)
            </button>
            <button
              type="button"
              class="px-2.5 py-1 text-[11px] font-medium rounded-sm border border-card-border bg-input-bg hover:bg-rose-500/10 hover:text-rose-600 transition cursor-pointer text-main-text"
              @click="clearShiftDays(shift.id)"
            >
              Clear
            </button>
          </div>
        </div>

        <!-- 30-Day Ethiopian Calendar Button Grid (Days 1 to 30) -->
        <div>
          <div class="flex items-center justify-between text-xs text-main-text/60 mb-2">
            <span>
              Click days to toggle duty for <strong class="text-main-text">{{ selectedGroup?.name }}</strong>:
            </span>
            <span class="font-bold text-main-text text-xs">
              {{ (shiftSelections[shift.id] || []).length }} of {{ maxMonthDays }} days selected
            </span>
          </div>

          <!-- Days Grid (5 rows of 6 or responsive columns) -->
          <div class="grid grid-cols-5 sm:grid-cols-6 md:grid-cols-10 gap-2">
            <button
              v-for="day in maxMonthDays"
              :key="day"
              type="button"
              :disabled="isDayDisabled(shift.id, day).disabled"
              :title="getDayTooltip(shift.id, day)"
              class="relative flex flex-col items-center justify-center p-2 rounded-sm border text-xs transition-all duration-150 select-none group h-14"
              :class="getDayButtonClasses(shift.id, day)"
              @click="toggleDay(shift.id, day)"
            >
              <!-- Day Number in Ethiopian Calendar -->
              <span class="text-sm font-bold font-mono tracking-tight leading-none">
                {{ day }}
              </span>
              <span class="text-[9px] mt-1 leading-none font-medium truncate max-w-full px-1">
                {{ getDayLabel(shift.id, day) }}
              </span>

              <!-- Status Dot / Icon -->
              <span
                v-if="isDaySelected(shift.id, day)"
                class="absolute top-1 right-1 w-2 h-2 rounded-full bg-white shadow-xs"
              ></span>
              <span
                v-else-if="isDayDisabled(shift.id, day).isOtherGroup"
                class="absolute top-1 right-1 text-[9px] leading-none"
              >
                🔒
              </span>
              <span
                v-else-if="isDayDisabled(shift.id, day).isOtherShift"
                class="absolute top-1 right-1 text-[9px] leading-none"
              >
                ⊘
              </span>
            </button>
          </div>
        </div>
      </div>

      <!-- Action Footer: Save Schedule -->
      <div class="flex flex-col sm:flex-row items-center justify-between gap-4 p-4 rounded-sm bg-card-bg border border-card-border shadow-2xs">
        <div class="text-xs text-main-text/75 space-y-0.5">
          <p class="font-bold text-main-text text-sm">
            Save Monthly Duty Shift Assignment
          </p>
          <p>
            Applies duty turns for <span class="font-bold text-nav-accent">{{ selectedGroup?.name }}</span> across selected shifts for <span class="font-bold text-main-text">{{ currentMonthNameAm }} {{ selectedYear }} ዓ.ም</span>.
          </p>
        </div>

        <div class="flex items-center gap-3 w-full sm:w-auto">
          <Button
            variant="secondary"
            size="sm"
            @click="syncGroupDaysFromMatrix"
          >
            Reset Changes
          </Button>
          <Button
            variant="primary"
            size="sm"
            :loading="shiftStore.saving"
            @click="handleSaveSchedule"
          >
            Save Shift Schedule
          </Button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted } from 'vue';
import { useShiftStore } from '@/stores/shiftStore';
import { useLibraryStore } from '@/stores/libraryStore';
import { useAuthStore } from '@/stores/authStore';
import { useLanguageStore } from '@/stores/languageStore';
import { useToastStore } from '@/stores/toastStore';
import Button from '@/components/ui/Button.vue';
import {
  Building2,
  Calendar,
  CalendarDays,
  Users,
} from 'lucide-vue-next';
import type { ShiftGroup } from '@/types';

const shiftStore = useShiftStore();
const libraryStore = useLibraryStore();
const authStore = useAuthStore();
const langStore = useLanguageStore();
const toast = useToastStore();

// Default 13 Ethiopian months fallback
const defaultMonths = [
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

// Available Ethiopian years (e.g. 2017 to 2022 E.C.)
const availableYears = [2017, 2018, 2019, 2020, 2021, 2022];

// Selected State
const selectedLibraryId = ref<number>(1);
const selectedYear = ref<number>(2019);
const selectedMonth = ref<number>(3); // Hidar default
const selectedGroupId = ref<number | ''>('');

// Shift Selections: shift_id => Array of selected days [1, 2, ... 30]
const shiftSelections = reactive<Record<number, number[]>>({});

// Scoped Branch check
const isBranchScoped = computed(() => {
  return !authStore.user?.can_manage_all_libraries && !!authStore.user?.library_id;
});

// Filter groups belonging strictly to current library branch
const filteredGroups = computed(() => {
  const libId = selectedLibraryId.value;
  return (shiftStore.groups || []).filter((g) => {
    return g.library_id === libId;
  });
});

const selectedGroup = computed<ShiftGroup | undefined>(() => {
  if (!selectedGroupId.value) return undefined;
  return filteredGroups.value.find((g) => g.id === Number(selectedGroupId.value));
});

const activeShifts = computed(() => {
  return (shiftStore.shifts || []).filter((s) => s.is_active);
});

const currentLibraryName = computed(() => {
  const lib = (libraryStore.libraries || []).find((l) => l.id === selectedLibraryId.value);
  return lib ? lib.name : 'Selected Library';
});

const currentMonthNameAm = computed(() => {
  const m = defaultMonths.find((item) => item.number === selectedMonth.value);
  return m ? `${m.name_am} (${m.name_en})` : `Month ${selectedMonth.value}`;
});

const maxMonthDays = computed(() => {
  return selectedMonth.value === 13 ? 5 : 30;
});

/**
 * Load Matrix from Backend
 */
const loadMatrix = async () => {
  const res = await shiftStore.fetchMatrix({
    library_id: selectedLibraryId.value,
    ethiopian_year: selectedYear.value,
    ethiopian_month: selectedMonth.value,
  });

  if (res) {
    if (res.current_ethiopian_date && selectedYear.value === 2019 && selectedMonth.value === 3) {
      selectedYear.value = res.current_ethiopian_date.year;
      selectedMonth.value = res.current_ethiopian_date.month;
    }
    // Also refresh groups for this branch
    await shiftStore.fetchGroups(selectedLibraryId.value);

    // If no group is currently selected or selected group is outside this branch, select first group
    if (!selectedGroupId.value || !filteredGroups.value.some((g) => g.id === Number(selectedGroupId.value))) {
      selectedGroupId.value = filteredGroups.value.length > 0 ? filteredGroups.value[0].id : '';
    }

    syncGroupDaysFromMatrix();
  }
};

const handleLibraryChange = async () => {
  await loadMatrix();
};

/**
 * Synchronize local reactive shiftSelections from matrixData for the selected group
 */
const syncGroupDaysFromMatrix = () => {
  // Clear selections
  for (const key of Object.keys(shiftSelections)) {
    delete shiftSelections[Number(key)];
  }

  if (!selectedGroupId.value || !shiftStore.matrixData) return;

  const groupId = Number(selectedGroupId.value);
  const existingGroupScheds = shiftStore.matrixData.schedules.filter(
    (s) => s.shift_group_id === groupId
  );

  // Initialize for all active shifts
  for (const shift of activeShifts.value) {
    shiftSelections[shift.id] = [];
  }

  // Populate from existing schedules
  for (const sched of existingGroupScheds) {
    const shiftId = sched.shift_id;
    if (Array.isArray(sched.days)) {
      shiftSelections[shiftId] = [...sched.days];
    }
  }
};

/**
 * Check if a day is selected for a given shift
 */
const isDaySelected = (shiftId: number, day: number): boolean => {
  return (shiftSelections[shiftId] || []).includes(day);
};

/**
 * Disabling Logic enforcing the user's two rules:
 *
 * Rule 1: Intra-group cross-shift conflict:
 * "if the date is selected in one shift it must be disabled for another shif in the shchedule of the same scheduule assigning"
 *
 * Rule 2: Inter-group same-shift conflict:
 * "and after that in that same library for other group shift date assigning fo rthe shif date selction ui the date that are belonges to another group in that same shif must be diabled also meaning the date that is selceted in night shift is 3 this means in group B the night shif date list the day 3 must be diabled not able to selctable like this"
 */
const isDayDisabled = (
  shiftId: number,
  day: number
): { disabled: boolean; isOtherShift: boolean; otherShiftName?: string; isOtherGroup: boolean; otherGroupName?: string } => {
  // If already selected in this shift, it's not disabled (it's active and clickable to unselect)
  if (isDaySelected(shiftId, day)) {
    return { disabled: false, isOtherShift: false, isOtherGroup: false };
  }

  // 1. RULE 1 (Intra-group): Is this day selected in ANOTHER shift for this same group?
  for (const s of activeShifts.value) {
    if (s.id !== shiftId && isDaySelected(s.id, day)) {
      return {
        disabled: true,
        isOtherShift: true,
        otherShiftName: s.name,
        isOtherGroup: false,
      };
    }
  }

  // 2. RULE 2 (Inter-group): Is this day claimed by ANOTHER group in the same shift and library?
  const matrix = shiftStore.matrixData?.conflict_matrix?.by_shift;
  if (matrix && matrix[shiftId] && matrix[shiftId][day]) {
    const conflict = matrix[shiftId][day];
    // If it belongs to a different group, disable it!
    if (conflict.group_id !== Number(selectedGroupId.value)) {
      return {
        disabled: true,
        isOtherShift: false,
        isOtherGroup: true,
        otherGroupName: conflict.group_name,
      };
    }
  }

  return { disabled: false, isOtherShift: false, isOtherGroup: false };
};

/**
 * Get CSS classes for day buttons
 */
const getDayButtonClasses = (shiftId: number, day: number) => {
  const selected = isDaySelected(shiftId, day);
  const status = isDayDisabled(shiftId, day);

  if (selected) {
    return 'bg-emerald-600 text-white border-emerald-700 shadow-xs cursor-pointer hover:bg-emerald-700';
  }

  if (status.isOtherGroup) {
    return 'bg-rose-500/10 text-rose-700 dark:text-rose-400 border-rose-500/20 cursor-not-allowed opacity-60';
  }

  if (status.isOtherShift) {
    return 'bg-amber-500/10 text-amber-700 dark:text-amber-400 border-amber-500/20 cursor-not-allowed opacity-60';
  }

  // Available
  return 'bg-card-bg text-main-text border-card-border hover:border-nav-accent hover:bg-nav-accent/5 cursor-pointer shadow-2xs';
};

const getDayLabel = (shiftId: number, day: number): string => {
  const selected = isDaySelected(shiftId, day);
  if (selected) return 'Active';

  const status = isDayDisabled(shiftId, day);
  if (status.isOtherGroup) return status.otherGroupName || 'Occupied';
  if (status.isOtherShift) return status.otherShiftName || 'Other Shift';

  return 'Free';
};

const getDayTooltip = (shiftId: number, day: number): string => {
  const selected = isDaySelected(shiftId, day);
  if (selected) return `Day ${day}: Selected for this shift. Click to remove.`;

  const status = isDayDisabled(shiftId, day);
  if (status.isOtherGroup) {
    return `Day ${day}: Disabled — Already assigned to ${status.otherGroupName} in this library branch for this shift.`;
  }
  if (status.isOtherShift) {
    return `Day ${day}: Disabled — Already assigned to ${status.otherShiftName} for this group.`;
  }

  return `Day ${day}: Available. Click to assign to this shift.`;
};

/**
 * Toggle a day for a shift
 */
const toggleDay = (shiftId: number, day: number) => {
  const status = isDayDisabled(shiftId, day);
  if (status.disabled) {
    if (status.isOtherGroup) {
      toast.warning(`Day ${day} is already assigned to ${status.otherGroupName} for this shift.`);
    } else if (status.isOtherShift) {
      toast.warning(`Day ${day} is already selected for ${status.otherShiftName} in this group.`);
    }
    return;
  }

  if (!shiftSelections[shiftId]) {
    shiftSelections[shiftId] = [];
  }

  const idx = shiftSelections[shiftId].indexOf(day);
  if (idx >= 0) {
    shiftSelections[shiftId].splice(idx, 1);
  } else {
    shiftSelections[shiftId].push(day);
    shiftSelections[shiftId].sort((a, b) => a - b);
  }
};

/**
 * Quick helpers
 */
const selectOddDays = (shiftId: number) => {
  const newDays: number[] = [];
  for (let d = 1; d <= maxMonthDays.value; d += 2) {
    const status = isDayDisabled(shiftId, d);
    if (!status.disabled || isDaySelected(shiftId, d)) {
      newDays.push(d);
    }
  }
  shiftSelections[shiftId] = newDays;
};

const selectEvenDays = (shiftId: number) => {
  const newDays: number[] = [];
  for (let d = 2; d <= maxMonthDays.value; d += 2) {
    const status = isDayDisabled(shiftId, d);
    if (!status.disabled || isDaySelected(shiftId, d)) {
      newDays.push(d);
    }
  }
  shiftSelections[shiftId] = newDays;
};

const clearShiftDays = (shiftId: number) => {
  shiftSelections[shiftId] = [];
};

/**
 * Save Schedule to Backend
 */
const handleSaveSchedule = async () => {
  if (!selectedGroupId.value) {
    toast.error('Please select a duty group');
    return;
  }

  const payloadShifts = activeShifts.value.map((s) => ({
    shift_id: s.id,
    days: shiftSelections[s.id] || [],
  }));

  const success = await shiftStore.assignSchedule({
    library_id: selectedLibraryId.value,
    shift_group_id: Number(selectedGroupId.value),
    ethiopian_year: selectedYear.value,
    ethiopian_month: selectedMonth.value,
    shifts: payloadShifts,
  });

  if (success) {
    syncGroupDaysFromMatrix();
  }
};

watch(
  () => libraryStore.activeLibraries,
  (libs) => {
    if (libs && libs.length > 0 && !selectedLibraryId.value) {
      if (authStore.user?.library_id) {
        selectedLibraryId.value = authStore.user.library_id;
      } else {
        selectedLibraryId.value = libs[0].id;
      }
    }
  },
  { immediate: true }
);

onMounted(async () => {
  if (authStore.user?.library_id) {
    selectedLibraryId.value = authStore.user.library_id;
  }
  await Promise.all([
    libraryStore.fetchLibraries(),
    shiftStore.fetchShifts(),
  ]);
  await loadMatrix();
});
</script>
